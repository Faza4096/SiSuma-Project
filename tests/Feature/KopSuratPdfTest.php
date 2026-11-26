<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuatData;
use Tests\TestCase;

/** Kop resmi tidak boleh hilang dari PDF laporan maupun arsip. */
class KopSuratPdfTest extends TestCase
{
    use BuatData, RefreshDatabase;

    public function test_pdf_laporan_memuat_kop_resmi(): void
    {
        $admin = User::factory()->admin()->create();
        $this->suratMasuk($admin);

        $pdf = $this->actingAs($admin)
            ->get(route('laporan.pdf'))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf')
            ->getContent();

        $this->assertKopResmi($pdf, 'LAPORAN SURAT MASUK');
    }

    public function test_pdf_arsip_memuat_kop_resmi(): void
    {
        $admin = User::factory()->admin()->create();
        $this->suratMasuk($admin, ['status' => 'diarsipkan', 'lokasi_arsip' => 'Lemari A-1']);

        $pdf = $this->actingAs($admin)
            ->get(route('arsip.pdf'))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf')
            ->getContent();

        $this->assertKopResmi($pdf, 'LAPORAN ARSIP SURAT MASUK');
    }

    private function assertKopResmi(string $pdf, string $judul): void
    {
        $this->assertStringStartsWith('%PDF', $pdf);

        $teks = $this->teksPdf($pdf);

        // Identitas dari Pengaturan Instansi, bukan teks bebas di template.
        $this->assertStringContainsString('PEMERINTAH KABUPATEN BOGOR', $teks);
        $this->assertStringContainsString('KECAMATAN CIOMAS', $teks);

        // Judul dan jabatan penanda tangan.
        $this->assertStringContainsString($judul, $teks);
        $this->assertStringContainsString('Pengelola Tata Naskah', $teks);

        // Logo benar-benar ditanam sebagai gambar, bukan hanya tautan.
        $this->assertMatchesRegularExpression('#/Subtype\s*/Image#', $pdf);

        // A4 landscape.
        $this->assertMatchesRegularExpression(
            '#/MediaBox\s*\[\s*0(?:\.0+)?\s+0(?:\.0+)?\s+841\.\d+\s+595\.\d+\s*\]#',
            $pdf
        );
    }

    /** Isi stream PDF yang sudah didekompres (operator gambar teks dompdf). */
    private function teksPdf(string $pdf): string
    {
        preg_match_all('/stream\r?\n(.*?)endstream/s', $pdf, $cocok);

        $teks = '';

        foreach ($cocok[1] as $blob) {
            $isi = @gzuncompress($blob);

            if ($isi !== false) {
                $teks .= $isi . "\n";
            }
        }

        return $teks;
    }
}
