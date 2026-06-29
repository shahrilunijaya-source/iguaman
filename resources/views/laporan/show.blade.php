@extends('layouts.staff')

@section('title', $report['label'])

@php
    $fmt = fn ($v) => $v instanceof \Illuminate\Support\Carbon ? $v->format('d/m/Y') : (($v === null || $v === '') ? '—' : $v);
    $qs = request()->only(['cawangan', 'dari', 'hingga']);
@endphp

@section('content')
    <div class="tap-nav" style="margin: -24px -20px 18px; border-radius: 0;">
        <a href="{{ route('laporan.index') }}" class="tap-nav__back">← Laporan</a>
        <span class="tap-nav__crumb">{{ $report['label'] }}</span>
        <div class="tap-nav__cluster">
            <a href="{{ route('laporan.csv', [$type] + $qs) }}" class="tap-head__btn">⬇ CSV</a>
            <a href="{{ route('laporan.pdf', [$type] + $qs) }}" target="_blank" rel="noopener" class="tap-head__btn">⬇ PDF</a>
        </div>
    </div>

    <div class="tap-head">
        <div>
            <h1 class="tap-head__title">{{ $report['label'] }}<span class="dot"></span></h1>
            <p class="tap-head__sub"><strong>{{ number_format($rows->total()) }}</strong> rekod</p>
        </div>
    </div>

    <form method="GET" action="{{ route('laporan.show', $type) }}" class="tap-filters">
        <select name="cawangan" class="tap-chip" onchange="this.form.submit()">
            <option value="">Semua Cawangan</option>
            @foreach ($cawanganList as $c)
                <option value="{{ $c }}" @selected(($filters['cawangan'] ?? '') === $c)>{{ $c }}</option>
            @endforeach
        </select>
        <label class="tap-chip" style="display:inline-flex; gap:6px; align-items:center;">Dari <input type="date" name="dari" value="{{ $filters['dari'] ?? '' }}" style="border:0; background:transparent;"></label>
        <label class="tap-chip" style="display:inline-flex; gap:6px; align-items:center;">Hingga <input type="date" name="hingga" value="{{ $filters['hingga'] ?? '' }}" style="border:0; background:transparent;"></label>
        <button type="submit" class="tap-chip">Tapis</button>
        @if (array_filter($filters))<a href="{{ route('laporan.show', $type) }}" class="tap-chip">✕ Reset</a>@endif
    </form>

    <div class="tap-card" style="overflow-x:auto;">
        <table style="width:100%; border-collapse:collapse; font-size:13px;">
            <thead>
                <tr>
                    @foreach ($report['columns'] as $label)
                        <th style="text-align:left; padding:8px 10px; border-bottom:2px solid var(--line); color:var(--pine-deep); font-size:11px; text-transform:uppercase; letter-spacing:.4px; white-space:nowrap;">{{ $label }}</th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @forelse ($rows as $row)
                    <tr>
                        @foreach (array_keys($report['columns']) as $field)
                            <td style="padding:7px 10px; border-bottom:1px solid #eef2f1;">{{ $fmt($row->$field) }}</td>
                        @endforeach
                    </tr>
                @empty
                    <tr><td colspan="{{ count($report['columns']) }}" style="padding:16px; color:var(--mute); text-align:center;">Tiada rekod.</td></tr>
                @endforelse
            </tbody>
        </table>

        @if ($rows->hasPages())
            <div class="tap-page" style="margin-top:12px;">
                <span>Halaman {{ $rows->currentPage() }} / {{ $rows->lastPage() }} · {{ number_format($rows->total()) }} rekod</span>
                <div class="tap-page__nav">
                    @if ($rows->onFirstPage())<span class="tap-page__btn" style="opacity:.4">← Sebelum</span>@else<a href="{{ $rows->previousPageUrl() }}" class="tap-page__btn">← Sebelum</a>@endif
                    @if ($rows->hasMorePages())<a href="{{ $rows->nextPageUrl() }}" class="tap-page__btn">Seterusnya →</a>@else<span class="tap-page__btn" style="opacity:.4">Seterusnya →</span>@endif
                </div>
            </div>
        @endif
    </div>
@endsection
