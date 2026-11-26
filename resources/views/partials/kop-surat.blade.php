@php
    /** @var \App\Models\PengaturanInstansi $identitas */
    $identitas = $identitas ?? \App\Models\PengaturanInstansi::current();
    $logoSrc = $identitas->logoDataUri();
@endphp

{{-- Kop resmi memakai tabel agar kompatibel dengan dompdf (tanpa flexbox). --}}
<style>
    .kop { width: 100%; border-collapse: collapse; }
    .kop td { padding: 0; vertical-align: middle; }
    .kop .kop-logo { width: 24mm; text-align: left; }
    .kop .kop-logo img { height: 19mm; }
    .kop .kop-spacer { width: 24mm; }
    .kop .kop-teks { text-align: center; }
    .kop .kop-pemerintah { font-size: 12.75pt; font-weight: bold; letter-spacing: .3px; line-height: 1.2; }
    .kop .kop-unit { font-size: 18pt; font-weight: bold; line-height: 1.15; text-transform: uppercase; }
    .kop .kop-alamat { font-size: 8.5pt; margin-top: 1mm; }
    .kop-garis { border-top: 3px solid #000; border-bottom: 1px solid #000; height: 1.5mm; margin: 2.5mm 0 4mm; }
</style>

<table class="kop">
    <tr>
        <td class="kop-logo">
            @if ($logoSrc)
                <img src="{{ $logoSrc }}" alt="Lambang {{ $identitas->nama }}">
            @endif
        </td>
        <td class="kop-teks">
            <div class="kop-pemerintah">{{ $identitas->namaKop() }}</div>
            <div class="kop-unit">{{ mb_strtoupper($identitas->namaUnit()) }}</div>
            @if ($identitas->alamatLengkap())
                <div class="kop-alamat">{{ $identitas->alamatLengkap() }}</div>
            @endif
        </td>
        {{-- Kolom kosong selebar logo agar teks tepat di tengah halaman. --}}
        <td class="kop-spacer"></td>
    </tr>
</table>
<div class="kop-garis"></div>
