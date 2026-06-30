@extends('layouts.staff')

@section('title', 'Agih Terus · '.($kn->no_permohonan ?: 'KN #'.$kn->id))

@section('content')
<style>
    .sl-table { width:100%; border-collapse:collapse; font-size:13px; }
    .sl-table th { text-align:left; padding:10px 12px; border-bottom:2px solid var(--line); color:var(--mute); font-size:12px; text-transform:uppercase; letter-spacing:.03em; }
    .sl-table td { padding:10px 12px; border-bottom:1px solid var(--line); }
    .sl-table tr:hover td { background:var(--paper-2,#f6faf9); }
    .sl-beban { display:inline-block; min-width:26px; text-align:center; padding:2px 8px; border-radius:999px; background:var(--paper-2,#eef4f3); color:var(--pine-deep,#003D3A); font-weight:600; }
</style>

<div class="tap-nav" style="margin: -24px -20px 18px; border-radius: 0;">
    <a href="{{ route('agihan-luar.index') }}" class="tap-nav__back">← Agihan Peguam Luar</a>
    <span class="tap-nav__crumb">{{ $kn->no_permohonan ?: 'KN #'.$kn->id }}</span>
</div>

@if (session('error'))
    <div class="formerr" style="margin-bottom:14px;">{{ session('error') }}</div>
@endif
@if ($errors->any())
    <div class="formerr" style="margin-bottom:14px;">{{ $errors->first() }}</div>
@endif

<div class="tap-card" style="margin-bottom:18px;">
    <div class="tap-card__eyebrow">Khidmat Nasihat</div>
    <div class="tap-card__row"><div class="k">Mangsa</div><div class="v">{{ $kn->nama_mangsa ?: '—' }}</div></div>
    <div class="tap-card__row"><div class="k">Jenis Kes</div><div class="v">{{ $kn->jenis_kes ?: '—' }}</div></div>
    <div class="tap-card__row"><div class="k">Cawangan</div><div class="v">{{ optional($kn->cawangan)->nama ?: '—' }}</div></div>
</div>

<div class="tap-card">
    <div class="tap-card__eyebrow">Pilih Peguam Panel (disusun mengikut beban tugas terendah)</div>
    @if ($shortlist->isEmpty())
        <div class="dash-empty__sub" style="padding:12px 0;">Tiada peguam panel aktif untuk dicadangkan.</div>
    @else
        <form method="POST" action="{{ route('agihan-luar.assign', $kn) }}" onsubmit="return confirm('Agihkan KN ini kepada peguam panel yang dipilih?')">
            @csrf
            <table class="sl-table">
                <thead>
                    <tr><th></th><th>Nama Peguam</th><th>No. KP</th><th>Firma</th><th>Beban</th></tr>
                </thead>
                <tbody>
                    @foreach ($shortlist as $p)
                        <tr>
                            <td><input type="radio" name="id_peguam_panel" value="{{ $p['id'] }}" required></td>
                            <td>{{ $p['nama'] ?: '—' }}</td>
                            <td>{{ $p['kp'] ?: '—' }}</td>
                            <td>{{ $p['firma'] ?: '—' }}</td>
                            <td><span class="sl-beban">{{ $p['beban'] }}</span></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            <div style="margin-top:16px;">
                <button type="submit" class="btn btn--primary">Agihkan Kepada Peguam Dipilih</button>
            </div>
        </form>
    @endif
</div>
@endsection
