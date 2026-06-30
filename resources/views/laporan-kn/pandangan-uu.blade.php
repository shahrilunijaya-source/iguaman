@extends('layouts.staff')

@section('title', 'Pandangan Undang-Undang')

@section('content')
    @include('laporan-kn._print_css')

    <div class="tap-nav js-no-print" style="margin: -24px -20px 18px; border-radius: 0;">
        <a href="{{ route('laporan-kn.index') }}" class="tap-nav__back">← Laporan KN</a>
        <span class="tap-nav__crumb">Pandangan Undang-Undang</span>
        <div class="tap-nav__cluster">
            <a href="{{ route('laporan-kn.pandangan-uu.excel', request()->query()) }}" class="tap-head__btn">⬇ Excel</a>
        </div>
    </div>

    <div class="tap-head">
        <div>
            <h1 class="tap-head__title">Pandangan Undang-Undang<span class="dot"></span></h1>
            <p class="tap-head__sub"><strong>{{ number_format($rows->total()) }}</strong> rekod</p>
        </div>
    </div>

    @include('laporan-kn._filters', ['routeName' => 'laporan-kn.pandangan-uu', 'show' => ['cawangan', 'kategori', 'subkategori', 'bulan', 'tahun']])

    <div class="tap-card" style="overflow-x:auto;">
        <table class="lkn-table">
            <thead>
                <tr>
                    <th>No. Permohonan</th>
                    <th>Nama</th>
                    <th>Kategori</th>
                    <th>Sub Kategori</th>
                    <th>Cawangan</th>
                    <th>Pandangan Undang-Undang</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($rows as $row)
                    <tr>
                        <td>{{ $row->no_permohonan ?? '—' }}</td>
                        <td>{{ $row->nama_mangsa ?? '—' }}</td>
                        <td>{{ $row->kategori?->jenis_kategori ?? '—' }}</td>
                        <td>{{ $row->subkategori?->nama ?? '—' }}</td>
                        <td>{{ $row->cawangan?->nama ?? '—' }}</td>
                        <td>{{ $row->ulasan_pegawai ?? '—' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6" style="padding:16px; color:var(--mute); text-align:center;">Tiada rekod.</td></tr>
                @endforelse
            </tbody>
        </table>

        @if ($rows->hasPages())
            <div class="tap-page js-no-print" style="margin-top:12px;">
                <span>Halaman {{ $rows->currentPage() }} / {{ $rows->lastPage() }} · {{ number_format($rows->total()) }} rekod</span>
                <div class="tap-page__nav">
                    @if ($rows->onFirstPage())<span class="tap-page__btn" style="opacity:.4">← Sebelum</span>@else<a href="{{ $rows->previousPageUrl() }}" class="tap-page__btn">← Sebelum</a>@endif
                    @if ($rows->hasMorePages())<a href="{{ $rows->nextPageUrl() }}" class="tap-page__btn">Seterusnya →</a>@else<span class="tap-page__btn" style="opacity:.4">Seterusnya →</span>@endif
                </div>
            </div>
        @endif
    </div>
@endsection
