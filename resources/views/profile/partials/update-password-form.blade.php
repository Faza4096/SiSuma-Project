<section>
    <h2 class="gov-card-title">Kata Sandi</h2>
    <p class="gov-help">Gunakan kata sandi panjang yang tidak dipakai di layanan lain.</p>

    <form method="post" action="{{ route('password.update') }}" class="mt-5 space-y-4"
          x-data="{ tampil: { lama: false, baru: false, ulang: false } }">
        @csrf
        @method('put')

        <div>
            <label for="update_password_current_password" class="gov-label">Kata Sandi Saat Ini</label>
            <div class="relative">
                <input id="update_password_current_password" name="current_password" type="password" class="gov-input pr-10" autocomplete="current-password"
                       x-bind:type="tampil.lama ? 'text' : 'password'">
                <button type="button" @click="tampil.lama = !tampil.lama"
                        :aria-label="tampil.lama ? 'Sembunyikan kata sandi' : 'Tampilkan kata sandi'"
                        :aria-pressed="tampil.lama"
                        class="absolute inset-y-0 right-0 flex items-center rounded-r-md px-3 text-slate-400 transition hover:text-gov-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-gov-500">
                    <x-icon name="eye" class="h-4 w-4" x-show="!tampil.lama" />
                    <x-icon name="eye-off" class="h-4 w-4" x-show="tampil.lama" x-cloak />
                </button>
            </div>
            <x-input-error :messages="$errors->updatePassword->get('current_password')" class="mt-1.5 text-sm text-red-600" />
        </div>

        <div>
            <label for="update_password_password" class="gov-label">Kata Sandi Baru</label>
            <div class="relative">
                <input id="update_password_password" name="password" type="password" class="gov-input pr-10" autocomplete="new-password"
                       x-bind:type="tampil.baru ? 'text' : 'password'">
                <button type="button" @click="tampil.baru = !tampil.baru"
                        :aria-label="tampil.baru ? 'Sembunyikan kata sandi' : 'Tampilkan kata sandi'"
                        :aria-pressed="tampil.baru"
                        class="absolute inset-y-0 right-0 flex items-center rounded-r-md px-3 text-slate-400 transition hover:text-gov-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-gov-500">
                    <x-icon name="eye" class="h-4 w-4" x-show="!tampil.baru" />
                    <x-icon name="eye-off" class="h-4 w-4" x-show="tampil.baru" x-cloak />
                </button>
            </div>
            <x-input-error :messages="$errors->updatePassword->get('password')" class="mt-1.5 text-sm text-red-600" />
        </div>

        <div>
            <label for="update_password_password_confirmation" class="gov-label">Ulangi Kata Sandi Baru</label>
            <div class="relative">
                <input id="update_password_password_confirmation" name="password_confirmation" type="password" class="gov-input pr-10" autocomplete="new-password"
                       x-bind:type="tampil.ulang ? 'text' : 'password'">
                <button type="button" @click="tampil.ulang = !tampil.ulang"
                        :aria-label="tampil.ulang ? 'Sembunyikan kata sandi' : 'Tampilkan kata sandi'"
                        :aria-pressed="tampil.ulang"
                        class="absolute inset-y-0 right-0 flex items-center rounded-r-md px-3 text-slate-400 transition hover:text-gov-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-gov-500">
                    <x-icon name="eye" class="h-4 w-4" x-show="!tampil.ulang" />
                    <x-icon name="eye-off" class="h-4 w-4" x-show="tampil.ulang" x-cloak />
                </button>
            </div>
            <x-input-error :messages="$errors->updatePassword->get('password_confirmation')" class="mt-1.5 text-sm text-red-600" />
        </div>

        <div class="flex items-center gap-3 pt-1">
            <button type="submit" class="gov-btn">
                <x-icon name="check" class="h-4 w-4" /> Simpan Kata Sandi
            </button>

            @if (session('status') === 'password-updated')
                <p x-data="{ tampil: true }" x-show="tampil" x-transition x-init="setTimeout(() => tampil = false, 2500)"
                   class="text-[12px] font-semibold text-leaf-700">Tersimpan.</p>
            @endif
        </div>
    </form>
</section>
