<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\SesiPenggunaService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;

/**
 * Pemulihan akses saat email reset tidak terjangkau atau akun terkunci, termasuk
 * akun administrator. Sandi baru wajib diganti saat pengguna pertama kali masuk.
 */
class ResetSandiPengguna extends Command
{
    protected $signature = 'sisurat:reset-sandi
                            {email? : Surel akun yang sandinya diatur ulang}
                            {--sandi= : Kata sandi baru; kosongkan agar ditanyakan tanpa echo}';

    protected $description = 'Mengatur ulang kata sandi akun, mis. administrator yang tidak bisa masuk';

    public function handle(SesiPenggunaService $sesi): int
    {
        $email = $this->argument('email') ?? $this->ask('Surel akun');
        $user = User::where('email', $email)->first();

        if (! $user) {
            $this->error("Akun dengan surel {$email} tidak ditemukan.");

            return self::FAILURE;
        }

        if ($this->option('sandi')) {
            $sandi = $konfirmasi = (string) $this->option('sandi');
        } else {
            $sandi = (string) $this->secret('Kata sandi baru (minimal 10 karakter, memuat huruf dan angka)');
            $konfirmasi = (string) $this->secret('Ulangi kata sandi baru');
        }

        if ($sandi !== $konfirmasi) {
            $this->error('Ulangi kata sandi tidak sama.');

            return self::FAILURE;
        }

        $sandiValid = Validator::make(
            ['sandi' => $sandi],
            ['sandi' => ['required', Password::defaults()]],
        );

        if ($sandiValid->fails()) {
            foreach ($sandiValid->errors()->all() as $pesan) {
                $this->error($pesan);
            }

            return self::FAILURE;
        }

        $user->forceFill([
            'password' => $sandi,
            'must_change_password' => true,
            'password_diubah_pada' => now(),
            'remember_token' => Str::random(60),
            // Akun yang dinonaktifkan ikut diaktifkan agar bisa dipakai masuk lagi.
            'aktif' => true,
        ])->save();

        $sesi->putuskan($user);

        $this->info("Kata sandi {$user->email} diatur ulang dan perangkat lama diputus.");
        $this->line('Pengguna wajib mengganti kata sandi sementara saat masuk.');

        return self::SUCCESS;
    }
}
