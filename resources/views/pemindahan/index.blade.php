@extends('layouts.staff')

@section('title', 'Pemindahan Cawangan')

@section('content')
<style>
    .pc-table { width:100%; border-collapse:collapse; font-size:13px; }
    .pc-table th { text-align:left; padding:10px 12px; border-bottom:2px solid var(--line); color:var(--mute); font-size:12px; text-transform:uppercase; letter-spacing:.03em; }
    .pc-table td { padding:10px 12px; border-bottom:1px solid var(--line); vertical-align:top; }
    .pc-pill { display:inline-block; padding:3px 10px; border-radius:999px; font-size:11px; font-weight:600; }
    .pc-pill--dipindah { background:rgba(0,184,169,.12); color:var(--brand,#00B8A9); }
    .pc-pill--diterima { background:rgba(16,185,129,.12); color:#10b981; }
    .pc-pill--ditolak { background:rgba(239,68,68,.10); color:var(--danger,#ef4444); }
    .pc-flow { white-space:nowrap; }
    .pc-act { display:flex; gap:6px; flex-wrap:wrap; align-items:flex-start; }
    .pc-reject summary { cursor:pointer; color:var(--danger); font-size:12px; padding:4px 0; }
    .pc-reject textarea { width:100%; margin:6px 0; }
</style>

@php
    $pill = [
        \App\Models\PemindahanCawangan::STATUS_DIPINDAH => ['Menunggu Terima', 'pc-pill--dipindah'],
        \App\Models\PemindahanCawangan::STATUS_DITERIMA => ['Diterima', 'pc-pill--diterima'],
        \App\Models\PemindahanCawangan::STATUS_DITOLAK => ['Ditolak', 'pc-pill--ditolak'],
    ];
@endphp

<div class="tap-head">
    <div>
        <h1 class="tap-head__title">Pemindahan Cawangan<span class="dot"></span></h1>
        <p class="tap-head__sub">Kes &amp; Khidmat Nasihat yang dipindahkan masuk/keluar cawangan anda. Sahkan terima atau tolak (rekod ditolak dikembalikan ke cawangan asal).</p>
    </div>
</div>

@if (session('status'))
    <div class="formerr" style="color:var(--success); background:rgba(16,185,129,0.08); border-color:rgba(16,185,129,0.18); margin-bottom:14px;">{{ session('status') }}</div>
@endif
@if (session('error'))
    <div class="formerr" style="margin-bottom:14px;">{{ session('error') }}</div>
@endif

<div class="tap-card">
    @if ($pindahan->isEmpty())
        <div class="dash-empty__sub" style="padding:12px 0;">Tiada pemindahan cawangan.</div>
    @else
        <table class="pc-table">
            <thead>
                <tr>
                    <th>Jenis</th><th>Rekod</th><th>Pemindahan</th><th>Sebab</th><th>Status</th><th>Tarikh</th><th></th>
                </tr>
            </thead>
            <tbody>
                @foreach ($pindahan as $p)
                    @php [$txt, $cls] = $pill[$p->status] ?? [$p->status, 'pc-pill--dipindah']; @endphp
                    <tr>
                        <td>{{ $p->isKes() ? 'Kes' : 'Khidmat Nasihat' }}</td>
                        <td>
                            @if ($p->isKes())
                                <a href="{{ route('kes.show', $p->id_rekod) }}">#{{ $p->id_rekod }}</a>
                            @else
                                @can('khidmat.view')<a href="{{ route('khidmat.show', $p->id_rekod) }}">#{{ $p->id_rekod }}</a>@else#{{ $p->id_rekod }}@endcan
                            @endif
                        </td>
                        <td class="pc-flow">{{ $p->cawangan_asal ?: '—' }} → <strong>{{ $p->cawangan_tujuan ?: '—' }}</strong></td>
                        <td>
                            {{ $p->sebab ?: '—' }}
                            @if ($p->sebab_tolak)
                                <div style="color:var(--danger); font-size:12px; margin-top:4px;">Tolak: {{ $p->sebab_tolak }}</div>
                            @endif
                        </td>
                        <td><span class="pc-pill {{ $cls }}">{{ $txt }}</span></td>
                        <td>{{ optional($p->tarikh_pindah)->format('d/m/Y') }}</td>
                        <td>
                            @if ($p->boleh_act ?? false)
                                <div class="pc-act">
                                    <form method="POST" action="{{ route('pemindahan.terima', $p) }}" onsubmit="return confirm('Sahkan terima pemindahan ini?')">
                                        @csrf
                                        <button type="submit" class="btn btn--primary" style="padding:4px 12px;font-size:12px;">Terima</button>
                                    </form>
                                    <details class="pc-reject">
                                        <summary>Tolak</summary>
                                        <form method="POST" action="{{ route('pemindahan.tolak', $p) }}" onsubmit="return confirm('Tolak pemindahan ini? Rekod akan dikembalikan ke cawangan asal.')">
                                            @csrf
                                            <textarea name="sebab_tolak" class="field__input" rows="2" maxlength="1000" required placeholder="Sebab penolakan"></textarea>
                                            <button type="submit" class="btn btn--ghost" style="padding:4px 12px;font-size:12px;color:var(--danger);">Sahkan Tolak</button>
                                        </form>
                                    </details>
                                </div>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
        <div style="margin-top:16px;">{{ $pindahan->links() }}</div>
    @endif
</div>
@endsection
