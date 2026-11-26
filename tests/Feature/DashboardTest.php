<?php

namespace Tests\Feature;

use App\Models\LogAktivitas;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\BuatData;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase, BuatData;

    public function test_super_melihat_antrean_belum_register_dan_belum_arsip(): void
    {
        $super = User::factory()->super()->create();

        $menunggu = $this->suratMasuk($super);
        $selesai = $this->suratMasuk($super, ['status' => 'selesai']);
        $sudahRegister = $this->suratMasuk($super);
        $this->disposisi($sudahRegister, $super, [$this->unit('UMPEG')]);

        $this->actingAs($super)->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Belum diregister')
            ->assertSee($menunggu->perihal)
            ->assertSee('Belum diarsipkan')
            ->assertSee($selesai->perihal)
            ->assertDontSee('Surat masuk menunggu ACC')
            ->assertDontSee($sudahRegister->perihal);
    }

    public function test_antrean_belum_register_mengurutkan_sifat_segera_lebih_dulu(): void
    {
        $super = User::factory()->super()->create();

        $biasa = $this->suratMasuk($super, ['sifat' => 'biasa']);
        $segera = $this->suratMasuk($super, ['sifat' => 'segera']);

        $html = $this->actingAs($super)->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Belum diregister')
            ->getContent();

        $this->assertLessThan(
            strpos($html, $biasa->perihal),
            strpos($html, $segera->perihal),
            'Surat segera harus diurutkan sebelum surat biasa.'
        );
    }

    public function test_admin_melihat_ringkasan_akun_dan_log_terbaru(): void
    {
        $admin = User::factory()->admin()->create();
        User::factory()->super()->create();

        LogAktivitas::create([
            'pengguna_id' => $admin->id,
            'aksi' => 'pengguna.baru',
            'keterangan' => 'Pengguna uji ditambahkan',
            'waktu' => now(),
        ]);

        $this->actingAs($admin)->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Akun Pengguna')
            ->assertSee('Total akun')
            ->assertSee('Masih memakai sandi sementara')
            ->assertSee('Log Terbaru')
            ->assertSee('Pengguna uji ditambahkan');
    }

    public function test_admin_tidak_melihat_antrean_operasional(): void
    {
        $admin = User::factory()->admin()->create();
        $surat = $this->suratMasuk($admin);

        $this->actingAs($admin)->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Akun Pengguna')
            ->assertDontSee('Belum diregister')
            ->assertDontSee('Belum diarsipkan')
            ->assertDontSee($surat->perihal);
    }

    public function test_super_tidak_melihat_menu_administrasi_dan_panel_admin(): void
    {
        $super = User::factory()->super()->create();
        User::factory()->admin()->create();

        LogAktivitas::create([
            'pengguna_id' => $super->id,
            'aksi' => 'pengguna.baru',
            'keterangan' => 'Pengguna uji ditambahkan',
            'waktu' => now(),
        ]);

        $this->actingAs($super)->get(route('dashboard'))
            ->assertOk()
            ->assertDontSee('Administrasi', false)
            ->assertDontSee('Akun Pengguna')
            ->assertDontSee('Total akun')
            ->assertDontSee('Log Terbaru')
            ->assertDontSee('Kelola pengguna')
            ->assertDontSee('Pengguna uji ditambahkan')
            ->assertDontSee(route('pengguna.index'))
            ->assertDontSee(route('pengaturan.edit'));
    }

    public function test_dashboard_tidak_menjalankan_query_berlebihan(): void
    {
        $super = User::factory()->super()->create();
        $unit = $this->unit('UMPEG');

        foreach (range(1, 5) as $i) {
            $surat = $this->suratMasuk($super);
            $this->disposisi($surat, $super, [$unit], ['tgl_penyelesaian' => now()->addDays($i)]);
        }

        DB::enableQueryLog();
        $this->actingAs($super)->get(route('dashboard'))->assertOk();
        $jumlah = count(DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertLessThanOrEqual(25, $jumlah, "Dashboard menjalankan {$jumlah} query.");
    }

    public function test_ringkasan_hanya_menghitung_surat_bulan_berjalan(): void
    {
        $super = User::factory()->super()->create();

        $this->suratMasuk($super, ['tgl_diterima' => now()->toDateString()]);
        $this->suratMasuk($super, ['tgl_diterima' => now()->subMonths(2)->toDateString()]);

        $response = $this->actingAs($super)->get(route('dashboard'))->assertOk();
        $ringkasan = $response->viewData('ringkasan');

        $this->assertSame(1, $ringkasan['masuk']);
        $this->assertStringContainsString('Bulan ini:', $response->getContent());
    }
}
