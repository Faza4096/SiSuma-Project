<?php

namespace App\Policies;

use App\Models\DisposisiTujuan;
use App\Models\User;

class DisposisiTujuanPolicy
{
    /**
     * Unit tujuan selalu boleh melihat tindak lanjutnya sendiri (termasuk
     * bukti yang ia unggah), selain itu mengikuti akses surat induk.
     */
    public function view(User $user, DisposisiTujuan $tujuan): bool
    {
        if ($tujuan->bolehDiubahOleh($user)) {
            return true;
        }

        return $tujuan->disposisi?->suratMasuk?->bolehDilihatOleh($user) ?? false;
    }

    /**
     * Administrator dan Super TU boleh memperbarui unit mana pun untuk
     * pemantauan tindak lanjut; unit tujuan tetap boleh mengubah miliknya.
     */
    public function ubah(User $user, DisposisiTujuan $tujuan): bool
    {
        return $user->punyaRole('admin', 'super') || $tujuan->bolehDiubahOleh($user);
    }
}
