@extends('layouts.staff')

@section('title', 'Beban Tugas Peguam')

@section('content')
    <div class="tap-head">
        <div>
            <h1 class="tap-head__title">Beban Tugas Peguam<span class="dot"></span></h1>
            <p class="tap-head__sub">Bilangan kes diagih setiap peguam panel</p>
        </div>
        <div class="tap-head__cluster">
            <span class="dash-kpi is-warn" style="padding:8px 14px;">
                <span class="dash-kpi__eyebrow">Belum Diagih</span>
                <span class="dash-kpi__value" style="font-size:18px;">{{ number_format($unassigned) }}</span>
            </span>
        </div>
    </div>

    <div class="tap-table">
        <div class="tap-table__head" style="grid-template-columns: 2fr 2fr 120px;">
            <div class="tap-table__th">Peguam</div>
            <div class="tap-table__th">Firma</div>
            <div class="tap-table__th">Bil. Kes</div>
        </div>
        @forelse ($lawyers as $l)
            <div class="tap-row" style="grid-template-columns: 2fr 2fr 120px;">
                <div class="tap-row__title">{{ $l['nama'] }}</div>
                <div class="tap-row__tujuan">{{ $l['firma'] ?: '—' }}</div>
                <div><span class="score">{{ number_format($l['kes']) }}</span></div>
            </div>
        @empty
            <div class="dash-empty" style="border:0">
                <div class="dash-empty__title">Tiada peguam panel<span class="dot"></span></div>
            </div>
        @endforelse
    </div>
@endsection
