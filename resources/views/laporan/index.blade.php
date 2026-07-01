@extends('layouts.staff')

@section('title', 'Laporan')

@php
    $groups = collect($reports)->groupBy('group');
@endphp

@section('content')
    <div class="tap-head">
        <div>
            <h1 class="tap-head__title">Laporan<span class="dot"></span></h1>
            <p class="tap-head__sub">Laporan litigasi &amp; pengantaraan - papar, tapis, eksport CSV/PDF.</p>
        </div>
    </div>

    @foreach ($groups as $groupName => $items)
        <div class="dash-sec">
            <div class="dash-sec__head"><span class="dash-sec__eyebrow">{{ $groupName }}</span></div>
            <div style="display:grid; grid-template-columns: repeat(3, 1fr); gap:14px;">
                @foreach ($items as $key => $r)
                    <a href="{{ route('laporan.show', $key) }}" style="text-decoration:none; color:inherit; border:1px solid var(--line); border-radius:var(--r-lg); padding:18px; background:#fff; display:block;"
                       onmouseover="this.style.borderColor='var(--teal)';" onmouseout="this.style.borderColor='var(--line)';">
                        <div style="font-weight:600; font-size:14px; color:var(--ink); margin-bottom:4px;">{{ $r['label'] }}</div>
                        <div style="font-size:11.5px; color:var(--mute);">{{ count($r['columns']) }} lajur · CSV / PDF</div>
                    </a>
                @endforeach
            </div>
        </div>
    @endforeach
@endsection
