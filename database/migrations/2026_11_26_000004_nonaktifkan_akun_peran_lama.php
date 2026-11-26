<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Hanya akun Administrator dan Super TU yang boleh aktif. Akun dengan peran
     * lain cukup dinonaktifkan, tidak dihapus, sehingga riwayat surat yang
     * dicatatnya tetap utuh.
     */
    public function up(): void
    {
        $peranAktif = DB::table('roles')->whereIn('nama', ['admin', 'super'])->pluck('id');

        DB::table('users')
            ->whereNotIn('role_id', $peranAktif)
            ->update(['aktif' => false]);
    }

    public function down(): void
    {
        // Sengaja tidak mengaktifkan kembali: status aktif dikelola administrator.
    }
};
