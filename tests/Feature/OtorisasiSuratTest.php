<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuatData;
use Tests\TestCase;

class OtorisasiSuratTest extends TestCase
{
    use RefreshDatabase, BuatData;

    public function test_admin_dan_super_membuka_semua_surat_masuk(): void
    {
        $pencatat = User::factory()->super()->create();
        $surat = $this->suratMasuk($pencatat, ['sifat' => 'rahasia']);

        $pengguna = [
            User::factory()->super()->create(),
            User::factory()->admin()->create(),
        ];

        foreach ($pengguna as $user) {
            $this->actingAs($user)->get(route('surat-masuk.show', $surat))->assertOk();
            $this->actingAs($user)->get(route('surat-masuk.index'))->assertSee($surat->perihal);
        }
    }

    public function test_admin_dan_super_dapat_mencatat_dan_mengubah_surat(): void
    {
        $pencatat = User::factory()->super()->create();
        $surat = $this->suratMasuk($pencatat);

        foreach ([User::factory()->super()->create(), User::factory()->admin()->create()] as $user) {
            $this->actingAs($user)->get(route('surat-masuk.create'))->assertOk();

            $this->actingAs($user)
                ->put(route('surat-masuk.update', $surat), [
                    'instansi_id' => $surat->instansi_id,
                    'no_surat' => '400.7/99-TU',
                    'tgl_surat' => now()->toDateString(),
                    'tgl_diterima' => now()->toDateString(),
                    'perihal' => 'Perubahan sah',
                    'sifat' => 'biasa',
                ])
                ->assertSessionHasNoErrors();
        }

        $this->assertSame('Perubahan sah', $surat->fresh()->perihal);
    }

    public function test_peran_lama_tidak_dapat_mengelola_surat(): void
    {
        $lama = User::factory()->role('staf')->create();

        // Akun berperan lama langsung diputus sesinya oleh middleware AkunAktif.
        $this->actingAs($lama)->get(route('surat-masuk.create'))->assertRedirect(route('login'));

        $surat = $this->suratMasuk(User::factory()->super()->create());
        $this->actingAs($lama)->get(route('surat-masuk.show', $surat))->assertRedirect(route('login'));
    }

    public function test_tamu_diarahkan_ke_halaman_masuk(): void
    {
        $surat = $this->suratMasuk(User::factory()->super()->create());

        $this->get(route('surat-masuk.show', $surat))->assertRedirect(route('login'));
        $this->get(route('register.index'))->assertRedirect(route('login'));
    }
}
