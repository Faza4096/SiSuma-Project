<?php

namespace App\Policies;

use App\Models\SuratMasuk;
use App\Models\User;

class SuratMasukPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, SuratMasuk $surat): bool
    {
        return $surat->bolehDilihatOleh($user);
    }

    public function create(User $user): bool
    {
        return $user->bolehKelolaSurat();
    }

    public function update(User $user, SuratMasuk $surat): bool
    {
        return $user->bolehKelolaSurat() && $surat->status !== 'diarsipkan';
    }

    public function delete(User $user, SuratMasuk $surat): bool
    {
        return $user->bolehKelolaSurat() && $surat->bisaDihapus();
    }

    /** Register surat masuk oleh Administrator dan Super TU. */
    public function register(User $user, SuratMasuk $surat): bool
    {
        return $user->bolehBeriDisposisi();
    }

    /**
     * Khusus Administrator dan Super TU: register, lampiran, dan berkasnya ikut
     * terhapus sehingga tidak dapat dibatalkan.
     */
    public function hapusPermanen(User $user, SuratMasuk $surat): bool
    {
        return $user->bolehHapusPermanen();
    }

    public function arsipkan(User $user, SuratMasuk $surat): bool
    {
        return $user->bolehKelolaSurat() && $surat->bisaDiarsipkan();
    }

    public function kelolaLampiran(User $user, SuratMasuk $surat): bool
    {
        return $user->bolehKelolaSurat();
    }
}
