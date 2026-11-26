@extends('layouts.sisurat')

@section('judul', 'Diarsipkan')
@section('subjudul', 'Surat masuk yang sudah diarsipkan beserta tanggal dan petugas arsipnya.')

@section('aksi')
    <a href="{{ route('arsip.ekspor', request()->query()) }}" class="gov-btn-outline">
        <x-icon name="download" class="h-4 w-4" /> Ekspor CSV
    </a>
    <a href="{{ route('arsip.pdf', request()->query()) }}" target="_blank" class="gov-btn">
        <x-icon name="print" class="h-4 w-4" /> Ekspor PDF
    </a>
@endsection

@section('isi')
    <form method="GET" class="gov-card mb-4 p-4">
        <div class="grid grid-cols-2 items-end gap-3 md:grid-cols-2 xl:grid-cols-5">
            <div class="relative col-span-2">
                <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-slate-400">
                    <x-icon name="search" class="h-4 w-4" />
                </span>
                <label for="cari" class="sr-only">Cari arsip</label>
                <input id="cari" name="cari" value="{{ request('cari') }}"
                       placeholder="Cari nomor agenda, nomor surat, perihal, atau lokasi arsip…"
                       class="gov-input pl-9">
            </div>
            <div>
                <label class="gov-label" for="instansi_id">Pengirim</label>
                <select id="instansi_id" name="instansi_id" class="gov-input" x-on:change="$el.form.requestSubmit()">
                    <option value="">Semua instansi</option>
                    @foreach ($instansi as $i)
                        <option value="{{ $i->id }}" @selected(request('instansi_id') == $i->id)>{{ $i->nama }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="gov-label" for="klasifikasi_id">Klasifikasi</label>
                <select id="klasifikasi_id" name="klasifikasi_id" class="gov-input" x-on:change="$el.form.requestSubmit()">
                    <option value="">Semua klasifikasi</option>
                    @foreach ($klasifikasi as $k)
                        <option value="{{ $k->id }}" @selected(request('klasifikasi_id') == $k->id)>{{ $k->kode }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-span-2 flex gap-2">
                <button class="gov-btn w-full md:w-auto"><x-icon name="filter" class="h-4 w-4" /> Terapkan</button>
                @if (request()->hasAny(['cari', 'instansi_id', 'klasifikasi_id']))
                    <a href="{{ route('arsip.index') }}" class="gov-btn-outline">Reset</a>
                @endif
            </div>
        </div>
    </form>

    <div class="gov-card overflow-hidden">
        <div class="gov-card-head">
            <p class="gov-card-title">Surat Masuk Diarsipkan</p>
            <p class="text-xs text-slate-500">{{ $surat->total() }} surat diarsipkan</p>
        </div>

        <div class="overflow-x-auto">
            <table class="gov-table-w980 divide-y divide-slate-100">
                <thead class="bg-slate-50/70">
                    <tr>
                        <th class="gov-th w-[140px]">No. Agenda</th>
                        <th class="gov-th">Perihal &amp; Nomor Surat</th>
                        <th class="gov-th w-[176px]">Pengirim</th>
                        <th class="gov-th w-[196px]">Diarsipkan</th>
                        <th class="gov-th w-[180px]">Lokasi Arsip</th>
                        <th class="gov-th w-[72px] pr-5 text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($surat as $s)
                        <tr class="hover:bg-slate-50/70">
                            <td class="gov-td whitespace-nowrap font-mono text-xs font-semibold tabular-nums text-gov-800">{{ $s->no_agenda }}</td>
                            <td class="gov-td">
                                <a href="{{ route('surat-masuk.show', $s) }}" class="font-semibold text-slate-800 hover:text-gov-700">{{ $s->perihal }}</a>
                                <p class="mt-0.5 font-mono text-xs text-slate-500">{{ $s->no_surat }}</p>
                            </td>
                            <td class="gov-td text-xs leading-snug">{{ $s->instansi?->nama ?? '-' }}</td>
                            <td class="gov-td whitespace-nowrap text-xs tabular-nums">
                                {{ $s->diarsipkan_pada?->translatedFormat('d M Y') ?? '-' }}
                                <p class="text-slate-400">{{ $s->pengarsip?->name ?? 'Sistem' }}</p>
                            </td>
                            <td class="gov-td text-xs leading-snug">{{ $s->lokasi_arsip ?: '-' }}</td>
                            <td class="gov-td pr-5">
                                <div class="flex items-center justify-end">
                                    <a href="{{ route('surat-masuk.show', $s) }}" class="rounded-md p-1.5 text-slate-500 hover:bg-gov-50 hover:text-gov-700" title="Lihat">
                                        <x-icon name="eye" class="h-4 w-4" />
                                    </a>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-4 py-14 text-center">
                                <span class="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-slate-100 text-slate-400">
                                    <x-icon name="document" class="h-6 w-6" />
                                </span>
                                <p class="mt-3 text-sm font-semibold text-slate-600">Belum ada surat masuk diarsipkan</p>
                                <p class="mt-1 text-xs text-slate-500">Surat masuk yang tindak lanjutnya selesai dapat diarsipkan dari halaman detail surat.</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($surat->hasPages())
            <div class="border-t border-slate-100 px-5 py-3">{{ $surat->links() }}</div>
        @endif
    </div>
@endsection
