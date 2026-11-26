<?php

namespace App\Policies;

use App\Models\Disposisi;
use App\Models\User;

class DisposisiPolicy
{
    /** Register terlihat bila surat induknya boleh dibuka pengguna. */
    public function view(User $user, Disposisi $disposisi): bool
    {
        if ($disposisi->bolehDilihatOleh($user)) {
            return true;
        }

        return $disposisi->suratMasuk?->bolehDilihatOleh($user) ?? false;
    }

    /** Peran yang melihat seluruh surat selalu boleh mengunduh kartu. */
    public function unduhKartu(User $user, Disposisi $disposisi): bool
    {
        return $disposisi->bolehDilihatOleh($user);
    }

    /** Administrator dan Super TU membuat register. */
    public function create(User $user): bool
    {
        return $user->bolehBeriDisposisi();
    }

    /** Register dibatalkan oleh Administrator/Super TU. */
    public function delete(User $user, Disposisi $disposisi): bool
    {
        return $user->punyaRole('admin', 'super');
    }
}
