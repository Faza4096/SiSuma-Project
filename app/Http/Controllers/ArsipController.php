<?php

namespace App\Http\Controllers;

use App\Models\Instansi;
use App\Models\Klasifikasi;
use App\Models\SuratMasuk;
use App\Services\PdfService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ArsipController extends Controller
{
    /** Daftar surat masuk yang sudah diarsipkan. */
    public function index(Request $request)
    {
        return view('arsip.index', [
            'surat' => $this->susun($request)->paginate(15)->withQueryString(),
            'instansi' => Instansi::orderBy('nama')->get(),
            'klasifikasi' => Klasifikasi::orderBy('kode')->get(),
        ]);
    }

    /** Ekspor CSV (UTF-8, pemisah titik koma) sesuai filter aktif. */
    public function ekspor(Request $request): StreamedResponse
    {
        $data = $this->susun($request)->get();
        $nama = 'arsip-surat-masuk-' . now()->format('Ymd-His') . '.csv';

        return response()->streamDownload(function () use ($data) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");

            $tulis = fn (array $baris) => fputcsv($out, array_map($this->selCsv(...), $baris), ';');

            $tulis(['No. Agenda', 'No. Surat', 'Tanggal Surat', 'Tanggal Diterima', 'Pengirim', 'Klasifikasi', 'Perihal', 'Sifat', 'Lokasi Arsip', 'Diarsipkan Pada', 'Petugas Arsip']);
            foreach ($data as $s) {
                $tulis([
                    $s->no_agenda, $s->no_surat,
                    $s->tgl_surat?->format('d/m/Y'), $s->tgl_diterima?->format('d/m/Y'),
                    $s->instansi?->nama, $s->klasifikasi?->kode, $s->perihal,
                    SuratMasuk::STATUS[$s->status] ?? $s->status,
                    $s->lokasi_arsip, $s->diarsipkan_pada?->format('d/m/Y H:i'), $s->pengarsip?->name,
                ]);
            }

            fclose($out);
        }, $nama, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /** Ekspor PDF (dompdf bila tersedia, selain itu halaman siap cetak). */
    public function pdf(Request $request)
    {
        $data = $this->susun($request)->get();

        $html = view('arsip.pdf', [
            'data' => $data,
            'filter' => [
                'cari' => $request->query('cari'),
                'instansi' => Instansi::find($request->query('instansi_id'))?->nama,
                'klasifikasi' => Klasifikasi::find($request->query('klasifikasi_id'))?->kode,
            ],
            'pdf' => PdfService::tersedia(),
        ])->render();

        if (PdfService::tersedia()) {
            return PdfService::unduh($html, 'arsip-surat-masuk-' . now()->format('Ymd-His') . '.pdf');
        }

        return response($html)->header('Content-Type', 'text/html; charset=UTF-8');
    }

    private function susun(Request $request): Builder
    {
        $cari = trim((string) $request->query('cari'));

        $query = SuratMasuk::with('instansi', 'klasifikasi', 'pengarsip')
            ->terlihatOleh($request->user())
            ->where('status', 'diarsipkan')
            ->latest('diarsipkan_pada');

        if ($cari !== '') {
            $query->where(fn ($w) => $w->where('perihal', 'like', "%{$cari}%")
                ->orWhere('no_surat', 'like', "%{$cari}%")
                ->orWhere('no_agenda', 'like', "%{$cari}%")
                ->orWhere('lokasi_arsip', 'like', "%{$cari}%"));
        }

        $query->when($request->query('instansi_id'), fn ($q, $v) => $q->where('instansi_id', $v))
            ->when($request->query('klasifikasi_id'), fn ($q, $v) => $q->where('klasifikasi_id', $v));

        return $query;
    }

    /**
     * Cegah CSV/formula injection: sel yang diawali pemicu formula Excel/Calc
     * diberi awalan kutip tunggal agar dibaca sebagai teks biasa.
     */
    private function selCsv(mixed $nilai): string
    {
        $teks = (string) ($nilai ?? '');

        return $teks !== '' && str_contains("=+-@\t\r", $teks[0])
            ? "'" . $teks
            : $teks;
    }
}
