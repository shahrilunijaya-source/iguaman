@extends('cetakan.layout')

@php
    $interim = $kes->status_perakuan === \App\Support\PerakuanService::STATUS_INTERIM;
    $jenis = $interim ? 'Interim' : 'Muktamad';
@endphp

@section('tajuk', 'Perakuan Bantuan Guaman ('.$jenis.')')
@section('subtajuk', ($kes->no_perakuan ? 'No. Perakuan: '.$kes->no_perakuan : '').($kes->no_fail ? ' · No. Fail: '.$kes->no_fail : ''))

@section('kepala_kanan')
    No. Perakuan: <strong>{{ $kes->no_perakuan ?: '-' }}</strong><br>
    Tarikh: {{ $tarikhCetak }}
@endsection

@php
    $f = fn ($v) => $v instanceof \Illuminate\Support\Carbon ? $v->format('d/m/Y') : (($v === null || $v === '') ? null : $v);

    $butiran = array_filter([
        'Nama Tertuduh (OYD)' => $kes->nama,
        'No. KP' => $kes->nokp,
        'No. Fail' => $kes->no_fail,
        'Cawangan' => $kes->cawangan,
        'No. Pertuduhan' => $kes->no_pertuduhan,
        'Seksyen Kesalahan' => $kes->seksyen_kesalahan,
        'Mahkamah' => $kes->mahkamah_pembelaan,
        'Peguam Panel' => $kes->nama_pegawai_yang_dapat_kes,
        'Status Perakuan' => $kes->status_perakuan,
        'Tarikh Interim' => $f($kes->tarikh_perakuan_interim),
        'Tarikh Muktamad' => $f($kes->tarikh_perakuan_muktamad),
    ], fn ($v) => $v !== null && $v !== '');
@endphp

@section('isi')
    <div class="sec">Butiran Perakuan</div>
    <table class="kv">
        @foreach ($butiran as $label => $value)
            <tr><td class="k">{{ $label }}</td><td class="v">{{ $value }}</td></tr>
        @endforeach
    </table>

    <div class="sec">Pengesahan</div>
    <p class="body">
        Adalah dengan ini diperakukan bahawa
        <strong>{{ $kes->nama ?: 'tertuduh' }}</strong>
        @if ($kes->nokp)(No. KP {{ $kes->nokp }})@endif
        telah <strong>diluluskan bantuan guaman</strong> di bawah skim Pembelaan Awam
        Jabatan Bantuan Guaman bagi pertuduhan
        @if ($kes->no_pertuduhan)<strong>{{ $kes->no_pertuduhan }}</strong>@else jenayah @endif
        @if ($kes->mahkamah_pembelaan) di {{ $kes->mahkamah_pembelaan }}@endif.
    </p>
    <p class="body">
        @if ($interim)
            Perakuan ini dikeluarkan secara <strong>INTERIM</strong> bagi kes segera membolehkan
            perwakilan guaman dimulakan dengan serta-merta, tertakluk kepada pengesahan muktamad
            kemudian pada {{ $f($kes->tarikh_perakuan_interim) ?: $tarikhCetak }}.
        @else
            Perakuan ini adalah <strong>MUKTAMAD</strong> dan sah sebagai pengesahan kelulusan
            bantuan guaman pada {{ $f($kes->tarikh_perakuan_muktamad) ?: $tarikhCetak }}.
        @endif
    </p>
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
