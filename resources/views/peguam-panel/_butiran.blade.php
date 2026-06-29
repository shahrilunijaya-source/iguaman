{{-- Detailed lawyer profile (butiran_peguam_panel). Param: $b (ButiranPeguamPanel|null). --}}
@php
    $d = function ($v) {
        if ($v instanceof \Illuminate\Support\Carbon) return $v->format('d/m/Y');
        return ($v === null || $v === '' || $v === '0000-00-00') ? '—' : $v;
    };
@endphp

@if ($b)
    <div style="display:grid; grid-template-columns: 1fr 1fr; gap:18px;">
        <div class="tap-card">
            <div class="tap-card__eyebrow">Kelayakan</div>
            <div class="tap-card__row"><div class="k">Kelulusan Akademik</div><div class="v">{{ $b->kelulusanAkademik ?: '—' }}</div></div>
            <div class="tap-card__row"><div class="k">Diterima Masuk</div><div class="v">{{ $d($b->tarikhDiterimaMasuk) }}</div></div>
            <div class="tap-card__row"><div class="k">Pengalaman</div><div class="v">{{ $b->tahunPengalaman ?: '0' }} tahun · {{ $b->bilanganKes ?: '0' }} kes</div></div>
            <div class="tap-card__row"><div class="k">Kategori</div><div class="v">{{ $b->category ?: '—' }}</div></div>
            @if ($b->keteranganKes)<div class="tap-card__row"><div class="k">Keterangan</div><div class="v">{{ $b->keteranganKes }}</div></div>@endif
        </div>

        <div class="tap-card">
            <div class="tap-card__eyebrow">Tauliah & Lesen</div>
            <div class="tap-card__row"><div class="k">No. CLP</div><div class="v">{{ $b->clpNumber ?: '—' }}</div></div>
            <div class="tap-card__row"><div class="k">CLP Sah</div><div class="v">{{ $d($b->clpMula) }} — {{ $d($b->clpAkhir) }}</div></div>
            @foreach ([1, 2, 3] as $i)
                @php $no = $b->{'csoNumber'.$i}; @endphp
                @if ($no)
                    <div class="tap-card__row"><div class="k">CSO {{ $i }}</div><div class="v">{{ $no }} · {{ $d($b->{'cso'.$i.'Mula'}) }}–{{ $d($b->{'cso'.$i.'Akhir'}) }}</div></div>
                @endif
            @endforeach
            <div class="tap-card__row"><div class="k">Lokasi Berguam</div><div class="v">{{ collect([$b->lokasiBerguam1, $b->lokasiBerguam2, $b->lokasiBerguam3])->filter()->implode(', ') ?: '—' }}</div></div>
        </div>

        <div class="tap-card">
            <div class="tap-card__eyebrow">Firma</div>
            <div class="tap-card__row"><div class="k">Nama Firma</div><div class="v">{{ $b->namaFirma ?: '—' }}</div></div>
            <div class="tap-card__row"><div class="k">Alamat</div><div class="v">{{ collect([$b->alamatFirma1, $b->alamatFirma2, $b->alamatFirma3, trim(($b->poskodFirma ?? '').' '.($b->bandarFirma ?? '')), $b->negeriFirma])->filter()->implode(', ') ?: '—' }}</div></div>
            <div class="tap-card__row"><div class="k">Telefon / Faks</div><div class="v">{{ $b->noTelFirma ?: '—' }} {{ $b->noFaksFirma ? '· '.$b->noFaksFirma : '' }}</div></div>
        </div>

        <div class="tap-card">
            <div class="tap-card__eyebrow">Insurans & Bank</div>
            <div class="tap-card__row"><div class="k">Insurans</div><div class="v">{{ $b->namaInsurans ?: '—' }} {{ $b->noPolisi ? '('.$b->noPolisi.')' : '' }}</div></div>
            <div class="tap-card__row"><div class="k">Perlindungan</div><div class="v">{{ $b->amaunPerlindungan ?: '—' }} · {{ $d($b->polisiMula) }}–{{ $d($b->polisiAkhir) }}</div></div>
            <div class="tap-card__row"><div class="k">Bank</div><div class="v">{{ $b->namaBank ?: '—' }}</div></div>
            <div class="tap-card__row"><div class="k">No. Akaun</div><div class="v">{{ $b->noAkaunBank ?: '—' }}</div></div>
        </div>
    </div>
@else
    <div class="tap-card">
        <div class="dash-empty__sub" style="padding:8px 0;">Tiada rekod profil terperinci (butiran_peguam_panel) untuk peguam ini.</div>
    </div>
@endif
