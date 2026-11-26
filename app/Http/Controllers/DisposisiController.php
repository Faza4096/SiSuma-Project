<?php

namespace App\Http\Controllers;

use App\Models\Disposisi;
use App\Models\DisposisiTujuan;
use App\Models\LogAktivitas;
use App\Models\SuratMasuk;
use App\Models\UnitKerja;
use App\Models\User;
use App\Notifications\DisposisiBaru;
use App\Notifications\StatusTindakLanjut;
use App\Services\KartuDisposisiService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class DisposisiController extends Controller
{
    /** Buku register surat masuk beserta status tindak lanjut tiap unit. */
    public function index(Request $request)
    {
        $items = $this->query($request)->paginate(15)->withQueryString();

        return view('disposisi.index', [
            'items' => $items,
            'units' => UnitKerja::orderBy('nama')->get(),
            'terpilih' => (array) $request->query('ids', []),
            'menunggu' => $this->belumDiregister($request->user()),
        ]);
    }

    /**
     * Form register untuk surat masuk (terisi otomatis dari surat). Instruksi
     * dan penerusan kepada unit boleh dikosongkan karena menunggu perintah
     * pimpinan.
     */
    public function formRegister(SuratMasuk $suratMasuk)
    {
        $this->authorize('register', $suratMasuk);
        $this->authorize('view', $suratMasuk);
        $suratMasuk->load('instansi', 'klasifikasi');

        return view('disposisi.acc', [
            's' => $suratMasuk,
            'units' => UnitKerja::orderBy('nama')->get(),
        ]);
    }

    /**
     * Simpan register surat masuk. Bila instruksi dan tujuan dikosongkan,
     * register tetap tersimpan dan surat menunggu perintah pimpinan.
     */
    public function store(Request $request, SuratMasuk $suratMasuk)
    {
        $this->authorize('register', $suratMasuk);

        $data = $request->validate([
            'indeks' => ['required', 'string', 'max:255'],
            'tgl_penyelesaian' => ['nullable', 'date', 'after_or_equal:today'],
            'instruksi' => ['nullable', 'string'],
            'tujuan' => ['nullable', 'array'],
            'tujuan.*' => ['exists:unit_kerja,id'],
        ]);

        $diteruskan = ! empty($data['tujuan']);

        $disposisi = DB::transaction(function () use ($data, $request, $suratMasuk, $diteruskan) {
            $disposisi = $this->buatDisposisi($request->user(), $data, $suratMasuk);

            if ($diteruskan) {
                $suratMasuk->update(['status' => 'didisposisi']);
            }

            LogAktivitas::catat('register.simpan', $suratMasuk->no_agenda);

            return $disposisi;
        });

        $disposisi = app(KartuDisposisiService::class)->simpan($disposisi);

        if ($diteruskan) {
            $this->kirimNotifikasi($disposisi);
        }

        $pesan = $diteruskan
            ? 'Register tersimpan. Kartu dibuat dan surat diteruskan ke unit tujuan.'
            : 'Register tersimpan. Instruksi dan penerusan dapat dilengkapi setelah ada perintah pimpinan.';

        return redirect()->route('surat-masuk.show', $suratMasuk)->with('ok', $pesan);
    }

    /** Halaman kartu register siap cetak. */
    public function kartu(Disposisi $disposisi)
    {
        $this->authorize('view', $disposisi);
        $this->muatRelasi($disposisi);

        return view('disposisi.kartu', ['d' => $disposisi]);
    }

    /** Unduh PDF kartu satu register (dibuat ulang bila berkas hilang). */
    public function pdf(Disposisi $disposisi)
    {
        $this->authorize('unduhKartu', $disposisi);

        $disposisi = app(KartuDisposisiService::class)->pastikan($disposisi);
        LogAktivitas::catat('kartu.unduh', $disposisi->nomorSurat());

        return Storage::download($disposisi->kartu_path, $disposisi->namaBerkasKartu());
    }

    /** Cetak/unduh beberapa kartu sekaligus dalam satu PDF. */
    public function cetak(Request $request)
    {
        $data = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer', 'exists:disposisi,id'],
        ]);

        $items = Disposisi::with('suratMasuk', 'tujuan.unit', 'pembuat')
            ->whereIn('id', $data['ids'])
            ->orderBy('tgl_disposisi')->get();

        foreach ($items as $item) {
            $this->authorize('unduhKartu', $item);
        }

        LogAktivitas::catat('kartu.unduh.massal', $items->count().' kartu register');

        return app(KartuDisposisiService::class)->unduhBanyak($items);
    }

    /**
     * Perbarui tindak lanjut. Administrator dan Super TU memantau semua unit.
     * Kartu PDF diperbarui otomatis setelah perubahan.
     */
    public function ubahStatus(Request $request, DisposisiTujuan $tujuan)
    {
        $this->authorize('ubah', $tujuan);

        $data = $request->validate([
            'status' => ['required', Rule::in(array_keys(DisposisiTujuan::STATUS))],
            'catatan' => ['nullable', 'string', 'max:2000'],
        ]);

        $tujuan->update([
            'status_tindak_lanjut' => $data['status'],
            'catatan' => $data['catatan'] ?? $tujuan->catatan,
            'tgl_selesai' => $data['status'] === 'selesai' ? now() : null,
            'selesai_oleh' => $data['status'] === 'selesai' ? $request->user()->id : null,
        ]);

        $disposisi = $tujuan->disposisi;
        $surat = $disposisi->suratMasuk;

        if ($surat) {
            $surat->update(['status' => $this->hitungStatusSurat($surat)]);
            LogAktivitas::catat('register.status', "{$surat->no_agenda} → {$data['status']}");
        }

        app(KartuDisposisiService::class)->simpan($disposisi->fresh());

        $this->kabariUnitTujuan($tujuan, $data['status'], $request->user());

        return back()->with('ok', 'Status tindak lanjut diperbarui dan kartu register disegarkan.');
    }

    /** Unggah bukti tindak lanjut (foto/PDF) oleh unit tujuan. */
    public function unggahBukti(Request $request, DisposisiTujuan $tujuan)
    {
        $this->authorize('ubah', $tujuan);

        $request->validate([
            'bukti' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:10240'],
            'catatan' => ['nullable', 'string', 'max:2000'],
        ]);

        if ($tujuan->bukti_path) {
            Storage::delete($tujuan->bukti_path);
        }

        $file = $request->file('bukti');
        $tujuan->update([
            'bukti_path' => $file->store('disposisi/bukti'),
            'bukti_nama' => $file->getClientOriginalName(),
            'diunggah_pada' => now(),
            'catatan' => $request->input('catatan', $tujuan->catatan),
            'status_tindak_lanjut' => $tujuan->status_tindak_lanjut === 'belum' ? 'diproses' : $tujuan->status_tindak_lanjut,
        ]);

        $disposisi = $tujuan->disposisi;

        if ($disposisi->suratMasuk) {
            $disposisi->suratMasuk->update([
                'status' => $this->hitungStatusSurat($disposisi->suratMasuk),
            ]);
        }

        LogAktivitas::catat('register.bukti', "{$disposisi->nomorSurat()}: {$file->getClientOriginalName()}");

        return back()->with('ok', 'Bukti tindak lanjut tersimpan.');
    }

    /** Unduh bukti tindak lanjut (berkas privat, mengikuti akses surat). */
    public function unduhBukti(DisposisiTujuan $tujuan)
    {
        $this->authorize('view', $tujuan);
        abort_unless($tujuan->bukti_path && Storage::exists($tujuan->bukti_path), 404, 'Bukti tidak ditemukan.');

        return Storage::download($tujuan->bukti_path, $tujuan->bukti_nama ?? 'bukti');
    }

    public function hapusBukti(DisposisiTujuan $tujuan)
    {
        $this->authorize('ubah', $tujuan);

        if ($tujuan->bukti_path) {
            Storage::delete($tujuan->bukti_path);
        }

        $tujuan->update(['bukti_path' => null, 'bukti_nama' => null, 'diunggah_pada' => null]);
        LogAktivitas::catat('register.bukti.hapus', $tujuan->disposisi->nomorSurat());

        return back()->with('ok', 'Bukti tindak lanjut dihapus.');
    }

    public function destroy(Disposisi $disposisi)
    {
        $this->authorize('delete', $disposisi);

        DB::transaction(function () use ($disposisi) {
            $surat = $disposisi->suratMasuk;

            app(KartuDisposisiService::class)->hapus($disposisi);
            $disposisi->tujuan()->delete();
            $disposisi->delete();

            if ($surat) {
                $surat->update(['status' => $this->hitungStatusSurat($surat)]);
            }
            LogAktivitas::catat('register.hapus', $disposisi->nomorSurat());
        });

        return back()->with('ok', 'Register dibatalkan.');
    }

    private function buatDisposisi(User $pembuat, array $data, SuratMasuk $surat): Disposisi
    {
        $disposisi = Disposisi::create([
            'surat_masuk_id' => $surat->id,
            'dibuat_oleh' => $pembuat->id,
            'indeks' => $data['indeks'] ?? $surat->klasifikasi?->kode,
            'tgl_penyelesaian' => $data['tgl_penyelesaian'] ?? null,
            'instruksi' => filled($data['instruksi'] ?? null) ? trim($data['instruksi']) : null,
            'tgl_disposisi' => now(),
        ]);

        foreach (array_unique($data['tujuan'] ?? []) as $unitId) {
            $disposisi->tujuan()->create(['unit_id' => $unitId]);
        }

        return $disposisi;
    }

    /** Perubahan oleh Administrator/Super TU dikabarkan ke pengguna unit tujuan. */
    private function kabariUnitTujuan(DisposisiTujuan $tujuan, string $status, User $oleh): void
    {
        if (! $oleh->punyaRole('admin', 'super')) {
            return;
        }

        $penerima = $tujuan->penerimaNotifikasi()
            ->reject(fn (User $user) => $user->id === $oleh->id);

        Notification::send($penerima, new StatusTindakLanjut($tujuan, $status, $oleh->name));
    }

    private function kirimNotifikasi(Disposisi $disposisi): void
    {
        $penerima = $disposisi->penerimaNotifikasi();

        if ($penerima->isNotEmpty()) {
            Notification::send($penerima, new DisposisiBaru($disposisi));
        }
    }

    private function muatRelasi(Disposisi $disposisi): void
    {
        $disposisi->load('suratMasuk.instansi', 'tujuan.unit', 'pembuat');
    }

    /** Semua selesai → selesai; ada diproses/selesai → diproses; semua belum → didisposisi. */
    private function hitungStatusSurat(SuratMasuk $surat): string
    {
        $disposisiIds = $surat->disposisi()->pluck('id');

        if ($disposisiIds->isEmpty()) {
            return 'diterima';
        }

        $status = DisposisiTujuan::whereIn('disposisi_id', $disposisiIds)->pluck('status_tindak_lanjut');

        if ($status->isEmpty()) {
            return 'diterima';
        }

        if ($status->every(fn ($s) => $s === 'selesai')) {
            return 'selesai';
        }

        if ($status->contains(fn ($s) => in_array($s, ['diproses', 'selesai'], true))) {
            return 'diproses';
        }

        return 'didisposisi';
    }

    /** Surat baru yang belum dibuatkan register. */
    private function belumDiregister(User $user): array
    {
        if (! $user->bolehLihatSemuaSurat()) {
            return ['masuk' => collect(), 'total' => 0];
        }

        $masuk = SuratMasuk::with('instansi')
            ->where('status', 'diterima')
            ->whereDoesntHave('disposisi');

        return [
            'masuk' => (clone $masuk)->orderBy('tgl_diterima')->limit(10)->get(),
            'total' => $masuk->count(),
        ];
    }

    private function query(Request $request): Builder
    {
        return Disposisi::with('suratMasuk.instansi', 'pembuat', 'tujuan.unit', 'tujuan.penyelesai')
            ->when(! $request->user()->bolehLihatSemuaSurat(), function ($q) use ($request) {
                $q->whereHas('tujuan', fn ($t) => $t->where('unit_id', $request->user()->unit_id));
            })
            ->when($request->query('cari'), function ($q, $cari) {
                $q->where(fn ($w) => $w->where('instruksi', 'like', "%{$cari}%")
                    ->orWhereHas('suratMasuk', fn ($s) => $s->where('no_agenda', 'like', "%{$cari}%")
                        ->orWhere('perihal', 'like', "%{$cari}%")));
            })
            ->when($request->query('unit'), fn ($q, $unit) => $q->whereHas('tujuan', fn ($t) => $t->where('unit_id', $unit)))
            ->when($request->query('status'), function ($q, $status) {
                $q->whereHas('tujuan', fn ($t) => $t->where('status_tindak_lanjut', $status));
            })
            ->latest('tgl_disposisi');
    }
}
