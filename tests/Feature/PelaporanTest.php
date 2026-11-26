<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuatData;
use Tests\TestCase;

class PelaporanTest extends TestCase
{
    use RefreshDatabase, BuatData;

    public function test_laporan_surat_dapat_difilter(): void
    {
        $super = User::factory()->super()->create();
        $surat = $this->suratMasuk($super);
        $lain = $this->suratMasuk($super, ['instansi_id' => $this->instansi('Dinas Kesehatan Kab. Bogor')->id]);

        $this->actingAs($super)
            ->get(route('laporan.index', ['jenis' => 'masuk', 'instansi_id' => $surat->instansi_id]))
            ->assertOk()
            ->assertSee($surat->perihal)
            ->assertDontSee($lain->perihal);
    }

    public function test_ekspor_excel_menghasilkan_csv(): void
    {
        $super = User::factory()->super()->create();
        $surat = $this->suratMasuk($super);

        $response = $this->actingAs($super)->get(route('laporan.ekspor', ['jenis' => 'masuk']));

        $response->assertOk();
        $this->assertStringContainsString('text/csv', $response->headers->get('content-type'));

        $isi = $response->streamedContent();
        $this->assertStringContainsString($surat->no_agenda, $isi);
        $this->assertStringContainsString('No. Agenda', $isi);
    }

    public function test_ekspor_pdf_menghasilkan_berkas_pdf(): void
    {
        $super = User::factory()->super()->create();
        $this->suratMasuk($super);

        $response = $this->actingAs($super)->get(route('laporan.pdf', ['jenis' => 'masuk']));

        $response->assertOk();
        $this->assertSame('application/pdf', $response->headers->get('content-type'));
        $this->assertStringStartsWith('%PDF', $response->getContent());
    }

    public function test_laporan_tindak_lanjut_menampilkan_status_unit(): void
    {
        $super = User::factory()->super()->create();
        $admin = User::factory()->admin()->create();
        $unit = $this->unit('UMPEG');
        $surat = $this->suratMasuk($super);
        $this->disposisi($surat, $admin, [$unit]);

        $this->actingAs($super)
            ->get(route('laporan.tindak-lanjut'))
            ->assertOk()
            ->assertSee($surat->no_agenda)
            ->assertSee($unit->nama);
    }

    public function test_laporan_tindak_lanjut_menampilkan_seluruh_unit(): void
    {
        $super = User::factory()->super()->create();
        $unitSendiri = $this->unit('UMPEG');
        $unitLain = $this->unit('ULA');

        $milikSendiri = $this->suratMasuk($super, ['perihal' => 'Perihal unit sendiri']);
        $milikLain = $this->suratMasuk($super, ['perihal' => 'Perihal unit lain']);

        $this->disposisi($milikSendiri, $super, [$unitSendiri]);
        $this->disposisi($milikLain, $super, [$unitLain]);

        $this->actingAs($super)
            ->get(route('laporan.tindak-lanjut'))
            ->assertOk()
            ->assertSee('Perihal unit sendiri')
            ->assertSee('Perihal unit lain')
            ->assertSee($unitLain->nama);
    }
}
