@extends('layouts.peguam')

@section('title', 'Kes · '.($kes->no_fail ?: '#'.$kes->id))

@section('content')
    <div class="tap-nav" style="margin: -24px -20px 18px; border-radius: 0;">
        <a href="{{ route('peguam.kes') }}" class="tap-nav__back">← Kes Saya</a>
        <span class="tap-nav__crumb">{{ $kes->no_fail ?: '#'.$kes->id }}</span>
        <div class="tap-nav__cluster">
            <span class="tap-nav__step">{{ $kes->status ?: 'baru' }}</span>
            @if (\App\Support\StatusAgihan::normalise($kes->status_agihan) === \App\Support\StatusAgihan::DITERIMA)
                <a href="{{ route('peguam.tarikdiri.form', $kes) }}" class="tap-head__btn">Tarik Diri</a>
            @endif
        </div>
    </div>

    @if (session('status'))
        <div class="formerr" style="color: var(--success); background: rgba(16,185,129,0.08); border-color: rgba(16,185,129,0.18); margin-bottom:14px;">{{ session('status') }}</div>
    @endif
    @if ($errors->any())
        <div class="formerr" style="margin-bottom:14px;">{{ $errors->first() }}</div>
    @endif

    <div style="display:grid; grid-template-columns: 1fr 360px; gap:24px; align-items:start;">
        <div style="display:flex; flex-direction:column; gap:18px;">
            <div class="tap-card">
                <div class="tap-card__eyebrow">Maklumat Kes</div>
                <div class="tap-card__row"><div class="k">Pemohon (OYD)</div><div class="v">{{ $kes->nama ?: '—' }}</div></div>
                <div class="tap-card__row"><div class="k">No. KP</div><div class="v">{{ $kes->nokp ?: '—' }}</div></div>
                <div class="tap-card__row"><div class="k">Kategori / Jenis</div><div class="v">{{ $kes->kategori_kes ?: '—' }} · {{ $kes->jenis_kes ?: '—' }}</div></div>
                <div class="tap-card__row"><div class="k">Mahkamah</div><div class="v">{{ $kes->nama_mahkamah ?: '—' }} {{ $kes->no_mahkamah ? '('.$kes->no_mahkamah.')' : '' }}</div></div>
                <div class="tap-card__row"><div class="k">Responden</div><div class="v">{{ $kes->nama_responden ?: '—' }}</div></div>
            </div>

            <div class="tap-card">
                <div class="tap-card__eyebrow">Laporan Kes ({{ $kes->laporanKes->count() }})</div>
                @forelse ($kes->laporanKes->sortByDesc('id') as $lap)
                    <div class="tap-card__row">
                        <div class="k">{{ $lap->no_kes ?: $lap->tarikh_sebutan }}</div>
                        <div class="v">{{ $lap->status_kes ?: '—' }}{{ $lap->isu ? ' · '.$lap->isu : '' }}<br><small style="color:var(--mute)">{{ optional($lap->tarikh_sebutan)->format('d/m/Y') }} · {{ $lap->nama_pegawai }}</small></div>
                    </div>
                @empty
                    <div class="dash-empty__sub" style="padding:6px 0;">Belum ada laporan.</div>
                @endforelse
            </div>
        </div>

        <div style="display:flex; flex-direction:column; gap:18px;">
            <div class="tap-card">
                <div class="tap-card__eyebrow">Rekod Laporan Baharu</div>
                <form method="POST" action="{{ route('peguam.laporan', $kes) }}" class="va-form">
                    @csrf
                    <input class="field__input" name="no_kes" placeholder="No. Kes Mahkamah">
                    <input class="field__input" name="pihak_pihak" placeholder="Pihak-pihak">
                    <input type="date" class="field__input" name="tarikh_sebutan">
                    <input class="field__input" name="isu" placeholder="Isu">
                    <input class="field__input" name="status_kes" placeholder="Status kes">
                    <textarea class="field__input" name="fakta_ringkas" rows="2" placeholder="Fakta ringkas"></textarea>
                    <textarea class="field__input" name="ringkasan" rows="2" placeholder="Ringkasan / perkembangan"></textarea>
                    <button type="submit" class="btn btn--primary btn--block">Rekod Laporan</button>
                </form>
            </div>

            @if (\App\Support\StatusAgihan::normalise($kes->status_agihan) === \App\Support\StatusAgihan::DITERIMA && blank($kes->tarikh_tutup_fail))
                <div class="tap-card" style="border-left:3px solid var(--success, #10b981);">
                    <div class="tap-card__eyebrow">Tandakan Kes Selesai</div>
                    <p class="dash-empty__sub" style="margin:0 0 10px;">Setelah ditandakan selesai, kes akan dihantar kepada JBG untuk pengesahan &amp; penutupan fail.</p>
                    <form method="POST" action="{{ route('peguam.selesai', $kes) }}" class="va-form" onsubmit="return confirm('Tandakan kes ini selesai?')">
                        @csrf
                        <input class="field__input" name="sebab_selesai" placeholder="Sebab / cara selesai (pilihan)" maxlength="50">
                        <button type="submit" class="btn btn--primary btn--block">✓ Tandakan Selesai</button>
                    </form>
                </div>
            @elseif (\App\Support\StatusAgihan::normalise($kes->status_agihan) === \App\Support\StatusAgihan::PP_SELESAI)
                <div class="tap-card" style="border-left:3px solid var(--success, #10b981);">
                    <div class="tap-card__eyebrow">Status Penyelesaian</div>
                    <p class="dash-empty__sub" style="margin:0;">Kes telah ditandakan selesai@if ($kes->tarikh_selesai) pada {{ optional($kes->tarikh_selesai)->format('d/m/Y') }}@endif. Menunggu pengesahan JBG.</p>
                </div>
            @endif
        </div>
    </div>
@endsection
