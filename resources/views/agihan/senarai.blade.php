@extends('layouts.staff')

@section('title', 'Agihan · '.$title)

@section('content')
<style>
    .ag-tabs { display:flex; gap:8px; margin-bottom:18px; flex-wrap:wrap; }
    .ag-tab { padding:8px 16px; border-radius:999px; border:1px solid var(--line); font-size:13px; font-weight:600; text-decoration:none; color:var(--text); }
    .ag-tab.is-active { background:var(--brand,#00B8A9); color:#fff; border-color:var(--brand,#00B8A9); }
    .ag-table { width:100%; border-collapse:collapse; font-size:13px; }
    .ag-table th { text-align:left; padding:10px 12px; border-bottom:2px solid var(--line); color:var(--mute); font-size:12px; text-transform:uppercase; letter-spacing:.03em; }
    .ag-table td { padding:10px 12px; border-bottom:1px solid var(--line); }
    .ag-status { display:inline-block; padding:3px 10px; border-radius:999px; background:rgba(0,184,169,.12); color:var(--brand,#00B8A9); font-size:11px; font-weight:600; }
</style>

<div class="tap-head">
    <div>
        <h1 class="tap-head__title">Agihan Kes<span class="dot"></span></h1>
        <p class="tap-head__sub">Aliran agihan berperingkat (PPUU → Pengarah → Ketua Pengarah)</p>
    </div>
</div>

<div class="ag-tabs">
    @foreach ($buckets as $key => [$codes, $label])
        <a href="{{ route('agihan.senarai', $key) }}" class="ag-tab {{ $bucket === $key ? 'is-active' : '' }}">{{ $label }}</a>
    @endforeach
</div>

<div class="tap-card">
    @if ($kes->isEmpty())
        <div class="dash-empty__sub" style="padding:12px 0;">Tiada kes dalam kategori {{ $title }}.</div>
    @else
        <table class="ag-table">
            <thead>
                <tr>
                    <th>#</th><th>No. Fail</th><th>OYD</th><th>Cawangan</th><th>Status Agihan</th><th>Peguam</th><th></th>
                </tr>
            </thead>
            <tbody>
                @foreach ($kes as $k)
                    <tr>
                        <td>{{ $k->id }}</td>
                        <td>{{ $k->no_fail ?: '—' }}</td>
                        <td>{{ $k->nama ?: '—' }}</td>
                        <td>{{ $k->cawangan ?: '—' }}</td>
                        <td><span class="ag-status">{{ \App\Support\StatusAgihan::label($k->status_agihan) }}</span></td>
                        <td>{{ $k->nama_pegawai_yang_dapat_kes ?: '—' }}</td>
                        <td><a href="{{ route('agihan.maklumat', $k) }}" class="btn btn--ghost" style="padding:4px 12px;font-size:12px;">Tindakan →</a></td>
                    </tr>
                @endforeach
            </tbody>
        </table>
        <div style="margin-top:16px;">{{ $kes->links() }}</div>
    @endif
</div>
@endsection
