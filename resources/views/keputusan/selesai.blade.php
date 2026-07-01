@extends('layouts.staff')

@section('title', 'Pengesahan Selesai')

@section('content')
<style>
    .ks-table { width:100%; border-collapse:collapse; font-size:13px; }
    .ks-table th { text-align:left; padding:10px 12px; border-bottom:2px solid var(--line); color:var(--mute); font-size:12px; text-transform:uppercase; letter-spacing:.03em; }
    .ks-table td { padding:10px 12px; border-bottom:1px solid var(--line); vertical-align:top; }
    .ks-actions { display:flex; gap:8px; flex-wrap:wrap; align-items:center; justify-content:flex-end; }
    .ks-reject { display:flex; gap:6px; align-items:center; }
    .ks-reject input { max-width:150px; }
</style>

<div class="tap-head">
    <div>
        <h1 class="tap-head__title">Pengesahan Selesai<span class="dot"></span></h1>
        <p class="tap-head__sub">Kes yang ditandakan selesai oleh peguam panel - menunggu pengesahan &amp; penutupan fail JBG</p>
    </div>
</div>

@if (session('status'))
    <div class="formerr" style="color: var(--success); background: rgba(16,185,129,0.08); border-color: rgba(16,185,129,0.18); margin-bottom:14px;">{{ session('status') }}</div>
@endif
@if ($errors->any())
    <div class="formerr" style="margin-bottom:14px;">{{ $errors->first() }}</div>
@endif

<div class="tap-card">
    @if ($kes->isEmpty())
        <div class="dash-empty__sub" style="padding:12px 0;">Tiada kes menunggu pengesahan selesai.</div>
    @else
        <div class="table-scroll">
        <table class="ks-table">
            <thead>
                <tr>
                    <th>#</th><th>No. Fail</th><th>OYD</th><th>Cawangan</th><th>Peguam</th><th>Tarikh Selesai</th><th></th>
                </tr>
            </thead>
            <tbody>
                @foreach ($kes as $k)
                    <tr>
                        <td><a href="{{ route('kes.show', $k) }}">{{ $k->id }}</a></td>
                        <td>{{ $k->no_fail ?: '-' }}</td>
                        <td>{{ $k->nama ?: '-' }}</td>
                        <td>{{ $k->cawangan ?: '-' }}</td>
                        <td>{{ $k->nama_pegawai_yang_dapat_kes ?: '-' }}</td>
                        <td>{{ optional($k->tarikh_selesai)->format('d/m/Y') ?: '-' }}</td>
                        <td>
                            <div class="ks-actions">
                                <form method="POST" action="{{ route('keputusan.kes.sahkan-selesai', $k) }}" onsubmit="return confirm('Sahkan penyelesaian & tutup fail kes ini?')">
                                    @csrf
                                    <button type="submit" class="btn btn--primary" style="padding:4px 12px;font-size:12px;">✓ Sahkan</button>
                                </form>
                                <form method="POST" action="{{ route('keputusan.kes.tolak-selesai', $k) }}" class="ks-reject" onsubmit="return confirm('Kembalikan kes ini kepada peguam?')">
                                    @csrf
                                    <input class="field__input" name="reason" placeholder="Sebab (pilihan)" aria-label="Sebab (pilihan)" maxlength="255">
                                    <button type="submit" class="btn btn--ghost" style="padding:4px 12px;font-size:12px;color:var(--danger);">↩ Tolak</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
        </div>
        <div style="margin-top:16px;">{{ $kes->links() }}</div>
    @endif
</div>
@endsection
