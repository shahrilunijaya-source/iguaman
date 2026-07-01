@extends('layouts.staff')

@section('title', 'Permohonan Tarik Diri')

@section('content')
<style>
    .td-table { width:100%; border-collapse:collapse; font-size:13px; }
    .td-table th { text-align:left; padding:10px 12px; border-bottom:2px solid var(--line); color:var(--mute); font-size:12px; text-transform:uppercase; }
    .td-table td { padding:10px 12px; border-bottom:1px solid var(--line); }
    .td-status { display:inline-block; padding:3px 10px; border-radius:999px; background:rgba(220,38,38,.1); color:#dc2626; font-size:11px; font-weight:600; }
</style>

<div class="tap-head">
    <div>
        <h1 class="tap-head__title">Permohonan Tarik Diri<span class="dot"></span></h1>
        <p class="tap-head__sub">Tarik Diri Mewakili OYD - semakan PPUU → Pengarah → Ketua Pengarah</p>
    </div>
</div>

<div class="tap-card">
    @if ($kes->isEmpty())
        <div class="dash-empty__sub" style="padding:12px 0;">Tiada permohonan tarik diri dalam proses.</div>
    @else
        <table class="td-table">
            <thead><tr><th>#</th><th>No. Fail</th><th>OYD</th><th>Peguam</th><th>Status</th><th></th></tr></thead>
            <tbody>
                @foreach ($kes as $k)
                    <tr>
                        <td>{{ $k->id }}</td>
                        <td>{{ $k->no_fail ?: '-' }}</td>
                        <td>{{ $k->nama ?: '-' }}</td>
                        <td>{{ $k->nama_pegawai_yang_dapat_kes ?: '-' }}</td>
                        <td><span class="td-status">{{ \App\Support\StatusAgihan::label($k->status_agihan) }}</span></td>
                        <td><a href="{{ route('tarikdiri.maklumat', $k) }}" class="btn btn--ghost" style="padding:4px 12px;font-size:12px;">Semak →</a></td>
                    </tr>
                @endforeach
            </tbody>
        </table>
        <div style="margin-top:16px;">{{ $kes->links() }}</div>
    @endif
</div>
@endsection
