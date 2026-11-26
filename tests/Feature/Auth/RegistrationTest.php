<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_halaman_registrasi_publik_tidak_tersedia(): void
    {
        // /register kini halaman buku register internal yang wajib masuk.
        $this->get('/register')->assertRedirect(route('login'));
    }

    public function test_pendaftaran_publik_ditolak(): void
    {
        // Tidak ada rute POST /register; hanya pendaftaran lewat menu Pengguna.
        $this->post('/register', [
            'name' => 'Penyerang',
            'email' => 'penyerang@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])->assertStatus(405);

        $this->assertDatabaseMissing('users', ['email' => 'penyerang@example.com']);
        $this->assertGuest();
    }

    public function test_akun_hanya_bisa_dibuat_administrator(): void
    {
        $this->get('/register')->assertRedirect(route('login'));

        // Hanya administrator yang boleh menambah pengguna.
        $super = User::factory()->super()->create();
        $this->actingAs($super)->get(route('pengguna.create'))->assertForbidden();
    }
}
