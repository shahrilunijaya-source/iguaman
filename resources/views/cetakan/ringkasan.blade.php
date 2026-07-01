@extends('cetakan.layout')

@section('tajuk', 'Ringkasan Kes')
@section('subtajuk', ($kes->no_fail ? 'No. Fail: '.$kes->no_fail : 'Kes #'.$kes->id).($kes->no_sistem ? ' · No. Sistem: '.$kes->no_sistem : ''))

@section('kepala_kanan')
    No. Fail: <strong>{{ $kes->no_fail ?: '-' }}</strong><br>
    Cawangan: {{ $kes->cawangan ?: '-' }}
@endsection

@php
    $f = fn ($v) => $v instanceof \Illuminate\Support\Carbon ? $v->format('d/m/Y') : (($v === null || $v === '') ? null : $v);
    // UX-11: monetary fields as RM with thousands separators, not raw integers.
    $m = fn ($v) => ($v === null || $v === '') ? null : 'RM '.number_format((float) $v, 2);

    $groups = [
        'Maklumat Pemohon' => [
            'Nama' => $kes->nama, 'No. KP' => $kes->nokp, 'Umur' => $kes->umur, 'Jantina' => $kes->jantina,
            'Agama' => $kes->agama, 'Bangsa' => $kes->bangsa, 'Etnik' => $kes->etnik, 'OKU' => $kes->oku,
            'Penjaga' => $kes->nama_penjaga, 'No. KP Penjaga' => $kes->nokp_penjaga,
        ],
        'Permohonan & Pendaftaran' => [
            'Tarikh Khidmat Nasihat' => $f($kes->tarikh_khidmat_nasihat), 'Tarikh Permohonan' => $f($kes->tarikh_permohonan),
            'Tarikh Daftar' => $f($kes->tarikh_daftar), 'Kategori Kes' => $kes->kategori_kes,
            'Jenis Kategori' => $kes->jenis_kategori, 'Jenis Kes' => $kes->jenis_kes, 'Jenis Jenayah' => $kes->jenis_jenayah,
            'Taraf' => $kes->taraf, 'Pegawai' => $kes->nama_pegawai, 'Didaftarkan Oleh' => $kes->didaftarkan_oleh,
        ],
        'Keputusan' => [
            'Keputusan' => $kes->keputusan, 'Diterima' => $kes->diterima, 'Kelulusan' => $kes->kelulusan,
            'Keputusan Menteri' => $kes->keputusan_menteri, 'Tarikh Perakuan' => $f($kes->tarikh_perakuan),
            'Tarikh Pemakluman' => $f($kes->tarikh_pemakluman), 'Sumbangan' => $kes->sumbangan, 'Nilai Sumbangan' => $m($kes->nilai_sumbangan),
        ],
        'Agihan & Peguam Panel' => [
            'Agih Kepada' => $kes->agih_kepada, 'Status Agihan' => $kes->status_agihan,
            'Peguam Dapat Kes' => $kes->nama_pegawai_yang_dapat_kes, 'Tarikh Penugasan Peguam' => $f($kes->tarikh_penugasan_peguam_panel),
        ],
        'Pengantaraan' => [
            'Status Pengantaraan' => $kes->status_pengantaraan, 'Tarikh Penugasan' => $f($kes->tarikh_penugasan),
            'Kaedah Sidang' => $kes->kaedah_sidang, 'Tarikh Sidang' => $f($kes->tarikh_sidang),
            'Status Sidang' => $kes->status_sidang, 'Cara Selesai' => $kes->cara_selesai,
        ],
        'Mahkamah' => [
            'Nama Pihak' => $kes->nama_pihak, 'Nama Responden' => $kes->nama_responden, 'Nama Mahkamah' => $kes->nama_mahkamah,
            'No. Mahkamah' => $kes->no_mahkamah, 'Tarikh Pemfailan' => $f($kes->tarikh_pemfailan_kes),
            'Keputusan Kendali Kes' => $kes->keputusan_kendali_kes, 'Tarikh Perintah' => $f($kes->tarikh_perintah),
        ],
        'Penutupan & Kos' => [
            'Status' => $kes->status, 'Tarikh Selesai' => $f($kes->tarikh_selesai), 'Sebab Selesai' => $kes->sebab_selesai,
            'Tarikh Tutup Fail' => $f($kes->tarikh_tutup_fail), 'Kos' => $m($kes->kos), 'Kos OYD' => $m($kes->kos_oyd),
            'Kos Pihak Lawan' => $m($kes->kos_pihak_lawan),
        ],
    ];
@endphp

@section('isi')
    @foreach ($groups as $title => $fields)
        @php $rows = array_filter($fields, fn ($v) => $v !== null && $v !== ''); @endphp
        @if (count($rows))
            <div class="sec">{{ $title }}</div>
            <table class="kv">
                @foreach ($rows as $label => $value)
                    <tr><td class="k">{{ $label }}</td><td class="v">{{ $value }}</td></tr>
                @endforeach
            </table>
        @endif
    @endforeach

    @if ($kes->laporanKes->count())
        <div class="sec">Laporan Kes Mahkamah ({{ $kes->laporanKes->count() }})</div>
        <table class="grid">
            <tr><th>No. Kes</th><th>Pihak</th><th>Tarikh Sebutan</th><th>Status</th></tr>
            @foreach ($kes->laporanKes as $lap)
                <tr>
                    <td>{{ $lap->no_kes ?: $lap->no_fail ?: '-' }}</td>
                    <td>{{ $lap->pihak_pihak ?: '-' }}</td>
                    <td>{{ optional($lap->tarikh_sebutan)->format('d/m/Y') ?: '-' }}</td>
                    <td>{{ $lap->status_kes ?: '-' }}</td>
                </tr>
            @endforeach
        </table>
    @endif
@endsection
