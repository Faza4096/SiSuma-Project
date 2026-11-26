<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Peran Super menggabungkan pekerjaan Petugas TU dan Pimpinan: mencatat
     * surat masuk/keluar sekaligus meng-ACC disposisi dan menyusun laporan.
     */
    public function up(): void
    {
        DB::table('roles')->updateOrInsert(['nama' => 'super'], ['nama' => 'super']);
    }

    public function down(): void
    {
        $staf = DB::table('roles')->where('nama', 'staf')->value('id');
        $super = DB::table('roles')->where('nama', 'super')->value('id');

        if ($staf !== null && $super !== null) {
            DB::table('users')->where('role_id', $super)->update(['role_id' => $staf]);
            DB::table('roles')->where('id', $super)->delete();
        }
    }
};
