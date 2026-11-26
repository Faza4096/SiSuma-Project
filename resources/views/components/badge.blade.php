@props(['value' => '', 'type' => 'status'])

@php
    $peta = [
        'status' => [
            'diterima' => ['Diterima', 'tone-info'],
            'didisposisi' => ['Diregister', 'tone-wait'],
            'diproses' => ['Diproses', 'tone-info'],
            'selesai' => ['Selesai', 'tone-done'],
            'diarsipkan' => ['Diarsipkan', 'tone-muted'],
            'belum' => ['Belum', 'tone-muted'],
        ],
        'sifat' => [
            'biasa' => ['Biasa', 'tone-muted'],
            'segera' => ['Segera', 'tone-wait'],
            'penting' => ['Penting', 'tone-urgent'],
            'rahasia' => ['Rahasia', 'tone-alert'],
        ],
        'role' => [
            'admin' => ['Administrator', 'tone-info'],
            'super' => ['Super TU', 'tone-done'],
        ],
    ];

    [$label, $kelas] = $peta[$type][$value] ?? [\Illuminate\Support\Str::headline($value ?: '-'), 'tone-muted'];
@endphp

<span {{ $attributes->merge(['class' => "gov-badge ring-1 ring-inset {$kelas}"]) }}>{{ $label }}</span>
