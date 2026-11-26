<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Laporan Surat Masuk — SiSuma</title>
    <style>
        @page { size: A4 landscape; margin: 12mm 12mm; }
        * { box-sizing: border-box; }
        body { font-family: "Times New Roman", Times, serif; font-size: 9.5pt; color: #000; margin: 0; }
        .judul { text-align: center; font-size: 12pt; font-weight: bold; text-decoration: underline; margin: 0 0 1mm; }
        .sub-judul { text-align: center; font-size: 8pt; margin: 0 0 4mm; }
        table.data { width: 100%; border-collapse: collapse; margin-top: 3mm; }
        table.data th, table.data td { border: 1px solid #444; padding: 1.6mm 2mm; vertical-align: top; text-align: left; font-size: 8.5pt; }
        table.data thead { display: table-header-group; }
        table.data tr { page-break-inside: avoid; }
        table.data th { background: #e8edf3; font-weight: 700; text-transform: uppercase; letter-spacing: .3px; }
        .ringkas { margin: 2mm 0 3mm; font-size: 9pt; }
        .ringkas span { display: inline-block; margin-right: 6mm; }
        table.ttd-wrap { width: 100%; margin-top: 8mm; page-break-inside: avoid; }
        table.ttd-wrap td { vertical-align: top; }
        .ttd { width: 65mm; text-align: center; font-size: 9pt; }
        .ttd .ruang { height: 18mm; }
        .ttd .garis { border-top: 1px solid #000; padding-top: 1mm; }
        .kecil { font-size: 8pt; color: #333; }
        .toolbar { margin-bottom: 4mm; }
        .toolbar button { font-family: inherit; padding: 2mm 4mm; border: 1px solid #123; background: #0b2036; color: #fff; border-radius: 2mm; cursor: pointer; }
        @media print { .toolbar { display: none; } }
    </style>
</head>
<body>
@include('partials.kop-surat')

@if (!isset($pdf) || !$pdf)
    <div class="toolbar no-print">
        <button onclick="window.print()">Cetak / Simpan sebagai PDF</button>
    </div>
@endif

<p class="judul">LAPORAN SURAT MASUK</p>
<p class="sub-judul">
    {{ $identitas->namaUnit() }} · Sistem Register Surat Masuk (SiSuma) · Dicetak {{ now()->translatedFormat('d F Y H:i') }}
</p>

<div class="ringkas">
    <span><strong>Jumlah data:</strong> {{ $data->count() }} surat</span>
    @if (! empty($filter['dari']) || ! empty($filter['sampai']))
        <span><strong>Periode:</strong> {{ $filter['dari'] ?: '…' }} s.d. {{ $filter['sampai'] ?: '…' }}</span>
    @endif
    @foreach ($ringkasan as $status => $jumlah)
        <span>{{ \Illuminate\Support\Str::headline($status) }}: {{ $jumlah }}</span>
    @endforeach
</div>

<table class="data">
    <thead>
        <tr>
            <th style="width: 4%">No</th>
            <th style="width: 14%">No. Agenda</th>
            <th style="width: 16%">Pengirim</th>
            <th>Perihal</th>
            <th style="width: 10%">Klasifikasi</th>
            <th style="width: 11%">Diterima</th>
            <th style="width: 11%">Status</th>
        </tr>
    </thead>
    <tbody>
        @forelse ($data as $i => $s)
            <tr>
                <td>{{ $i + 1 }}</td>
                <td>{{ $s->no_agenda }}</td>
                <td>{{ $s->instansi?->nama ?? '-' }}</td>
                <td>{{ $s->perihal }}</td>
                <td>{{ $s->klasifikasi?->kode ?? '-' }}</td>
                <td>{{ $s->tgl_diterima?->format('d/m/Y') }}</td>
                <td>{{ \App\Models\SuratMasuk::STATUS[$s->status] ?? \Illuminate\Support\Str::headline($s->status) }}</td>
            </tr>
        @empty
            <tr><td colspan="7" style="text-align:center; padding:8mm;">Tidak ada data pada filter tersebut.</td></tr>
        @endforelse
    </tbody>
</table>

<table class="ttd-wrap">
    <tr>
        <td></td>
        <td class="ttd">
            Bogor, {{ now()->translatedFormat('d F Y') }}<br>
            Pengelola Tata Naskah
            <div class="ruang"></div>
            <div class="garis">{{ auth()->user()?->name ?? 'Operator' }}</div>
            <div class="kecil">NIP {{ auth()->user()?->nip ?: '-' }}</div>
        </td>
    </tr>
</table>
</body>
</html>
