<?php

namespace Tests\Feature;

use App\Models\Disposisi;
use App\Models\DisposisiTujuan;
use App\Models\SuratMasuk;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\BuatData;
use Tests\TestCase;

/**
 * Berkas surat berada di disk privat dan hanya boleh diunduh lewat rute
 * ber-otorisasi oleh Administrator dan Super TU.
 */
class OtorisasiUnduhanBerkasTest extends TestCase
{
    use BuatData, RefreshDatabase;

    private User $super;

    private User $admin;

    private SuratMasuk $suratMasuk;

    private Disposisi $disposisi;

    private DisposisiTujuan $tujuan;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');

        $unitTujuan = $this->unit('UMPEG');

        $this->super = User::factory()->super()->create();
        $this->admin = User::factory()->admin()->create();

        $this->suratMasuk = $this->suratMasuk($this->super);
        Storage::disk('local')->put('lampiran/uji.pdf', 'isi lampiran');
        $this->suratMasuk->lampiran()->create([
            'nama_file' => 'uji.pdf',
            'path' => 'lampiran/uji.pdf',
            'jenis' => 'scan',
            'diunggah_oleh' => $this->super->id,
        ]);

        $this->disposisi = $this->disposisi($this->suratMasuk, $this->super, [$unitTujuan]);
        $this->tujuan = $this->disposisi->tujuan()->first();

        Storage::disk('local')->put('disposisi/bukti/uji.jpg', 'isi bukti');
        $this->tujuan->update([
            'bukti_path' => 'disposisi/bukti/uji.jpg',
            'bukti_nama' => 'uji.jpg',
        ]);

        Storage::disk('local')->put('kartu-disposisi/kartu.pdf', '%PDF-1.4 uji');
        $this->disposisi->update([
            'kartu_path' => 'kartu-disposisi/kartu.pdf',
            'kartu_nama' => 'kartu.pdf',
        ]);
    }

    public function test_admin_dan_super_bisa_mengunduh_berkas_surat(): void
    {
        foreach ([$this->super, $this->admin] as $pengguna) {
            $this->actingAs($pengguna);

            foreach ($this->tautanBerkas() as $nama => $tautan) {
                $this->get($tautan)->assertOk();
            }
        }
    }

    public function test_tamu_diarahkan_ke_halaman_masuk(): void
    {
        foreach ($this->tautanBerkas() as $nama => $tautan) {
            $this->get($tautan)->assertRedirect(route('login'));
        }
    }

    /** @return array<string, string> */
    private function tautanBerkas(): array
    {
        return [
            'lampiran surat masuk' => route('lampiran.unduh', $this->suratMasuk->lampiran()->first()),
            'bukti tindak lanjut' => route('tujuan.bukti.unduh', $this->tujuan),
            'pdf kartu register' => route('register.pdf', $this->disposisi),
        ];
    }
}
