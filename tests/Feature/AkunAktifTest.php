<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AkunAktifTest extends TestCase
{
    use RefreshDatabase;

    public function test_akun_nonaktif_tidak_dapat_login(): void
    {
        $user = User::factory()->super()->create(['aktif' => false]);

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ])->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_sesi_akun_yang_dinonaktifkan_langsung_diputus(): void
    {
        $user = User::factory()->super()->create(['aktif' => true]);

        $this->actingAs($user);
        $user->update(['aktif' => false]);

        $this->get(route('dashboard'))->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_akun_peran_lama_tidak_dapat_login_walau_masih_aktif(): void
    {
        $user = User::factory()->role('staf')->create(['aktif' => true]);

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ])->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_sesi_akun_berperan_lama_langsung_diputus(): void
    {
        $user = User::factory()->role('staf')->create(['aktif' => true]);

        $this->actingAs($user);

        $this->get(route('dashboard'))->assertRedirect(route('login'));
        $this->assertGuest();
    }
}
