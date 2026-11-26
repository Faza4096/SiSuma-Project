<?php

namespace Tests\Feature;

use App\Models\Disposisi;
use App\Models\User;
use App\Notifications\DisposisiBaru;
use App\Services\KartuDisposisiService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\BuatData;
use Tests\TestCase;

class AccDisposisiTest extends TestCase
{
    use BuatData, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        Notification::fake();
    }

    public function test_super_meregister_surat_dan_kartu_pdf_dibuat(): void
    {
        $super = User::factory()->super()->create();
        $unit = $this->unit('UMPEG');
        $penerima = User::factory()->super()->unit($unit)->create();
        $surat = $this->suratMasuk($super);

        $this->actingAs($super)
            ->post(route('register.store', $surat), [
                'indeks' => '400.7',
                'tgl_penyelesaian' => now()->addDays(3)->toDateString(),
                'instruksi' => 'Koordinasikan dan laporkan hasilnya.',
                'tujuan' => [$unit->id],
            ])
            ->assertRedirect(route('surat-masuk.show', $surat));

        $this->assertSame('didisposisi', $surat->fresh()->status);

        $disposisi = Disposisi::where('surat_masuk_id', $surat->id)->firstOrFail();
        $this->assertSame('400.7', $disposisi->indeks);
        $this->assertSame($super->id, $disposisi->dibuat_oleh);
        $this->assertCount(1, $disposisi->tujuan);

        $this->assertNotNull($disposisi->kartu_path);
        Storage::disk('local')->assertExists($disposisi->kartu_path);
        $this->assertStringStartsWith('%PDF', Storage::disk('local')->get($disposisi->kartu_path));

        Notification::assertSentTo($penerima, DisposisiBaru::class);
    }

    public function test_register_boleh_tanpa_instruksi_dan_tujuan(): void
    {
        $super = User::factory()->super()->create();
        $surat = $this->suratMasuk($super);

        $this->actingAs($super)
            ->post(route('register.store', $surat), ['indeks' => '400.7'])
            ->assertRedirect(route('surat-masuk.show', $surat));

        $disposisi = Disposisi::where('surat_masuk_id', $surat->id)->firstOrFail();

        $this->assertNull($disposisi->instruksi);
        $this->assertCount(0, $disposisi->tujuan);
        $this->assertSame('diterima', $surat->fresh()->status);
        Notification::assertNothingSent();
    }

    public function test_form_register_terisi_data_surat(): void
    {
        $super = User::factory()->super()->create();
        $surat = $this->suratMasuk($super, ['perihal' => 'Undangan Rapat Koordinasi']);

        $this->actingAs($super)
            ->get(route('register.form', $surat))
            ->assertOk()
            ->assertSee('Register Surat Masuk')
            ->assertSee('Undangan Rapat Koordinasi')
            ->assertSee($surat->no_surat)
            ->assertSee($this->instansi()->nama)
            ->assertSee('(opsional)', false);
    }

    public function test_kartu_pdf_dapat_diunduh_admin_dan_super(): void
    {
        $super = User::factory()->super()->create();
        $admin = User::factory()->admin()->create();
        $unit = $this->unit('UMPEG');
        $surat = $this->suratMasuk($super);
        $disposisi = $this->disposisi($surat, $super, [$unit]);
        app(KartuDisposisiService::class)->simpan($disposisi);

        $this->actingAs($super)->get(route('register.pdf', $disposisi))
            ->assertOk()->assertHeader('content-type', 'application/pdf');

        $this->actingAs($admin)->get(route('register.pdf', $disposisi))->assertOk();
    }

    public function test_nama_berkas_pdf_kartu_mengikuti_isi_lembar(): void
    {
        $super = User::factory()->super()->create();
        $unit = $this->unit();
        $surat = $this->suratMasuk($super, ['perihal' => 'Undangan Rapat Koordinasi']);
        $disposisi = $this->disposisi($surat, $super, [$unit]);
        app(KartuDisposisiService::class)->simpan($disposisi);

        $nama = $disposisi->fresh()->namaBerkasKartu();

        $this->assertSame(
            'Kartu Register - '.str_replace('/', '_', $surat->no_agenda)
                .' - Undangan Rapat Koordinasi - '.$disposisi->tgl_disposisi->format('Y-m-d').'.pdf',
            $nama
        );
        $this->assertStringNotContainsString('/', $nama);

        $this->actingAs($super)->get(route('register.pdf', $disposisi))
            ->assertOk()
            ->assertDownload($nama);
    }

    public function test_cetak_massal_menghasilkan_satu_pdf(): void
    {
        $super = User::factory()->super()->create();
        $unit = $this->unit();

        $satu = $this->disposisi($this->suratMasuk($super), $super, [$unit]);
        $dua = $this->disposisi($this->suratMasuk($super), $super, [$unit]);

        $response = $this->actingAs($super)->get(route('register.cetak', [
            'ids' => [$satu->id, $dua->id],
        ]));

        $response->assertOk()->assertHeader('content-type', 'application/pdf');

        $isi = method_exists($response->baseResponse, 'streamedContent')
            ? $response->streamedContent()
            : $response->getContent();

        $this->assertStringStartsWith('%PDF', $isi);
    }

    public function test_notifikasi_register_melampirkan_pdf_kartu(): void
    {
        $super = User::factory()->super()->create();
        $unit = $this->unit();
        $surat = $this->suratMasuk($super);

        $this->actingAs($super)->post(route('register.store', $surat), [
            'indeks' => '400',
            'tgl_penyelesaian' => now()->addDay()->toDateString(),
            'instruksi' => 'Tindak lanjuti.',
            'tujuan' => [$unit->id],
        ]);

        $disposisi = Disposisi::where('surat_masuk_id', $surat->id)->firstOrFail();
        $mail = (new DisposisiBaru($disposisi->load('pembuat', 'tujuan.unit')))
            ->toMail(User::factory()->super()->unit($unit)->create());

        $this->assertCount(1, $mail->attachments);
        $this->assertSame('application/pdf', $mail->attachments[0]['options']['mime']);
        $this->assertSame($disposisi->kartu_nama, $mail->attachments[0]['options']['as']);
    }

    public function test_peran_lama_tidak_dapat_meregister(): void
    {
        $surat = $this->suratMasuk(User::factory()->super()->create());
        $lama = User::factory()->role('staf')->create();

        // Akun berperan lama langsung diputus sesinya oleh middleware AkunAktif.
        $this->actingAs($lama)->get(route('register.form', $surat))->assertRedirect(route('login'));

        $this->actingAs($lama)->post(route('register.store', $surat), [
            'indeks' => '400.7',
        ])->assertRedirect(route('login'));
    }
}
