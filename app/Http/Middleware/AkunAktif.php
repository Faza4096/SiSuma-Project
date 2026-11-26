<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Akun yang dinonaktifkan administrator, atau yang masih memakai peran lama,
 * langsung kehilangan sesinya sehingga hanya Administrator dan Super TU aktif.
 */
class AkunAktif
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && (! $user->aktif || ! $user->bolehMasuk())) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->withErrors([
                'email' => $user->aktif
                    ? 'Akun ini tidak memiliki akses. Hanya Administrator dan Super TU yang dapat masuk.'
                    : 'Akun Anda dinonaktifkan. Hubungi administrator instansi.',
            ]);
        }

        return $next($request);
    }
}
