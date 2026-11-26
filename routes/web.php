<?php

use App\Http\Controllers\ArsipController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DisposisiController;
use App\Http\Controllers\InstansiController;
use App\Http\Controllers\KlasifikasiController;
use App\Http\Controllers\LampiranController;
use App\Http\Controllers\LaporanController;
use App\Http\Controllers\LogAktivitasController;
use App\Http\Controllers\NotifikasiController;
use App\Http\Controllers\PasswordWajibController;
use App\Http\Controllers\PengaturanController;
use App\Http\Controllers\PenggunaController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\SuratMasukController;
use App\Http\Controllers\UnitKerjaController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/dashboard', DashboardController::class)->name('dashboard');

    /*
    | Wajib ganti kata sandi default
    */
    Route::get('ganti-sandi-wajib', [PasswordWajibController::class, 'edit'])->name('password.wajib.edit');
    Route::put('ganti-sandi-wajib', [PasswordWajibController::class, 'update'])->name('password.wajib.update');

    /*
    | Surat masuk
    */
    Route::get('surat-masuk', [SuratMasukController::class, 'index'])->name('surat-masuk.index');
    Route::get('surat-masuk/{suratMasuk}', [SuratMasukController::class, 'show'])
        ->whereNumber('suratMasuk')->name('surat-masuk.show');

    Route::middleware('role:admin,super')->group(function () {
        Route::get('surat-masuk/create', [SuratMasukController::class, 'create'])->name('surat-masuk.create');
        Route::post('surat-masuk', [SuratMasukController::class, 'store'])->name('surat-masuk.store');
        Route::get('surat-masuk/{suratMasuk}/edit', [SuratMasukController::class, 'edit'])->name('surat-masuk.edit');
        Route::put('surat-masuk/{suratMasuk}', [SuratMasukController::class, 'update'])->name('surat-masuk.update');
        Route::delete('surat-masuk/{suratMasuk}', [SuratMasukController::class, 'destroy'])->name('surat-masuk.destroy');
        Route::patch('surat-masuk/{suratMasuk}/arsipkan', [SuratMasukController::class, 'arsipkan'])->name('surat-masuk.arsipkan');
        Route::post('surat-masuk/{suratMasuk}/lampiran', [SuratMasukController::class, 'unggahLampiran'])->name('surat-masuk.lampiran.store');
    });

    /*
    | Register & tindak lanjut surat masuk
    */
    Route::get('register', [DisposisiController::class, 'index'])->name('register.index');
    Route::get('register/cetak/pdf', [DisposisiController::class, 'cetak'])->name('register.cetak');
    Route::get('register/{disposisi}/kartu', [DisposisiController::class, 'kartu'])
        ->whereNumber('disposisi')->name('register.kartu');
    Route::get('register/{disposisi}/pdf', [DisposisiController::class, 'pdf'])
        ->whereNumber('disposisi')->name('register.pdf');

    Route::middleware('role:admin,super')->group(function () {
        Route::get('surat-masuk/{suratMasuk}/register', [DisposisiController::class, 'formRegister'])
            ->whereNumber('suratMasuk')->name('register.form');
        Route::post('surat-masuk/{suratMasuk}/register', [DisposisiController::class, 'store'])
            ->whereNumber('suratMasuk')->name('register.store');
        Route::delete('register/{disposisi}', [DisposisiController::class, 'destroy'])
            ->whereNumber('disposisi')->name('register.destroy');
    });

    Route::patch('register/tujuan/{tujuan}', [DisposisiController::class, 'ubahStatus'])->name('tujuan.status');
    Route::get('register/tujuan/{tujuan}/bukti', [DisposisiController::class, 'unduhBukti'])->name('tujuan.bukti.unduh');
    Route::post('register/tujuan/{tujuan}/bukti', [DisposisiController::class, 'unggahBukti'])->name('tujuan.bukti');
    Route::delete('register/tujuan/{tujuan}/bukti', [DisposisiController::class, 'hapusBukti'])->name('tujuan.bukti.hapus');

    /*
    | Arsip surat
    */
    Route::get('arsip', [ArsipController::class, 'index'])->name('arsip.index');
    Route::get('arsip/ekspor', [ArsipController::class, 'ekspor'])->name('arsip.ekspor');
    Route::get('arsip/pdf', [ArsipController::class, 'pdf'])->name('arsip.pdf');

    /*
    | Laporan & ekspor
    */
    Route::get('laporan', [LaporanController::class, 'index'])->name('laporan.index');
    Route::get('laporan/tindak-lanjut', [LaporanController::class, 'tindakLanjut'])->name('laporan.tindak-lanjut');
    Route::get('laporan/ekspor', [LaporanController::class, 'ekspor'])->name('laporan.ekspor');
    Route::get('laporan/pdf', [LaporanController::class, 'pdf'])->name('laporan.pdf');

    /*
    | Notifikasi
    */
    Route::get('notifikasi', [NotifikasiController::class, 'index'])->name('notifikasi.index');
    Route::get('notifikasi/{id}', [NotifikasiController::class, 'baca'])->name('notifikasi.baca');
    Route::post('notifikasi/baca-semua', [NotifikasiController::class, 'bacaSemua'])->name('notifikasi.baca-semua');

    /*
    | Data master & log aktivitas (administrator)
    */
    Route::middleware('role:admin')->group(function () {
        Route::resource('unit-kerja', UnitKerjaController::class)->except('show');
        Route::resource('klasifikasi', KlasifikasiController::class)->except('show');
        Route::resource('instansi', InstansiController::class)->except('show');
        Route::resource('pengguna', PenggunaController::class)->except('show');
        Route::get('log-aktivitas', [LogAktivitasController::class, 'index'])->name('log.index');
        Route::get('pengaturan', [PengaturanController::class, 'edit'])->name('pengaturan.edit');
        Route::put('pengaturan', [PengaturanController::class, 'update'])->name('pengaturan.update');
    });

    /*
    | Pemeliharaan data: hapus permanen surat beserta berkasnya (Administrator & Super).
    */
    Route::middleware('role:admin,super')->group(function () {
        Route::get('surat-masuk/{suratMasuk}/permanen', [SuratMasukController::class, 'formHapusPermanen'])
            ->whereNumber('suratMasuk')->name('surat-masuk.hapus-permanen.form');
        Route::delete('surat-masuk/{suratMasuk}/permanen', [SuratMasukController::class, 'hapusPermanen'])
            ->whereNumber('suratMasuk')->name('surat-masuk.hapus-permanen');
    });

    /*
    | Lampiran & profil
    */
    Route::get('lampiran/{lampiran}', [LampiranController::class, 'unduh'])->name('lampiran.unduh');
    Route::delete('lampiran/{lampiran}', [SuratMasukController::class, 'hapusLampiran'])->name('lampiran.hapus');

    Route::get('profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::post('profile/foto', [ProfileController::class, 'unggahFoto'])->name('profile.foto.unggah');
    Route::delete('profile/foto', [ProfileController::class, 'hapusFoto'])->name('profile.foto.hapus');
    Route::get('foto-profil/{user}', [ProfileController::class, 'foto'])
        ->whereNumber('user')->name('profile.foto');
});

require __DIR__ . '/auth.php';
