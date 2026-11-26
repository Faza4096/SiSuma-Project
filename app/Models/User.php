<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    /** Dua peran aplikasi beserta sebutannya di antarmuka. */
    public const PERAN = [
        'admin' => 'Administrator',
        'super' => 'Super TU',
    ];

    /** Peran yang boleh masuk aplikasi; akun peran lama ditolak walau masih aktif. */
    public const PERAN_AKTIF = ['admin', 'super'];

    /** Peran yang mencatat, meregister, dan mengarsipkan surat masuk. */
    public const PERAN_TATA_USAHA = ['admin', 'super'];

    /** Peran yang boleh membuat register dan meneruskan surat. */
    public const PERAN_PIMPINAN = ['admin', 'super'];

    /** Peran yang melihat seluruh surat untuk pengawasan dan tindak lanjut. */
    public const PERAN_LIHAT_SEMUA = ['admin', 'super'];

    /** Peran yang boleh menghapus surat beserta berkasnya secara permanen. */
    public const PERAN_HAPUS_PERMANEN = ['admin', 'super'];

    protected $fillable = [
        'unit_id', 'role_id', 'name', 'nip', 'jabatan', 'foto_profil', 'email', 'password',
        'must_change_password', 'aktif', 'password_diubah_pada',
    ];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'email_verified_at' => 'datetime',
            'must_change_password' => 'boolean',
            'aktif' => 'boolean',
            'password_diubah_pada' => 'datetime',
        ];
    }

    public function role()
    {
        return $this->belongsTo(Role::class);
    }

    public function unit()
    {
        return $this->belongsTo(UnitKerja::class, 'unit_id');
    }

    public function punyaRole(string ...$roles): bool
    {
        return in_array($this->role?->nama, $roles, true);
    }

    /** Sebutan peran untuk antarmuka, mis. "Petugas TU". */
    public function sebutanPeran(): string
    {
        $nama = $this->role?->nama;

        return self::PERAN[$nama] ?? Str::headline($nama ?: '-');
    }

    /** Hanya Administrator dan Super TU yang boleh memakai aplikasi. */
    public function bolehMasuk(): bool
    {
        return $this->punyaRole(...self::PERAN_AKTIF);
    }

    /** Administrator dan Super TU mencatat, meregister, dan mengarsipkan surat. */
    public function bolehKelolaSurat(): bool
    {
        return $this->punyaRole(...self::PERAN_TATA_USAHA);
    }

    /** Administrator dan Super TU membuat register surat masuk. */
    public function bolehBeriDisposisi(): bool
    {
        return $this->punyaRole(...self::PERAN_PIMPINAN);
    }

    /** Administrator dan Super TU melihat seluruh surat. */
    public function bolehLihatSemuaSurat(): bool
    {
        return $this->punyaRole(...self::PERAN_LIHAT_SEMUA);
    }

    /** Hanya Administrator dan Super yang boleh menghapus permanen surat. */
    public function bolehHapusPermanen(): bool
    {
        return $this->punyaRole(...self::PERAN_HAPUS_PERMANEN);
    }

    public function perluGantiSandi(): bool
    {
        return (bool) $this->must_change_password;
    }

    /** Alamat foto profil; null bila pengguna belum mengunggah foto. */
    public function fotoProfilUrl(): ?string
    {
        if (! $this->foto_profil) {
            return null;
        }

        return route('profile.foto', [
            'user' => $this->id,
            // Versi menyertai alamat agar peramban memuat foto terbaru usai unggah.
            'v' => $this->updated_at?->timestamp ?? time(),
        ]);
    }

    public function suratMasukDiarsipkan()
    {
        return $this->hasMany(SuratMasuk::class, 'dicatat_oleh');
    }

    /** Penerima notifikasi register baru: Administrator dan Super TU. */
    public function scopePemberiDisposisi($query)
    {
        return $query->whereHas('role', fn ($q) => $q->whereIn('nama', self::PERAN_PIMPINAN));
    }
}
