@extends('layouts.staff')

@section('title', 'Kes #'.$kes->id)

@php
    $f = function ($v) {
        if ($v instanceof \Illuminate\Support\Carbon) return $v->format('d/m/Y');
        return ($v === null || $v === '') ? null : $v;
    };

    $groups = [
        'Pemohon' => [
            'Nama' => $kes->nama, 'No. KP' => $kes->nokp, 'Umur' => $kes->umur,
            'Jantina' => $kes->jantina, 'Agama' => $kes->agama, 'Bangsa' => $kes->bangsa,
            'Etnik' => $kes->etnik, 'OKU' => $kes->oku,
            'Penjaga' => $kes->nama_penjaga, 'No. KP Penjaga' => $kes->nokp_penjaga,
        ],
        'Permohonan & Pendaftaran' => [
            'Cawangan' => $kes->cawangan, 'Tarikh Khidmat Nasihat' => $f($kes->tarikh_khidmat_nasihat),
            'Tarikh Permohonan' => $f($kes->tarikh_permohonan), 'Tarikh Daftar' => $f($kes->tarikh_daftar),
            'Kategori Kes' => $kes->kategori_kes, 'Jenis Kategori' => $kes->jenis_kategori,
            'Jenis Kes' => $kes->jenis_kes, 'Jenis Jenayah' => $kes->jenis_jenayah,
            'Taraf' => $kes->taraf, 'No. Fail' => $kes->no_fail, 'No. Sistem' => $kes->no_sistem,
            'Pegawai' => $kes->nama_pegawai, 'Didaftarkan Oleh' => $kes->didaftarkan_oleh,
        ],
        'Keputusan' => [
            'Keputusan' => $kes->keputusan, 'Diterima' => $kes->diterima, 'Kelulusan' => $kes->kelulusan,
            'Keputusan Menteri' => $kes->keputusan_menteri, 'Tarikh Perakuan' => $f($kes->tarikh_perakuan),
            'Tarikh Pemakluman' => $f($kes->tarikh_pemakluman), 'Sumbangan' => $kes->sumbangan,
            'Nilai Sumbangan' => $kes->nilai_sumbangan,
        ],
        'Agihan & Peguam' => [
            'Agih Kepada' => $kes->agih_kepada, 'Status Agihan' => $kes->status_agihan,
            'Pegawai Dapat Kes' => $kes->nama_pegawai_yang_dapat_kes,
            'Tarikh Penugasan Peguam' => $f($kes->tarikh_penugasan_peguam_panel),
        ],
        'Pengantaraan' => [
            'Status Pengantaraan' => $kes->status_pengantaraan, 'Tarikh Penugasan' => $f($kes->tarikh_penugasan),
            'Kaedah Sidang' => $kes->kaedah_sidang, 'Tarikh Sidang' => $f($kes->tarikh_sidang),
            'Status Sidang' => $kes->status_sidang, 'Cara Selesai' => $kes->cara_selesai,
            'Setuju Pengantara' => $kes->setuju_pengantara,
        ],
        'Mahkamah' => [
            'Nama Pihak' => $kes->nama_pihak, 'Nama Responden' => $kes->nama_responden,
            'Nama Mahkamah' => $kes->nama_mahkamah, 'No. Mahkamah' => $kes->no_mahkamah,
            'Tarikh Pemfailan' => $f($kes->tarikh_pemfailan_kes), 'Keputusan Kendali Kes' => $kes->keputusan_kendali_kes,
            'Tarikh Perintah' => $f($kes->tarikh_perintah), 'Tarikh Serahan Perintah' => $f($kes->tarikh_serahan_perintah),
        ],
        'Penutupan & Kos' => [
            'Status' => $kes->status, 'Tarikh Selesai' => $f($kes->tarikh_selesai),
            'Sebab Selesai' => $kes->sebab_selesai, 'Tarikh Tutup Fail' => $f($kes->tarikh_tutup_fail),
            'Sebab Tutup Fail' => $kes->sebab_tutup_fail, 'Kos' => $kes->kos,
            'Kos OYD' => $kes->kos_oyd, 'Kos Pihak Lawan' => $kes->kos_pihak_lawan,
        ],
    ];
@endphp

@section('content')
    <div class="tap-nav" style="margin: -24px -20px 18px; border-radius: 0;">
        <a href="{{ route('kes.index') }}" class="tap-nav__back">← Senarai Kes</a>
        <span class="tap-nav__crumb">Kes <span class="now">{{ $kes->no_fail ?: '#'.$kes->id }}</span></span>
        <div class="tap-nav__cluster">
            <span class="tap-nav__step">{{ $kes->status ?: 'baru' }}</span>
            <a href="{{ route('agihan.form', $kes) }}" class="tap-head__btn">Agih Peguam</a>
            <a href="{{ route('pengantaraan.edit', $kes) }}" class="tap-head__btn">Pengantaraan</a>
            <a href="{{ route('mahkamah.edit', $kes) }}" class="tap-head__btn">Kes Mahkamah</a>
            <a href="{{ route('kes.edit', $kes) }}" class="tap-head__btn">✎ Kemaskini</a>
        </div>
    </div>

    @if (session('status'))
        <div class="formerr" style="color: var(--success); background: rgba(16,185,129,0.08); border-color: rgba(16,185,129,0.18); margin-bottom:14px;">
            {{ session('status') }}
        </div>
    @endif

    <div class="tap-title" style="border:1px solid var(--line); border-radius: var(--r-lg); margin-bottom: 18px;">
        <div>
            <h1 class="tap-title__h1">{{ $kes->nama ?: 'Tanpa Nama' }}<span class="dot"></span></h1>
            <p class="tap-title__sub">No. KP <strong>{{ $kes->nokp ?: '—' }}</strong> · {{ $kes->cawangan ?: '—' }}</p>
            <div class="tap-title__chips">
                @if ($kes->kategori_kes)<span class="tap-title__chip">{{ $kes->kategori_kes }}</span>@endif
                @if ($kes->jenis_kes)<span class="tap-title__chip">{{ $kes->jenis_kes }}</span>@endif
                @if ($kes->no_fail)<span class="tap-title__chip">{{ $kes->no_fail }}</span>@endif
            </div>
        </div>
        <div class="tap-title__meta">
            <div class="due">{{ optional($kes->tarikh_permohonan)->format('d/m/Y') ?: '—' }}</div>
            <div>Tarikh Permohonan</div>
        </div>
    </div>

    <div style="display:grid; grid-template-columns: 1fr 320px; gap: 24px; align-items:start;">
        <div style="display:flex; flex-direction:column; gap:18px; min-width:0;">
            @foreach ($groups as $title => $fields)
                @php $rows = array_filter($fields, fn ($v) => $v !== null && $v !== ''); @endphp
                @if (count($rows))
                    <div class="tap-card">
                        <div class="tap-card__eyebrow">{{ $title }}</div>
                        @foreach ($rows as $label => $value)
                            <div class="tap-card__row">
                                <div class="k">{{ $label }}</div>
                                <div class="v">{{ $value }}</div>
                            </div>
                        @endforeach
                    </div>
                @endif
            @endforeach

            @if ($kes->laporanKes->count())
                <div class="tap-card">
                    <div class="tap-card__eyebrow">Laporan Kes ({{ $kes->laporanKes->count() }})</div>
                    @foreach ($kes->laporanKes as $lap)
                        <div class="tap-card__row">
                            <div class="k">{{ $lap->no_kes ?: $lap->no_fail ?: 'Laporan' }}</div>
                            <div class="v">{{ $lap->status_kes ?: '—' }} · {{ $lap->isu ?: ($lap->pihak_pihak ?: '') }}</div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>

        <div style="display:flex; flex-direction:column; gap:14px;">
            <div class="rail-card">
                <div class="rail-card__head"><span class="rail-card__eyebrow">Sejarah Peguam</span></div>
                <div class="audit-list">
                    @forelse ($kes->sejarahPeguamPanel as $h)
                        <div class="audit-row">
                            <div class="audit-row__dot"></div>
                            <div class="audit-row__body"><strong>{{ $h->nama_pp_lama ?: '—' }}</strong><br>{{ $h->status ?: '' }} {{ $h->alasan ? '· '.$h->alasan : '' }}</div>
                            <div class="audit-row__when">{{ optional($h->tarikh_penugasan)->format('d/m/y') }}</div>
                        </div>
                    @empty
                        <div class="dash-empty__sub">Tiada rekod.</div>
                    @endforelse
                </div>
            </div>

            <div class="rail-card">
                <div class="rail-card__head"><span class="rail-card__eyebrow">Sejarah Sidang</span></div>
                <div class="audit-list">
                    @forelse ($kes->sejarahSidang as $h)
                        <div class="audit-row">
                            <div class="audit-row__dot"></div>
                            <div class="audit-row__body">{{ $h->alasan_tangguh ?: 'Sidang' }}</div>
                            <div class="audit-row__when">{{ optional($h->tarikh_sidang)->format('d/m/y') }}</div>
                        </div>
                    @empty
                        <div class="dash-empty__sub">Tiada rekod.</div>
                    @endforelse
                </div>
            </div>

            <div class="rail-card">
                <div class="rail-card__head"><span class="rail-card__eyebrow">Sejarah Pegawai</span></div>
                <div class="audit-list">
                    @forelse ($kes->sejarahPegawai as $h)
                        <div class="audit-row">
                            <div class="audit-row__dot"></div>
                            <div class="audit-row__body"><strong>{{ $h->nama_pegawai_lama ?: '—' }}</strong></div>
                            <div class="audit-row__when">{{ optional($h->tarikh_kemaskini)->format('d/m/y') }}</div>
                        </div>
                    @empty
                        <div class="dash-empty__sub">Tiada rekod.</div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
@endsection
