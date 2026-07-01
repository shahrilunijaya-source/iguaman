@extends('layouts.staff')

@section('title', 'Log Audit')

@php
    $tone = [
        'INSERT' => 'pill--received', 'APPROVE' => 'pill--received',
        'UPDATE' => 'pill--pending', 'REJECT' => 'pill--overdue', 'DELETE' => 'pill--overdue',
    ];
@endphp

@section('content')
    <div class="tap-head">
        <div>
            <h1 class="tap-head__title">Log Audit<span class="dot"></span></h1>
            <p class="tap-head__sub"><strong>{{ number_format($log->total()) }}</strong> rekod perubahan</p>
        </div>
    </div>

    <form method="GET" action="{{ route('audit.index') }}" class="tap-filters">
        <select name="table" class="tap-chip" onchange="this.form.submit()">
            <option value="">Semua Jadual</option>
            @foreach ($tableList as $t)
                <option value="{{ $t }}" @selected(($filters['table'] ?? '') === $t)>{{ $t }}</option>
            @endforeach
        </select>
        <select name="action" class="tap-chip" onchange="this.form.submit()">
            <option value="">Semua Tindakan</option>
            @foreach ($actionList as $a)
                <option value="{{ $a }}" @selected(($filters['action'] ?? '') === $a)>{{ $a }}</option>
            @endforeach
        </select>
        <div class="tap-search">
            <span class="tap-search__icon">⌕</span>
            <input type="text" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Cari catatan atau pengguna…" aria-label="Cari catatan atau pengguna…">
        </div>
    </form>

    <div class="tap-table">
        <div class="tap-table__head" style="grid-template-columns: 150px 160px 110px 2fr 1.2fr;">
            <div class="tap-table__th">Tarikh</div>
            <div class="tap-table__th">Jadual · ID</div>
            <div class="tap-table__th">Tindakan</div>
            <div class="tap-table__th">Catatan</div>
            <div class="tap-table__th">Oleh</div>
        </div>

        @forelse ($log as $row)
            <div class="tap-row" style="grid-template-columns: 150px 160px 110px 2fr 1.2fr;">
                <div class="tap-row__no">{{ optional($row->modified_date)->format('d/m/Y H:i') ?: '—' }}</div>
                <div class="tap-row__tujuan">{{ $row->table_name }} <span style="color:var(--mute)">#{{ $row->record_id }}</span></div>
                <div><span class="pill {{ $tone[$row->action_type] ?? 'pill--pending' }}">{{ $row->action_type }}</span></div>
                <div class="tap-row__sub">{{ $row->remarks ?: '—' }}</div>
                <div class="tap-row__tujuan">{{ $row->modified_by }}</div>
            </div>
        @empty
            <div class="dash-empty" style="border:0">
                <div class="dash-empty__title">Tiada rekod audit<span class="dot"></span></div>
                <div class="dash-empty__sub">Laraskan penapis atau carian.</div>
            </div>
        @endforelse

        @if ($log->hasPages())
            <div class="tap-page">
                <span>Halaman {{ $log->currentPage() }} / {{ $log->lastPage() }} · {{ number_format($log->total()) }} rekod</span>
                <div class="tap-page__nav">
                    @if ($log->onFirstPage())
                        <span class="tap-page__btn" style="opacity:.4">← Sebelum</span>
                    @else
                        <a href="{{ $log->previousPageUrl() }}" class="tap-page__btn">← Sebelum</a>
                    @endif
                    @if ($log->hasMorePages())
                        <a href="{{ $log->nextPageUrl() }}" class="tap-page__btn">Seterusnya →</a>
                    @else
                        <span class="tap-page__btn" style="opacity:.4">Seterusnya →</span>
                    @endif
                </div>
            </div>
        @endif
    </div>
@endsection
