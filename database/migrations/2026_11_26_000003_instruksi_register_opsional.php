<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Instruksi/informasi pada register boleh dikosongkan karena menunggu
     * perintah pimpinan; penerusan kepada unit juga tidak wajib.
     */
    public function up(): void
    {
        Schema::table('disposisi', function (Blueprint $t) {
            $t->text('instruksi')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('disposisi', function (Blueprint $t) {
            $t->text('instruksi')->nullable(false)->change();
        });
    }
};
