@extends('cetakan.layout')

@section('tajuk', 'Surat Pembatalan Permohonan')
@section('subtajuk', ($kes->no_fail ? 'No. Fail: '.$kes->no_fail : 'Kes #'.$kes->id))

@section('kepala_kanan')
    No. Fail: <strong>{{ $kes->no_fail ?: '-' }}</strong><br>
    Tarikh: {{ $tarikhCetak }}
@endsection

@php
    $f = fn ($v) => $v instanceof \Illuminate\Support\Carbon ? $v->format('d/m/Y') : (($v === null || $v === '') ? null : $v);
    $sebab = $kes->reason ?: ($kes->sebab_tutup_fail ?: null);

    $butiran = array_filter([
        'Nama Pemohon' => $kes->nama,
        'No. KP' => $kes->nokp,
        'Kategori / Jenis Kes' => trim(($kes->kategori_kes ?: '-').' · '.($kes->jenis_kes ?: '-'), ' ·'),
        'Cawangan' => $kes->cawangan,
        'Tarikh Permohonan' => $f($kes->tarikh_permohonan),
        'Keputusan' => $kes->keputusan,
        'Status' => $kes->status,
    ], fn ($v) => $v !== null && $v !== '');
@endphp

@section('isi')
    <div class="sec">Butiran Permohonan</div>
    <table class="kv">
        @foreach ($butiran as $label => $value)
            <tr><td class="k">{{ $label }}</td><td class="v">{{ $value }}</td></tr>
        @endforeach
    </table>

    <div class="sec">Pembatalan</div>
    <p class="body">
        Dengan hormatnya dimaklumkan bahawa permohonan bantuan guaman bagi pihak
        <strong>{{ $kes->nama ?: 'pemohon' }}</strong>
        @if ($kes->no_fail)(No. Fail {{ $kes->no_fail }})@endif
        telah <strong>tidak diluluskan / dibatalkan</strong> oleh Jabatan Bantuan Guaman.
    </p>
    @if ($sebab)
        <p class="body"><strong>Sebab:</strong> {{ $sebab }}</p>
    @endif
    <p class="body muted">
        Surat ini dijana secara automatik daripada Sistem Integrated Bantuan Guaman dan sah tanpa tandatangan.
    </p>

    <table class="sign">
        <tr>
            <td>
                <div class="line">Pegawai Bertanggungjawab</div>
                {{ $oleh ?? '-' }}<br>
                <span class="muted">Jabatan Bantuan Guaman</span>
            </td>
            <td>
                <div class="line">Cop Rasmi JBG</div>
            </td>
        </tr>
    </table>
@endsection
