@extends('layouts.staff')

@section('title', 'Permohonan Peguam')

@php
    $pills = ['0' => 'pill--review', '1' => 'pill--approved', '2' => 'pill--rejected', '3' => 'pill--received'];
@endphp

@section('content')
    <div class="tap-head">
        <div>
            <h1 class="tap-head__title">Permohonan Peguam Panel<span class="dot"></span></h1>
            <p class="tap-head__sub"><strong>{{ number_format($pending) }}</strong> menunggu keputusan</p>
        </div>
    </div>

    <div class="tap-filters">
        <a href="{{ route('permohonan-peguam.index') }}" class="tap-chip {{ $status === null || $status === '' ? 'is-active' : '' }}">Semua</a>
        @foreach ($statusLabels as $code => $label)
            <a href="{{ route('permohonan-peguam.index', ['status' => $code]) }}" class="tap-chip {{ (string) $status === (string) $code ? 'is-active' : '' }}">{{ $label }}</a>
        @endforeach
    </div>

    {{-- W10: approval-track (jalur) filter — criminal vs civil/syariah queues. --}}
    <div class="tap-filters">
        <a href="{{ route('permohonan-peguam.index', ['status' => $status]) }}" class="tap-chip {{ ($jalur ?? '') === '' || $jalur === null ? 'is-active' : '' }}">Semua Jalur</a>
        @foreach ($jalurList as $j)
            <a href="{{ route('permohonan-peguam.index', ['status' => $status, 'jalur' => $j]) }}" class="tap-chip {{ ($jalur ?? '') === $j ? 'is-active' : '' }}">{{ str_replace('_', '/', $j) }}</a>
        @endforeach
    </div>

    <div class="tap-table">
        <div class="tap-table__head" style="grid-template-columns: 2fr 1.2fr 1fr 1fr 120px;">
            <div class="tap-table__th">Peguam</div>
            <div class="tap-table__th">No. KP</div>
            <div class="tap-table__th">Sokongan</div>
            <div class="tap-table__th">Status</div>
            <div class="tap-table__th">Tarikh Mohon</div>
        </div>
        @forelse ($permohonan as $p)
            <a href="{{ route('permohonan-peguam.show', $p) }}" class="tap-row" style="grid-template-columns: 2fr 1.2fr 1fr 1fr 120px;">
                <div class="tap-row__title">{{ $p->namaPeguam }}</div>
                <div class="tap-row__no">{{ $p->kpBaru }}</div>
                <div class="tap-row__tujuan">{{ $p->sokonganPengarah === '1' ? 'Disokong' : ($p->sokonganPengarah === '0' ? 'Tidak Sokong' : '—') }}</div>
                <div><span class="pill {{ $pills[$p->permohonan_status] ?? 'pill--received' }}">{{ $statusLabels[$p->permohonan_status] ?? 'Baharu' }}</span></div>
                <div class="tap-row__due"><div class="tap-row__due-label">{{ optional($p->tarikhMohon)->format('d/m/Y') ?: '—' }}</div></div>
            </a>
        @empty
            <div class="dash-empty" style="border:0"><div class="dash-empty__title">Tiada permohonan<span class="dot"></span></div></div>
        @endforelse

        @if ($permohonan->hasPages())
            <div class="tap-page">
                <span>Halaman {{ $permohonan->currentPage() }} / {{ $permohonan->lastPage() }}</span>
                <div class="tap-page__nav">
                    @if (!$permohonan->onFirstPage())<a href="{{ $permohonan->previousPageUrl() }}" class="tap-page__btn">← Sebelum</a>@endif
                    @if ($permohonan->hasMorePages())<a href="{{ $permohonan->nextPageUrl() }}" class="tap-page__btn">Seterusnya →</a>@endif
                </div>
            </div>
        @endif
    </div>
@endsection
