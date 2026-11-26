<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ResetSandiCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_perintah_mengatur_ulang_sandi_dan_mengaktifkan_akun(): void
    {
        $admin = User::factory()->admin()->create([
            'aktif' => false,
            'must_change_password' => false,
        ]);

        $this->artisan('sisurat:reset-sandi', [
            'email' => $admin->email,
            '--sandi' => 'SandiPulih2026',
        ])->assertExitCode(0);

        $admin->refresh();

        $this->assertTrue($admin->aktif);
        $this->assertTrue($admin->must_change_password);
        $this->assertTrue(Hash::check('SandiPulih2026', $admin->password));
    }

    public function test_perintah_menolak_surel_yang_tidak_dikenal(): void
    {
        $this->artisan('sisurat:reset-sandi', [
            'email' => 'tidak-ada@example.test',
            '--sandi' => 'SandiPulih2026',
        ])->assertExitCode(1);
    }

    public function test_perintah_menolak_sandi_lemah(): void
    {
        $user = User::factory()->create();

        $this->artisan('sisurat:reset-sandi', [
            'email' => $user->email,
            '--sandi' => 'pendek',
        ])->assertExitCode(1);

        $this->assertFalse(Hash::check('pendek', $user->fresh()->password));
    }
}
