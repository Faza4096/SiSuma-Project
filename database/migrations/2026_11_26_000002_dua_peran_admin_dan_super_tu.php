<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Dua peran aktif: Administrator (pengelola sistem) dan Super TU
     * (pencatat surat masuk, register, arsip, dan laporan).
     */
    public function up(): void
    {
        foreach (['admin', 'super'] as $nama) {
            DB::table('roles')->updateOrInsert(['nama' => $nama], ['nama' => $nama]);
        }

        $super = DB::table('roles')->where('nama', 'super')->value('id');

        // Petugas TU lama menjadi Super TU dan tetap aktif. Peran di luar tata
        // usaha (pimpinan, staf, peran khusus) dipindahkan ke Super TU tetapi
        // dinonaktifkan: riwayat suratnya utuh, aksesnya berhenti.
        foreach (DB::table('roles')->whereNotIn('nama', ['admin', 'super'])->get() as $lama) {
            DB::table('users')->where('role_id', $lama->id)->update([
                'role_id' => $super,
                'aktif' => $lama->nama === 'petugas_tu',
            ]);
        }

        DB::table('roles')->whereNotIn('nama', ['admin', 'super'])->delete();
    }

    public function down(): void
    {
        foreach (['petugas_tu', 'pimpinan', 'staf'] as $nama) {
            DB::table('roles')->updateOrInsert(['nama' => $nama], ['nama' => $nama]);
        }
    }
};
