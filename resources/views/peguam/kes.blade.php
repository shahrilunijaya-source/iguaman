@extends('layouts.peguam')

@section('title', 'Kes Saya')

@section('content')
    <div class="tap-head">
        <div>
            <h1 class="tap-head__title">Kes Saya<span class="dot"></span></h1>
            <p class="tap-head__sub"><strong>{{ number_format($kes->total()) }}</strong> kes ditugaskan kepada {{ $profile->nama_peguam ?? auth()->user()->name }}</p>
        </div>
    </div>

    @unless ($profile)
        <div class="dash-empty">
            <div class="dash-empty__title">Profil peguam belum dipadankan<span class="dot"></span></div>
            <div class="dash-empty__sub">Akaun anda belum dipautkan ke rekod peguam panel. Hubungi pentadbir JBG.</div>
        </div>
    @else
        <div class="tap-table">
            <div class="tap-table__head" style="grid-template-columns: 150px 2fr 1.2fr 1fr 120px;">
                <div class="tap-table__th">No. Fail</div>
                <div class="tap-table__th">Pemohon</div>
                <div class="tap-table__th">Kategori</div>
                <div class="tap-table__th">Status</div>
                <div class="tap-table__th">Tarikh Agih</div>
            </div>
            @forelse ($kes as $row)
                <div class="tap-row" style="grid-template-columns: 150px 2fr 1.2fr 1fr 120px;">
                    <div class="tap-row__no">{{ $row->no_fail ?: '—' }}</div>
                    <div>
                        <div class="tap-row__title">{{ $row->nama ?: 'Tanpa Nama' }}</div>
                        <div class="tap-row__sub">{{ $row->nokp ?: '—' }}</div>
                    </div>
                    <div class="tap-row__tujuan">{{ $row->kategori_kes ?: '—' }}</div>
                    <div><span class="pill pill--received">{{ $row->status ?: 'baru' }}</span></div>
                    <div class="tap-row__due"><div class="tap-row__due-label">{{ optional($row->tarikh_penugasan_peguam_panel)->format('d/m/Y') ?: '—' }}</div></div>
                </div>
            @empty
                <div class="dash-empty" style="border:0">
                    <div class="dash-empty__title">Tiada kes ditugaskan<span class="dot"></span></div>
                </div>
            @endforelse

            @if ($kes->hasPages())
                <div class="tap-page">
                    <span>Halaman {{ $kes->currentPage() }} / {{ $kes->lastPage() }}</span>
                    <div class="tap-page__nav">
                        @if (!$kes->onFirstPage())<a href="{{ $kes->previousPageUrl() }}" class="tap-page__btn">← Sebelum</a>@endif
                        @if ($kes->hasMorePages())<a href="{{ $kes->nextPageUrl() }}" class="tap-page__btn">Seterusnya →</a>@endif
                    </div>
                </div>
            @endif
        </div>
    @endunless
@endsection
