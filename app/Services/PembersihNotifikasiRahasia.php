<?php

namespace App\Services;

use App\Models\SuratMasuk;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Surat rahasia hanya boleh dibaca unit yang menerima disposisinya, ditambah
 * Administrator dan Super TU. Notifikasi lama yang terlanjur
 * tersimpan untuk penerima di luar unit tersebut masih memuat nomor/perihal
 * rahasia, sehingga barisnya dihapus berdasarkan tautan surat pada kolom data.
 */
class PembersihNotifikasiRahasia
{
    /** @return int jumlah notifikasi yang dihapus */
    public function jalankan(): int
    {
        $dihapus = 0;

        DB::table('notifications')
            ->select('id', 'notifiable_id', 'data')
            ->chunkById(200, function ($baris) use (&$dihapus) {
                $ids = $baris
                    ->filter(fn ($notifikasi) => $this->tidakBerhak($notifikasi))
                    ->pluck('id');

                if ($ids->isNotEmpty()) {
                    DB::table('notifications')->whereIn('id', $ids)->delete();
                    $dihapus += $ids->count();
                }
            });

        return $dihapus;
    }

    /** Notifikasi rahasia yang penerimanya tidak lagi berhak membuka surat. */
    private function tidakBerhak(object $notifikasi): bool
    {
        $surat = $this->suratRahasia($notifikasi->data);

        if (! $surat) {
            return false;
        }

        return ! $surat->bolehDilihatOleh(User::find($notifikasi->notifiable_id));
    }

    private function suratRahasia(?string $data): ?SuratMasuk
    {
        $url = json_decode((string) $data, true)['url'] ?? '';

        if (! is_string($url) || ! preg_match('#/surat-masuk/(\d+)#', $url, $cocok)) {
            return null;
        }

        $surat = SuratMasuk::find((int) $cocok[1]);

        return $surat?->isRahasia() ? $surat : null;
    }
}
