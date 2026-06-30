@extends('cetakan.layout')

@section('tajuk', 'Surat Penutupan Fail')
@section('subtajuk', ($kes->no_fail ? 'No. Fail: '.$kes->no_fail : 'Kes #'.$kes->id).($kes->no_sistem ? ' · No. Sistem: '.$kes->no_sistem : ''))

@section('kepala_kanan')
    No. Fail: <strong>{{ $kes->no_fail ?: '—' }}</strong><br>
    Tarikh: {{ $tarikhCetak }}
@endsection

@php
    $f = fn ($v) => $v instanceof \Illuminate\Support\Carbon ? $v->format('d/m/Y') : (($v === null || $v === '') ? null : $v);

    $butiran = array_filter([
        'Nama OYD' => $kes->nama,
        'No. KP' => $kes->nokp,
        'Kategori / Jenis Kes' => trim(($kes->kategori_kes ?: '—').' · '.($kes->jenis_kes ?: '—'), ' ·'),
        'Cawangan' => $kes->cawangan,
        'Peguam Panel' => $kes->nama_pegawai_yang_dapat_kes,
        'Tarikh Penugasan Peguam' => $f($kes->tarikh_penugasan_peguam_panel),
        'Tarikh Selesai (Peguam)' => $f($kes->tarikh_selesai),
        'Tarikh Tutup Fail' => $f($kes->tarikh_tutup_fail),
        'Sebab Selesai' => $kes->sebab_selesai,
        'Status Agihan' => \App\Support\StatusAgihan::label($kes->status_agihan),
    ], fn ($v) => $v !== null && $v !== '');
@endphp

@section('isi')
    <div class="sec">Butiran Kes</div>
    <table class="kv">
        @foreach ($butiran as $label => $value)
            <tr><td class="k">{{ $label }}</td><td class="v">{{ $value }}</td></tr>
        @endforeach
    </table>

    <div class="sec">Pengesahan Penutupan</div>
    <p class="body">
        Adalah dengan ini disahkan bahawa fail kes
        <strong>{{ $kes->no_fail ?: '#'.$kes->id }}</strong>
        bagi pihak <strong>{{ $kes->nama ?: 'OYD' }}</strong>
        telah <strong>selesai dikendalikan</strong> oleh peguam panel
        @if ($kes->nama_pegawai_yang_dapat_kes)<strong>{{ $kes->nama_pegawai_yang_dapat_kes }}</strong>@else yang ditugaskan @endif
        dan <strong>ditutup secara rasmi</strong> oleh Jabatan Bantuan Guaman
        pada {{ $f($kes->tarikh_tutup_fail) ?: $tarikhCetak }}.
    </p>
    <p class="body muted">
        Surat ini dijana secara automatik daripada Sistem Integrated Bantuan Guaman dan sah tanpa tandatangan.
    </p>

    <table class="sign">
        <tr>
            <td>
                <div class="line">Pegawai Bertanggungjawab</div>
                {{ $oleh ?? '—' }}<br>
                <span class="muted">Jabatan Bantuan Guaman</span>
            </td>
            <td>
                <div class="line">Cop Rasmi JBG</div>
            </td>
        </tr>
    </table>
@endsection
