<?php

namespace Tests\Feature;

use App\Models\Disposisi;
use App\Models\SuratMasuk;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\BuatData;
use Tests\TestCase;

class PeranSuperTest extends TestCase
{
    use BuatData, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        Notification::fake();
    }

    public function test_super_mencatat_surat_masuk_lalu_diarahkan_ke_register(): void
    {
        $super = User::factory()->super()->create();
        $instansi = $this->instansi();
        $klasifikasi = $this->klasifikasi();

        $this->actingAs($super)->get(route('surat-masuk.create'))->assertOk();

        $this->actingAs($super)->post(route('surat-masuk.store'), [
            'instansi_id' => $instansi->id,
            'no_surat' => '400.7/100-SUPER',
            'tgl_surat' => now()->subDay()->toDateString(),
            'tgl_diterima' => now()->toDateString(),
            'klasifikasi_id' => $klasifikasi->id,
            'perihal' => 'Undangan rapat koordinasi',
            'sifat' => 'biasa',
        ])->assertSessionHasNoErrors();

        $masuk = SuratMasuk::where('no_surat', '400.7/100-SUPER')->firstOrFail();

        $this->assertSame($super->id, $masuk->dicatat_oleh);
        $this->assertSame('diterima', $masuk->status);
    }

    public function test_super_meregister_tanpa_instruksi_dan_tujuan(): void
    {
        $super = User::factory()->super()->create();
        $surat = $this->suratMasuk($super);

        $this->actingAs($super)->get(route('register.form', $surat))->assertOk();

        $this->actingAs($super)->post(route('register.store', $surat), [
            'indeks' => '400.7',
        ])->assertSessionHasNoErrors();

        $disposisi = Disposisi::where('surat_masuk_id', $surat->id)->firstOrFail();

        $this->assertSame($super->id, $disposisi->dibuat_oleh);
        $this->assertNull($disposisi->instruksi);
        $this->assertSame(0, $disposisi->tujuan()->count());
        $this->assertSame('diterima', $surat->fresh()->status);
    }

    public function test_super_dapat_membuka_laporan_dan_ekspornya(): void
    {
        $super = User::factory()->super()->create();
        $this->suratMasuk($super, ['perihal' => 'Surat untuk laporan super']);

        $this->actingAs($super)->get(route('laporan.index'))
            ->assertOk()
            ->assertSee('Surat untuk laporan super');

        $this->actingAs($super)->get(route('laporan.tindak-lanjut'))->assertOk();
        $this->actingAs($super)->get(route('laporan.ekspor'))->assertOk();
    }

    public function test_super_tidak_dapat_membuka_menu_administrasi(): void
    {
        $super = User::factory()->super()->create();

        foreach ([
            route('pengguna.index'),
            route('unit-kerja.index'),
            route('klasifikasi.index'),
            route('instansi.index'),
            route('log.index'),
            route('pengaturan.edit'),
        ] as $url) {
            $this->actingAs($super)->get($url)->assertForbidden();
        }
    }

    public function test_dashboard_super_tidak_menampilkan_surat_yang_sama_dua_kali(): void
    {
        $super = User::factory()->super()->create();
        $surat = $this->suratMasuk($super, ['perihal' => 'Undangan Rapat Koordinasi']);

        $html = $this->actingAs($super)->get(route('dashboard'))
            ->assertOk()
            ->getContent();

        $this->assertSame(1, substr_count($html, $surat->no_agenda));
        $this->assertSame(1, substr_count($html, 'Undangan Rapat Koordinasi'));
    }
}
