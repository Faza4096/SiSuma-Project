<?php

namespace App\Http\Controllers;

use App\Models\DisposisiTujuan;
use App\Models\LogAktivitas;
use App\Models\PengaturanInstansi;
use App\Models\SuratMasuk;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class DashboardController extends Controller
{
    /** Urutan sifat: segera dulu, lalu penting, rahasia, terakhir biasa. */
    private const URUT_SIFAT = "case sifat when 'segera' then 0 when 'penting' then 1 when 'rahasia' then 2 else 3 end";

    public function __invoke(Request $request)
    {
        $user = $request->user();
        $peran = $user->role?->nama ?? 'super';

        return view('dashboard', [
            'peran' => $peran,
            'instansi' => PengaturanInstansi::current()->nama,
            'ringkasan' => $this->ringkasan($user),
            'bagian' => $this->bagian($user),
            'tren' => $this->tren($user),
            'akun' => $peran === 'admin' ? $this->ringkasanAkun() : null,
            'log' => $peran === 'admin'
                ? LogAktivitas::with('pengguna')->latest('waktu')->take(10)->get()
                : collect(),
        ]);
    }

    /** Satu baris ringkasan, bukan deretan kartu statistik. */
    private function ringkasan(User $user): array
    {
        $awalBulan = now()->startOfMonth();

        return [
            'masuk' => SuratMasuk::terlihatOleh($user)->where('tgl_diterima', '>=', $awalBulan)->count(),
            'register' => SuratMasuk::terlihatOleh($user)
                ->whereHas('disposisi', fn ($q) => $q->where('tgl_disposisi', '>=', $awalBulan))
                ->count(),
            'terbuka' => $this->tindakLanjutQuery($user)->count(),
        ];
    }

    private function tindakLanjutQuery(User $user)
    {
        return DisposisiTujuan::where('status_tindak_lanjut', '!=', 'selesai')
            ->terlihatOleh($user);
    }

    /** Daftar pekerjaan sesuai peran. Administrator fokus pada pengelolaan sistem. */
    private function bagian(User $user): array
    {
        return match ($user->role?->nama) {
            'admin' => [],
            'super' => $this->bagianSuper($user),
            default => [],
        };
    }

    private function bagianSuper(User $user): array
    {
        return [
            $this->antreanBelumRegister($user),
            $this->antreanBelumArsip($user),
        ];
    }

    private function antreanBelumRegister(User $user): array
    {
        $items = SuratMasuk::with('instansi')
            ->terlihatOleh($user)
            ->where('status', 'diterima')
            ->whereDoesntHave('disposisi')
            ->orderByRaw(self::URUT_SIFAT)
            ->orderBy('tgl_diterima')
            ->take(8)
            ->get()
            ->map(fn (SuratMasuk $s) => $this->barisSuratMasuk($s, $s->tgl_diterima) + [
                'aksi' => ['label' => 'Register', 'url' => route('register.form', $s)],
            ]);

        return [
            'judul' => 'Belum diregister',
            'catatan' => 'Surat sudah tercatat dan menunggu dibuatkan register.',
            'items' => $items,
            'kosong' => 'Semua surat masuk sudah diregister.',
            'tautan' => ['label' => 'Catat surat masuk', 'url' => route('surat-masuk.create')],
        ];
    }

    private function antreanBelumArsip(User $user): array
    {
        $items = SuratMasuk::with('instansi')
            ->terlihatOleh($user)
            ->where('status', 'selesai')
            ->latest('tgl_diterima')
            ->take(8)
            ->get()
            ->map(fn (SuratMasuk $s) => $this->barisSuratMasuk($s, null, 'Surat masuk'));

        return [
            'judul' => 'Belum diarsipkan',
            'catatan' => 'Tindak lanjut selesai, arsip fisik belum dicatat.',
            'items' => $items,
            'kosong' => 'Semua surat sudah diarsipkan.',
            'tautan' => ['label' => 'Diarsipkan', 'url' => route('arsip.index')],
        ];
    }

    private function barisSuratMasuk(SuratMasuk $s, ?Carbon $acuan, ?string $jenis = null): array
    {
        return [
            'nomor' => $s->no_agenda,
            'judul' => $s->perihal,
            'meta' => trim(($jenis ? $jenis . ' · ' : '') . ($s->instansi?->nama ?? '-')),
            'sifat' => $s->sifat,
            'waktu' => $acuan ? 'Masuk ' . $acuan->translatedFormat('d M Y') : optional($s->tgl_diterima)->translatedFormat('d M Y'),
            'keadaan' => $acuan && $acuan->lt(now()->subDays(3)) ? 'dekat' : null,
            'url' => route('surat-masuk.show', $s),
        ];
    }

    /** Grafik enam bulan dari satu query agregat surat masuk. */
    private function tren(User $user): array
    {
        $awal = now()->startOfMonth()->subMonths(5);

        $masuk = SuratMasuk::terlihatOleh($user)
            ->where('tgl_diterima', '>=', $awal)
            ->get(['tgl_diterima'])
            ->groupBy(fn ($s) => $s->tgl_diterima->format('Y-m'))
            ->map->count();

        $bulan = collect(range(5, 0))->map(function ($mundur) use ($masuk) {
            $awal = now()->copy()->subMonths($mundur)->startOfMonth();

            return [
                'label' => $awal->translatedFormat('M'),
                'masuk' => (int) ($masuk[$awal->format('Y-m')] ?? 0),
            ];
        });

        return [
            'bulan' => $bulan,
            'maks' => max(1, $bulan->max('masuk')),
        ];
    }

    private function ringkasanAkun(): array
    {
        return [
            'total' => User::count(),
            'aktif' => User::where('aktif', true)->count(),
            'wajib_ganti' => User::where('must_change_password', true)->count(),
            'peran' => User::query()
                ->join('roles', 'roles.id', '=', 'users.role_id')
                ->selectRaw('roles.nama, count(*) as jumlah')
                ->groupBy('roles.nama')
                ->pluck('jumlah', 'nama'),
        ];
    }
}
