<?php

namespace Tests\Feature;

use App\Models\Disposisi;
use App\Models\DisposisiTujuan;
use App\Models\Lampiran;
use App\Models\SuratMasuk;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\BuatData;
use Tests\TestCase;

class HapusPermanenTest extends TestCase
{
    use RefreshDatabase, BuatData;

    public function test_hapus_permanen_surat_masuk_membersihkan_lampiran(): void
    {
        Storage::fake('local');

        $admin = User::factory()->admin()->create();
        $super = User::factory()->super()->create();
        $surat = $this->suratMasuk($super);

        Storage::disk('local')->put('lampiran/masuk/scan.pdf', 'isi');
        $lampiran = $surat->lampiran()->create([
            'nama_file' => 'scan.pdf',
            'path' => 'lampiran/masuk/scan.pdf',
            'jenis' => 'scan',
            'diunggah_oleh' => $super->id,
        ]);

        $this->actingAs($admin)
            ->get(route('surat-masuk.show', $surat))
            ->assertSee('Hapus Permanen Surat Masuk')
            ->assertDontSee('Zona berbahaya');

        $konfirmasi = $this->actingAs($admin)
            ->get(route('surat-masuk.hapus-permanen.form', $surat))
            ->assertOk()
            ->assertSee($surat->no_agenda)
            ->assertSee('Tindakan ini membuang surat beserta 0 register, lampiran, kartu PDF, dan bukti tindak lanjut.');

        // Peringatan tidak boleh mengulang subjudul "Tindakan ini tidak dapat dibatalkan.".
        $this->assertSame(1, substr_count($konfirmasi->getContent(), 'dapat dibatalkan'));

        // Tanpa nomor yang diketik persis, surat tidak boleh terhapus.
        $this->actingAs($admin)
            ->delete(route('surat-masuk.hapus-permanen', $surat), ['konfirmasi' => 'AGD/salah'])
            ->assertSessionHasErrors('konfirmasi');
        $this->actingAs($admin)
            ->delete(route('surat-masuk.hapus-permanen', $surat))
            ->assertSessionHasErrors('konfirmasi');
        $this->actingAs($admin)
            ->delete(route('surat-masuk.hapus-permanen', $surat), ['konfirmasi' => [$surat->no_agenda]])
            ->assertSessionHasErrors('konfirmasi');
        $this->assertNotNull(SuratMasuk::withTrashed()->find($surat->id));

        $this->actingAs($admin)
            ->delete(route('surat-masuk.hapus-permanen', $surat), ['konfirmasi' => $surat->no_agenda])
            ->assertRedirect(route('surat-masuk.index'));

        $this->assertNull(SuratMasuk::withTrashed()->find($surat->id));
        $this->assertNull(Lampiran::find($lampiran->id));
        Storage::disk('local')->assertMissing('lampiran/masuk/scan.pdf');
    }

    public function test_surat_masuk_yang_sudah_diregister_dihapus_beserta_registernya(): void
    {
        $admin = User::factory()->admin()->create();
        $surat = $this->suratMasuk($admin);
        $disposisi = $this->disposisi($surat, $admin, [$this->unit('UMPEG')]);

        $this->actingAs($admin)
            ->get(route('surat-masuk.hapus-permanen.form', $surat))
            ->assertOk()
            ->assertSee('Register yang ikut terhapus')
            ->assertSee('1 register, lampiran, kartu PDF, dan bukti tindak lanjut');

        $this->actingAs($admin)
            ->delete(route('surat-masuk.hapus-permanen', $surat), ['konfirmasi' => $surat->no_agenda])
            ->assertRedirect(route('surat-masuk.index'));

        $this->assertNull(SuratMasuk::withTrashed()->find($surat->id));
        $this->assertSame(0, Disposisi::withTrashed()->where('surat_masuk_id', $surat->id)->count());
        $this->assertSame(0, DisposisiTujuan::where('disposisi_id', $disposisi->id)->count());
    }

    public function test_super_boleh_menghapus_permanen_surat_masuk(): void
    {
        $super = User::factory()->super()->create();
        $surat = $this->suratMasuk($super);

        $this->actingAs($super)
            ->get(route('surat-masuk.show', $surat))
            ->assertSee('Hapus Permanen Surat Masuk');

        $this->actingAs($super)
            ->get(route('surat-masuk.hapus-permanen.form', $surat))
            ->assertOk()
            ->assertSee($surat->no_agenda);

        $this->actingAs($super)
            ->delete(route('surat-masuk.hapus-permanen', $surat), ['konfirmasi' => $surat->no_agenda])
            ->assertRedirect(route('surat-masuk.index'));

        $this->assertNull(SuratMasuk::withTrashed()->find($surat->id));
    }

    public function test_register_yang_sudah_dibatalkan_ikut_terhapus(): void
    {
        $admin = User::factory()->admin()->create();
        $surat = $this->suratMasuk($admin);
        $disposisi = $this->disposisi($surat, $admin, [$this->unit('UMPEG')]);

        // Batal register mengembalikan surat ke status diterima, sehingga boleh dihapus permanen.
        $this->actingAs($admin)->delete(route('register.destroy', $disposisi));
        $this->assertSame('diterima', $surat->fresh()->status);

        $this->actingAs($admin)
            ->delete(route('surat-masuk.hapus-permanen', $surat), ['konfirmasi' => $surat->no_agenda]);

        $this->assertSame(0, Disposisi::withTrashed()->where('surat_masuk_id', $surat->id)->count());
        $this->assertSame(0, DisposisiTujuan::where('disposisi_id', $disposisi->id)->count());
    }
}
