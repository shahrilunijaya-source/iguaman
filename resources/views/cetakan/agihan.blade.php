@extends('cetakan.layout')

@section('tajuk', 'Surat Penugasan Peguam Panel')
@section('subtajuk', $kes->no_fail ? 'No. Fail: '.$kes->no_fail : 'Kes #'.$kes->id)

@section('kepala_kanan')
    Tarikh: <strong>{{ $tarikhCetak }}</strong>
@endsection

@section('isi')
    <table class="kv" style="margin-bottom: 14px;">
        <tr><td class="k">Kepada (Peguam Panel)</td><td class="v">{{ $kes->nama_pegawai_yang_dapat_kes ?: '—' }}</td></tr>
        @if ($peguam)
            <tr><td class="k">Firma</td><td class="v">{{ $peguam->nama_firma ?: '—' }}</td></tr>
            <tr><td class="k">Alamat Firma</td><td class="v">{{ trim(collect([$peguam->alamat_firma_1, $peguam->alamat_firma_2, $peguam->alamat_firma_3, $peguam->poskod_firma, $peguam->negeri_firma])->filter()->implode(', ')) ?: '—' }}</td></tr>
            <tr><td class="k">No. Telefon</td><td class="v">{{ $peguam->tel_peguam ?: ($peguam->tel_firma ?: '—') }}</td></tr>
            <tr><td class="k">Emel</td><td class="v">{{ $peguam->emel_peguam ?: '—' }}</td></tr>
        @endif
        <tr><td class="k">Tarikh Penugasan</td><td class="v">{{ optional($kes->tarikh_penugasan_peguam_panel)->format('d/m/Y') ?: $tarikhCetak }}</td></tr>
    </table>

    <p class="body">Tuan/Puan,</p>
    <p class="body"><strong>PENUGASAN KES BANTUAN GUAMAN</strong></p>
    <p class="body">
        Dengan segala hormatnya merujuk kepada perkara di atas. Sukacita dimaklumkan bahawa tuan/puan
        telah ditugaskan untuk mengendalikan kes Bantuan Guaman seperti butiran di bawah:
    </p>

    <div class="sec">Butiran Kes</div>
    <table class="kv">
        <tr><td class="k">Nama Pemohon (OYD)</td><td class="v">{{ $kes->nama ?: '—' }}</td></tr>
        <tr><td class="k">No. KP</td><td class="v">{{ $kes->nokp ?: '—' }}</td></tr>
        <tr><td class="k">No. Fail</td><td class="v">{{ $kes->no_fail ?: '—' }}</td></tr>
        <tr><td class="k">Kategori / Jenis Kes</td><td class="v">{{ trim(collect([$kes->kategori_kes, $kes->jenis_kes, $kes->jenis_jenayah])->filter()->implode(' · ')) ?: '—' }}</td></tr>
        <tr><td class="k">Cawangan</td><td class="v">{{ $kes->cawangan ?: '—' }}</td></tr>
        @if ($kes->nama_mahkamah)<tr><td class="k">Mahkamah</td><td class="v">{{ $kes->nama_mahkamah }} {{ $kes->no_mahkamah ? '('.$kes->no_mahkamah.')' : '' }}</td></tr>@endif
    </table>

    <p class="body" style="margin-top: 12px;">
        Tuan/puan adalah diminta untuk mengambil tindakan sewajarnya dan melaporkan perkembangan kes ini
        kepada pihak Jabatan dari semasa ke semasa. Kerjasama tuan/puan amatlah dihargai.
    </p>
    <p class="body">Sekian, terima kasih.</p>
    <p class="body"><strong>"BERKHIDMAT UNTUK NEGARA"</strong></p>

    <table class="sign">
        <tr>
            <td>
                Yang menjalankan tugas,
                <div class="line">{{ $oleh ?? '' }}</div>
                Pegawai Bantuan Guaman<br>{{ $kes->cawangan ?: '' }}
            </td>
            <td></td>
        </tr>
    </table>
@endsection
