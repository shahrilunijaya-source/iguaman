@extends('layouts.staff')

@section('title', 'Agihan Peguam Luar')

@section('content')
<style>
    .al-table { width:100%; border-collapse:collapse; font-size:13px; }
    .al-table th { text-align:left; padding:10px 12px; border-bottom:2px solid var(--line); color:var(--mute); font-size:12px; text-transform:uppercase; letter-spacing:.03em; }
    .al-table td { padding:10px 12px; border-bottom:1px solid var(--line); vertical-align:top; }
    .al-actions { display:flex; gap:6px; flex-wrap:wrap; justify-content:flex-end; }
    .al-pill { display:inline-block; padding:3px 10px; border-radius:999px; font-size:11px; font-weight:600; }
    .al-pill--grab { background:rgba(26,111,168,.12); color:var(--brand,#1a6fa8); }
    .al-pill--diagih { background:rgba(16,185,129,.12); color:#10b981; }
    .al-pill--luput { background:rgba(239,68,68,.10); color:var(--danger,#ef4444); }
    .al-pill--baru { background:var(--paper-2,#eef4f3); color:var(--pine-deep,#0d2e48); }
    .al-filter { display:flex; gap:8px; flex-wrap:wrap; margin-bottom:16px; }
</style>

@php
    $plLabel = [
        \App\Models\KhidmatNasihat::PL_BUKA_GRAB => ['Dibuka Grab', 'al-pill--grab'],
        \App\Models\KhidmatNasihat::PL_DIAGIH => ['Diagihkan', 'al-pill--diagih'],
        \App\Models\KhidmatNasihat::PL_LUPUT => ['Grab Luput', 'al-pill--luput'],
    ];
@endphp

<div class="tap-head">
    <div>
        <h1 class="tap-head__title">Agihan Peguam Luar<span class="dot"></span></h1>
        <p class="tap-head__sub">Agih Khidmat Nasihat selesai kepada peguam panel luar — buka grab (siapa cepat) atau agih terus.</p>
    </div>
</div>

@if (session('status'))
    <div class="formerr" style="color: var(--success); background: rgba(16,185,129,0.08); border-color: rgba(16,185,129,0.18); margin-bottom:14px;">{{ session('status') }}</div>
@endif
@if (session('error'))
    <div class="formerr" style="margin-bottom:14px;">{{ session('error') }}</div>
@endif

<form method="GET" class="al-filter">
    <select name="status_agihan_pl" class="field__input" style="max-width:200px;" onchange="this.form.submit()">
        <option value="">Semua status</option>
        @foreach ($plLabel as $val => [$txt, $cls])
            <option value="{{ $val }}" @selected(($filters['status_agihan_pl'] ?? '') === $val)>{{ $txt }}</option>
        @endforeach
    </select>
    <input name="q" value="{{ $filters['q'] ?? '' }}" class="field__input" style="max-width:240px;" placeholder="Cari no. permohonan / nama">
    <button type="submit" class="btn btn--ghost">Cari</button>
</form>

<div class="tap-card">
    @if ($kn->isEmpty())
        <div class="dash-empty__sub" style="padding:12px 0;">Tiada Khidmat Nasihat selesai untuk diagihkan.</div>
    @else
        <table class="al-table">
            <thead>
                <tr>
                    <th>#</th><th>No. Permohonan</th><th>Mangsa</th><th>Cawangan</th><th>Status PL</th><th>Peguam</th><th></th>
                </tr>
            </thead>
            <tbody>
                @foreach ($kn as $k)
                    @php $pl = $k->status_agihan_pl; @endphp
                    <tr>
                        <td>@can('khidmat.view')<a href="{{ route('khidmat.show', $k) }}">{{ $k->id }}</a>@else{{ $k->id }}@endcan</td>
                        <td>{{ $k->no_permohonan ?: '—' }}</td>
                        <td>{{ $k->nama_mangsa ?: '—' }}</td>
                        <td>{{ optional($k->cawangan)->nama ?: '—' }}</td>
                        <td>
                            @if ($pl && isset($plLabel[$pl]))
                                <span class="al-pill {{ $plLabel[$pl][1] }}">{{ $plLabel[$pl][0] }}</span>
                            @else
                                <span class="al-pill al-pill--baru">Belum Diagih</span>
                            @endif
                        </td>
                        <td>{{ optional($k->peguamPanel)->nama_peguam ?: '—' }}</td>
                        <td>
                            <div class="al-actions">
                                @if ($pl !== \App\Models\KhidmatNasihat::PL_DIAGIH)
                                    <a href="{{ route('agihan-luar.agih', $k) }}" class="btn btn--primary" style="padding:4px 12px;font-size:12px;">Agih Terus</a>
                                    <form method="POST" action="{{ route('agihan-luar.buka-grab', $k) }}" onsubmit="return confirm('Buka kes ini untuk grab peguam panel?')">
                                        @csrf
                                        <button type="submit" class="btn btn--ghost" style="padding:4px 12px;font-size:12px;">
                                            {{ $pl === \App\Models\KhidmatNasihat::PL_BUKA_GRAB ? 'Buka Semula' : 'Buka Grab' }}
                                        </button>
                                    </form>
                                @else
                                    <form method="POST" action="{{ route('agihan-luar.tarik-semula', $k) }}" onsubmit="return confirm('Tarik semula agihan ini? Tuntutan draf akan dibatalkan dan KN boleh diagih semula.')">
                                        @csrf
                                        <button type="submit" class="btn btn--ghost" style="padding:4px 12px;font-size:12px;color:var(--danger);">↩ Tarik Semula</button>
                                    </form>
                                @endif
                            </div>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
        <div style="margin-top:16px;">{{ $kn->links() }}</div>
    @endif
</div>
@endsection
