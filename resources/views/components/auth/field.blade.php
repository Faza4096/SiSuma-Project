@props([
    'name',
    'label',
    'type' => 'text',
    'icon' => null,
    'value' => null,
    'placeholder' => null,
    'autocomplete' => 'off',
    'autofocus' => false,
    'help' => null,
    'toggle' => false,
])

<div @if ($toggle) x-data="{ tampil: false }" @endif>
    <label for="{{ $name }}" class="gov-label">{{ $label }}</label>

    <div class="relative">
        @if ($icon)
            <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-slate-400">
                <x-icon :name="$icon" class="h-4 w-4" />
            </span>
        @endif

        <input id="{{ $name }}" type="{{ $type }}" name="{{ $name }}" value="{{ $value }}"
               placeholder="{{ $placeholder }}" required autocomplete="{{ $autocomplete }}"
               @if ($autofocus) autofocus @endif
               @if ($toggle) x-bind:type="tampil ? 'text' : '{{ $type }}'" @endif
               {{ $attributes->merge(['class' => 'gov-input' . ($icon ? ' pl-9' : '') . ($toggle ? ' pr-10' : '')]) }}>

        @if ($toggle)
            <button type="button" @click="tampil = !tampil"
                    :aria-label="tampil ? 'Sembunyikan kata sandi' : 'Tampilkan kata sandi'"
                    :aria-pressed="tampil"
                    class="absolute inset-y-0 right-0 flex items-center rounded-r-md px-3 text-slate-400 transition hover:text-gov-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-gov-500">
                <x-icon name="eye" class="h-4 w-4" x-show="!tampil" />
                <x-icon name="eye-off" class="h-4 w-4" x-show="tampil" x-cloak />
            </button>
        @endif
    </div>

    @if ($help)
        <p class="gov-help">{{ $help }}</p>
    @endif

    <x-input-error :messages="$errors->get($name)" class="mt-1.5 text-sm text-red-600" />
</div>
