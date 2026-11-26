<?php

namespace Tests\Feature;

use App\Models\DisposisiTujuan;
use App\Models\User;
use App\Notifications\DisposisiBaru;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\BuatData;
use Tests\TestCase;

class AlurDisposisiTest extends TestCase
{
    use RefreshDatabase, BuatData;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    public function test_super_meregister_dan_unit_tujuan_menerima_notifikasi(): void
    {
        Notification::fake();

        $super = User::factory()->super()->create();
        $unit = $this->unit('UMPEG');
        $penerima = User::factory()->super()->unit($unit)->create();
        $surat = $this->suratMasuk($super);

        $this->actingAs($super)->post(route('register.store', $surat), [
            'indeks' => '400.7',
            'instruksi' => 'Koordinasikan dengan Puskesmas.',
            'tgl_penyelesaian' => now()->addDays(2)->toDateString(),
            'tujuan' => [$unit->id],
        ])->assertSessionHasNoErrors();

        $this->assertSame('didisposisi', $surat->fresh()->status);
        $this->assertDatabaseHas('disposisi_tujuan', ['unit_id' => $unit->id, 'status_tindak_lanjut' => 'belum']);
        Notification::assertSentTo($penerima, DisposisiBaru::class);
    }

    public function test_register_tanpa_tujuan_menunggu_perintah_pimpinan(): void
    {
        Notification::fake();

        $super = User::factory()->super()->create();
        $surat = $this->suratMasuk($super);

        $this->actingAs($super)->post(route('register.store', $surat), [
            'indeks' => '400.7',
        ])->assertSessionHasNoErrors();

        $this->assertSame('diterima', $surat->fresh()->status);
        $this->assertSame(0, $surat->disposisi()->first()->tujuan()->count());
        Notification::assertNothingSent();
    }

    public function test_status_surat_mengikuti_tindak_lanjut_unit(): void
    {
        $super = User::factory()->super()->create();
        $unit = $this->unit('UMPEG');
        $surat = $this->suratMasuk($super);
        $tujuan = $this->disposisi($surat, $super, [$unit])->tujuan()->first();

        $this->actingAs($super)->patch(route('tujuan.status', $tujuan), ['status' => 'diproses'])
            ->assertSessionHasNoErrors();
        $this->assertSame('diproses', $surat->fresh()->status);

        $this->actingAs($super)->patch(route('tujuan.status', $tujuan), ['status' => 'belum'])
            ->assertSessionHasNoErrors();
        $this->assertSame('didisposisi', $surat->fresh()->status);

        $this->actingAs($super)->patch(route('tujuan.status', $tujuan), ['status' => 'selesai'])
            ->assertSessionHasNoErrors();
        $this->assertSame('selesai', $surat->fresh()->status);
        $this->assertSame($super->id, $tujuan->fresh()->selesai_oleh);
    }

    public function test_admin_boleh_mengubah_status_unit_lain(): void
    {
        $admin = User::factory()->admin()->create();
        $unit = $this->unit('UMPEG');

        $surat = $this->suratMasuk($admin);
        $tujuan = $this->disposisi($surat, $admin, [$unit])->tujuan()->first();

        $this->actingAs($admin)->patch(route('tujuan.status', $tujuan), ['status' => 'selesai'])
            ->assertSessionHasNoErrors();
        $this->assertSame('selesai', $tujuan->fresh()->status_tindak_lanjut);
    }

    public function test_unit_tujuan_mengunggah_bukti_tindak_lanjut(): void
    {
        $super = User::factory()->super()->create();
        $unit = $this->unit('UMPEG');
        $pelaksana = User::factory()->super()->unit($unit)->create();

        $surat = $this->suratMasuk($super);
        $tujuan = $this->disposisi($surat, $super, [$unit])->tujuan()->first();

        $this->actingAs($pelaksana)->post(route('tujuan.bukti', $tujuan), [
            'bukti' => UploadedFile::fake()->create('bukti.pdf', 120, 'application/pdf'),
            'catatan' => 'Sudah dikoordinasikan dengan Puskesmas.',
        ])->assertSessionHasNoErrors();

        $tujuan->refresh();
        $this->assertNotNull($tujuan->bukti_path);
        $this->assertSame('bukti.pdf', $tujuan->bukti_nama);
        $this->assertSame('diproses', $tujuan->status_tindak_lanjut);
        $this->assertSame('diproses', $surat->fresh()->status);
        Storage::disk('local')->assertExists($tujuan->bukti_path);
    }

    public function test_surat_yang_sudah_diregister_tidak_dapat_dihapus(): void
    {
        $super = User::factory()->super()->create();
        $surat = $this->suratMasuk($super);
        $this->disposisi($surat, $super, [$this->unit('UMPEG')]);

        $this->actingAs($super)->delete(route('surat-masuk.destroy', $surat))->assertForbidden();
        $this->assertNotSoftDeleted($surat);
    }

    public function test_register_dapat_dibatalkan_dan_surat_kembali_diterima(): void
    {
        $super = User::factory()->super()->create();
        $surat = $this->suratMasuk($super);
        $disposisi = $this->disposisi($surat, $super, [$this->unit('UMPEG')]);

        $this->actingAs($super)->delete(route('register.destroy', $disposisi))
            ->assertRedirect();

        $this->assertSoftDeleted('disposisi', ['id' => $disposisi->id]);
        $this->assertSame('diterima', $surat->fresh()->status);
    }

    public function test_super_mengarsipkan_surat_yang_sudah_selesai(): void
    {
        $super = User::factory()->super()->create();
        $surat = $this->suratMasuk($super, ['status' => 'selesai']);

        $this->actingAs($super)->patch(route('surat-masuk.arsipkan', $surat), [
            'lokasi_arsip' => 'Lemari B-2, Rak 3',
        ])->assertSessionHasNoErrors();

        $surat->refresh();
        $this->assertSame('diarsipkan', $surat->status);
        $this->assertSame('Lemari B-2, Rak 3', $surat->lokasi_arsip);
        $this->assertSame($super->id, $surat->diarsipkan_oleh);
    }

    public function test_surat_yang_belum_selesai_tidak_dapat_diarsipkan(): void
    {
        $super = User::factory()->super()->create();
        $surat = $this->suratMasuk($super, ['status' => 'diproses']);

        $this->actingAs($super)->patch(route('surat-masuk.arsipkan', $surat), [
            'lokasi_arsip' => 'Lemari A',
        ])->assertForbidden();
    }
}
