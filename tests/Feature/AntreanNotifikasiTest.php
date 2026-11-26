<?php

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\DisposisiBaru;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\BuatData;
use Tests\TestCase;

/**
 * Memakai DatabaseMigrations agar tidak ada transaksi pembungkus RefreshDatabase:
 * callback after-commit hanya berjalan pada commit transaksi terluar.
 */
class AntreanNotifikasiTest extends TestCase
{
    use BuatData, DatabaseMigrations;

    public function test_notifikasi_baru_masuk_antrean_setelah_transaksi_commit(): void
    {
        config(['queue.default' => 'database']);

        $super = User::factory()->super()->create();
        $admin = User::factory()->admin()->create();
        $unit = $this->unit('UMPEG');
        $penerima = User::factory()->super()->unit($unit)->create();

        $surat = $this->suratMasuk($super);
        $disposisi = $this->disposisi($surat, $admin, [$unit]);

        DB::transaction(function () use ($penerima, $disposisi) {
            $penerima->notify(new DisposisiBaru($disposisi));

            $this->assertSame(0, DB::table('jobs')->count(), 'Job sudah masuk antrean sebelum transaksi commit.');
            $this->assertSame(0, $penerima->notifications()->count(), 'Notifikasi database terkirim sebelum transaksi commit.');
        });

        // Satu job per kanal: 'database' dan 'mail'.
        $this->assertSame(2, DB::table('jobs')->count());
    }
}
