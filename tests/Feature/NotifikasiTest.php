<?php

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\DisposisiBaru;
use App\Notifications\PengingatBatasWaktu;
use App\Notifications\StatusTindakLanjut;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\SendQueuedNotifications;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Tests\Concerns\BuatData;
use Tests\TestCase;

class NotifikasiTest extends TestCase
{
    use RefreshDatabase, BuatData;

    public function test_ketiga_notifikasi_di_antrekan(): void
    {
        $kelas = [DisposisiBaru::class, StatusTindakLanjut::class, PengingatBatasWaktu::class];

        foreach ($kelas as $notifikasi) {
            $this->assertContains(ShouldQueue::class, class_implements($notifikasi), "$notifikasi belum masuk antrean.");
        }
    }

    public function test_notifikasi_dari_request_masuk_antrean(): void
    {
        Queue::fake();

        $super = User::factory()->super()->create();
        $unit = $this->unit('UMPEG');
        User::factory()->super()->unit($unit)->create();

        $surat = $this->suratMasuk($super);
        $tujuan = $this->disposisi($surat, $super, [$unit])->tujuan()->first();

        $this->actingAs($super)->patch(route('tujuan.status', $tujuan), ['status' => 'selesai'])
            ->assertSessionHasNoErrors();

        Queue::assertPushed(
            SendQueuedNotifications::class,
            fn (SendQueuedNotifications $job) => $job->notification instanceof StatusTindakLanjut,
        );
    }

    public function test_notifikasi_tersimpan_di_database_dan_tampil_di_halaman(): void
    {
        $super = User::factory()->super()->create();
        $admin = User::factory()->admin()->create();
        $unit = $this->unit('UMPEG');
        $penerima = User::factory()->super()->unit($unit)->create();

        $surat = $this->suratMasuk($super);
        $disposisi = $this->disposisi($surat, $admin, [$unit]);

        $penerima->notify(new DisposisiBaru($disposisi));

        $this->assertSame(1, $penerima->notifications()->count());

        $this->actingAs($penerima)->get(route('notifikasi.index'))
            ->assertOk()
            ->assertSee($surat->no_agenda);

        $notifikasi = $penerima->notifications()->first();
        $this->actingAs($penerima)->get(route('notifikasi.baca', $notifikasi->id))->assertRedirect();
        $this->assertNotNull($notifikasi->fresh()->read_at);
    }

    public function test_perubahan_status_dikabarkan_ke_unit_tujuan_tanpa_pelaku(): void
    {
        Notification::fake();

        $unit = $this->unit('UMPEG');
        $pelaku = User::factory()->super()->unit($unit)->create();
        $rekan = User::factory()->super()->unit($unit)->create();

        $surat = $this->suratMasuk($pelaku);
        $tujuan = $this->disposisi($surat, $pelaku, [$unit])->tujuan()->first();

        $this->actingAs($pelaku)->patch(route('tujuan.status', $tujuan), ['status' => 'selesai'])
            ->assertSessionHasNoErrors();

        Notification::assertSentTo($rekan, StatusTindakLanjut::class);
        Notification::assertNotSentTo($pelaku, StatusTindakLanjut::class);
    }

    public function test_register_surat_rahasia_dikabarkan_ke_unit_tujuan(): void
    {
        Notification::fake();

        $super = User::factory()->super()->unit(null)->create();
        $unit = $this->unit('UMPEG');
        $pegawai = User::factory()->super()->unit($unit)->create();
        $operatorUnit = User::factory()->super()->unit($unit)->create();
        $luar = User::factory()->super()->unit($this->unit('ULA'))->create();

        $surat = $this->suratMasuk($super, ['sifat' => 'rahasia']);

        $this->actingAs($super)->post(route('register.store', $surat), [
            'indeks' => '400.7/R',
            'tgl_penyelesaian' => now()->addDays(3)->toDateString(),
            'instruksi' => 'Tindak lanjuti secara terbatas.',
            'tujuan' => [$unit->id],
        ])->assertSessionHasNoErrors();

        Notification::assertSentTo($pegawai, DisposisiBaru::class);
        Notification::assertSentTo($operatorUnit, DisposisiBaru::class);
        Notification::assertNotSentTo($luar, DisposisiBaru::class);
    }

    public function test_status_tindak_lanjut_surat_rahasia_dikabarkan_ke_unit_tujuan(): void
    {
        Notification::fake();

        $super = User::factory()->super()->unit(null)->create();
        $unit = $this->unit('UMPEG');
        $pegawai = User::factory()->super()->unit($unit)->create();
        $luar = User::factory()->super()->unit($this->unit('ULA'))->create();

        $surat = $this->suratMasuk($super, ['sifat' => 'rahasia']);
        $tujuan = $this->disposisi($surat, $super, [$unit])->tujuan()->first();

        $this->actingAs($super)->patch(route('tujuan.status', $tujuan), ['status' => 'diproses'])
            ->assertSessionHasNoErrors();

        Notification::assertSentTo($pegawai, StatusTindakLanjut::class);
        Notification::assertNotSentTo($luar, StatusTindakLanjut::class);
    }

    public function test_command_pengingat_mengirim_notifikasi_batas_waktu(): void
    {
        Notification::fake();

        $super = User::factory()->super()->create();
        $unit = $this->unit('UMPEG');
        $penerima = User::factory()->super()->unit($unit)->create();

        $surat = $this->suratMasuk($super);
        $this->disposisi($surat, $super, [$unit], ['tgl_penyelesaian' => now()->addDay()]);

        $this->artisan('disposisi:ingatkan')->assertSuccessful();

        Notification::assertSentTo($penerima, PengingatBatasWaktu::class);
    }

    public function test_command_tidak_mengirim_untuk_batas_yang_masih_lama(): void
    {
        Notification::fake();

        $super = User::factory()->super()->create();
        $unit = $this->unit('UMPEG');
        $penerima = User::factory()->super()->unit($unit)->create();

        $surat = $this->suratMasuk($super);
        $this->disposisi($surat, $super, [$unit], ['tgl_penyelesaian' => now()->addDays(20)]);

        $this->artisan('disposisi:ingatkan')->assertSuccessful();

        Notification::assertNotSentTo($penerima, PengingatBatasWaktu::class);
    }
}
