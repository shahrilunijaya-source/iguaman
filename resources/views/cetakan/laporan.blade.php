@extends('cetakan.layout')

@section('tajuk', 'Laporan Kes Mahkamah')
@section('subtajuk', ($kes->nama ?: 'Tanpa Nama').' · '.($kes->no_fail ? 'No. Fail '.$kes->no_fail : 'Kes #'.$kes->id))

@section('kepala_kanan')
    No. Fail: <strong>{{ $kes->no_fail ?: '—' }}</strong><br>
    {{ $kes->nama_mahkamah ?: '' }}
@endsection

@section('isi')
    <div class="sec">Maklumat Kes</div>
    <table class="kv">
        <tr><td class="k">Pemohon (OYD)</td><td class="v">{{ $kes->nama ?: '—' }}</td></tr>
        <tr><td class="k">Responden</td><td class="v">{{ $kes->nama_responden ?: '—' }}</td></tr>
        <tr><td class="k">Mahkamah</td><td class="v">{{ $kes->nama_mahkamah ?: '—' }} {{ $kes->no_mahkamah ? '('.$kes->no_mahkamah.')' : '' }}</td></tr>
        <tr><td class="k">Peguam Panel</td><td class="v">{{ $kes->nama_pegawai_yang_dapat_kes ?: '—' }}</td></tr>
    </table>

    @if ($kes->laporanKes->count())
        @foreach ($kes->laporanKes as $i => $lap)
            <div class="sec">Laporan {{ $i + 1 }} — {{ $lap->no_kes ?: $lap->no_fail ?: '—' }}</div>
            <table class="kv">
                <tr><td class="k">Pihak-Pihak</td><td class="v">{{ $lap->pihak_pihak ?: '—' }}</td></tr>
                <tr><td class="k">No. Kes / No. Fail</td><td class="v">{{ $lap->no_kes ?: '—' }} {{ $lap->no_fail ? '/ '.$lap->no_fail : '' }}</td></tr>
                <tr><td class="k">Pegawai</td><td class="v">{{ $lap->nama_pegawai ?: '—' }}</td></tr>
                <tr><td class="k">Tarikh Sebutan</td><td class="v">{{ optional($lap->tarikh_sebutan)->format('d/m/Y') ?: '—' }}</td></tr>
                <tr><td class="k">Isu</td><td class="v">{{ $lap->isu ?: '—' }}</td></tr>
                <tr><td class="k">Status Kes</td><td class="v">{{ $lap->status_kes ?: '—' }}</td></tr>
                @if ($lap->fakta_ringkas)<tr><td class="k">Fakta Ringkas</td><td class="v">{{ $lap->fakta_ringkas }}</td></tr>@endif
                @if ($lap->ringkasan)<tr><td class="k">Ringkasan</td><td class="v">{{ $lap->ringkasan }}</td></tr>@endif
            </table>
        @endforeach
    @else
        <p class="body muted" style="margin-top: 16px;">Tiada laporan kes mahkamah direkodkan untuk kes ini.</p>
    @endif
@endsection
