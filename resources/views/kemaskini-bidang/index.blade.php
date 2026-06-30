@extends('layouts.staff')

@section('title', 'Kemaskini Bidang Pengkhususan')

@section('content')
@php
    use App\Models\ButiranPeguamPanel6 as P6;
    $label = [
        P6::DROP_MOHON => 'Mohon Gugur', P6::ADD_MOHON => 'Mohon Tambah',
        P6::DROP_DISOKONG => 'Gugur (disokong Pengarah)', P6::ADD_DISOKONG => 'Tambah (disokong Pengarah)',
    ];
    $user = auth()->user();
@endphp
<style>
    .kb-table { width:100%; border-collapse:collapse; font-size:13px; }
    .kb-table th { text-align:left; padding:10px 12px; border-bottom:2px solid var(--line); color:var(--mute); font-size:12px; text-transform:uppercase; }
    .kb-table td { padding:10px 12px; border-bottom:1px solid var(--line); vertical-align:top; }
    .kb-act { display:flex; gap:6px; flex-wrap:wrap; }
    .kb-badge { display:inline-block; padding:3px 10px; border-radius:999px; background:rgba(26,111,168,.12); color:var(--brand,#1a6fa8); font-size:11px; font-weight:600; }
</style>

<div class="tap-head">
    <div>
        <h1 class="tap-head__title">Kemaskini Bidang Pengkhususan<span class="dot"></span></h1>
        <p class="tap-head__sub">Permohonan tambah / gugur bidang oleh peguam panel</p>
    </div>
</div>

@if (session('status'))
    <div class="formerr" style="color:var(--success);background:rgba(16,185,129,.08);border-color:rgba(16,185,129,.18);margin-bottom:16px;">{{ session('status') }}</div>
@endif
@if ($errors->any())
    <div class="formerr" style="margin-bottom:16px;">{{ $errors->first() }}</div>
@endif

<div class="tap-card">
    @if ($rows->isEmpty())
        <div class="dash-empty__sub" style="padding:12px 0;">Tiada permohonan kemaskini bidang.</div>
    @else
        <table class="kb-table">
            <thead><tr><th>Peguam</th><th>Bidang</th><th>Permohonan</th><th>Tindakan</th></tr></thead>
            <tbody>
                @foreach ($rows as $row)
                    <tr>
                        <td>{{ $names[$row->kpBaru] ?? $row->kpBaru }}</td>
                        <td>{{ $row->category }} — {{ $row->checkbox_value }}</td>
                        <td><span class="kb-badge">{{ $label[$row->checkbox_value_status] ?? '—' }}</span>
                            @if ($row->ulasanPengarah)<div style="font-size:11px;color:var(--mute);margin-top:4px;">Pengarah: {{ $row->ulasanPengarah }}</div>@endif
                        </td>
                        <td>
                            <div class="kb-act">
                                @if (in_array($row->checkbox_value_status, P6::PENGARAH_PENDING, true) && $user->hasAnyRole(['pengarah', 'admin']))
                                    <form method="POST" action="{{ route('kemaskini-bidang.pengarah', $row) }}" style="display:flex;gap:6px;align-items:center;">
                                        @csrf
                                        <input type="hidden" name="keputusan" value="sokong">
                                        <input type="text" name="ulasan" placeholder="Ulasan" maxlength="500" style="font-size:12px;padding:4px 8px;border:1px solid var(--line);border-radius:6px;">
                                        <button class="btn btn--primary" style="padding:3px 10px;font-size:11px;">Sokong</button>
                                    </form>
                                    <form method="POST" action="{{ route('kemaskini-bidang.pengarah', $row) }}">@csrf<input type="hidden" name="keputusan" value="tolak"><button class="btn btn--ghost" style="padding:3px 10px;font-size:11px;">Tolak</button></form>
                                @elseif (in_array($row->checkbox_value_status, P6::KP_PENDING, true) && $user->hasAnyRole(['ketua_pengarah', 'admin']))
                                    <form method="POST" action="{{ route('kemaskini-bidang.kp', $row) }}">@csrf<input type="hidden" name="keputusan" value="lulus"><button class="btn btn--primary" style="padding:3px 10px;font-size:11px;">Luluskan</button></form>
                                    <form method="POST" action="{{ route('kemaskini-bidang.kp', $row) }}">@csrf<input type="hidden" name="keputusan" value="tolak"><button class="btn btn--ghost" style="padding:3px 10px;font-size:11px;">Tolak</button></form>
                                @else
                                    <span style="font-size:11px;color:var(--mute);">Menunggu peringkat seterusnya</span>
                                @endif
                            </div>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
        <div style="margin-top:16px;">{{ $rows->links() }}</div>
    @endif
</div>
@endsection
