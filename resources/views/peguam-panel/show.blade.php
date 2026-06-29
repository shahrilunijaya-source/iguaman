@extends('layouts.staff')

@section('title', 'Peguam · '.$peguam->nama_peguam)

@section('content')
    <div class="tap-nav" style="margin: -24px -20px 18px; border-radius: 0;">
        <a href="{{ route('agihan.beban') }}" class="tap-nav__back">← Beban Tugas</a>
        <span class="tap-nav__crumb">{{ $peguam->nama_peguam }}</span>
        <div class="tap-nav__cluster">
            <a href="{{ route('peguam-panel.edit', $peguam) }}" class="tap-head__btn">✎ Kemaskini</a>
        </div>
    </div>

    <div class="tap-title" style="border:1px solid var(--line); border-radius: var(--r-lg); margin-bottom: 18px;">
        <div>
            <h1 class="tap-title__h1">{{ $peguam->nama_peguam }}<span class="dot"></span></h1>
            <p class="tap-title__sub">No. KP <strong>{{ $peguam->kp_peguam ?: '—' }}</strong> · {{ $peguam->nama_firma ?: '—' }}</p>
            <div class="tap-title__chips">
                <span class="tap-title__chip">{{ $peguam->tel_peguam ?: '—' }}</span>
                <span class="tap-title__chip">{{ $peguam->emel_peguam ?: '—' }}</span>
                <span class="tap-title__chip">{{ $kes->count() }} kes ditugaskan</span>
                <span class="tap-title__chip" style="{{ $peguam->isAktif() ? 'color:var(--success);' : 'color:#dc2626;' }}">{{ $peguam->isAktif() ? 'AKTIF' : 'TIDAK AKTIF' }}</span>
            </div>
        </div>
    </div>

    @if (session('status'))
        <div class="formerr" style="color:var(--success);background:rgba(16,185,129,.08);border-color:rgba(16,185,129,.18);margin-bottom:16px;">{{ session('status') }}</div>
    @endif
    @if ($errors->any())
        <div class="formerr" style="margin-bottom:16px;">{{ $errors->first() }}</div>
    @endif

    @if (auth()->user()->hasRole('admin', 'koordinator', 'pengarah', 'ketua_pengarah'))
        <div class="tap-card" style="margin-bottom:18px;">
            <div class="tap-card__eyebrow">Status Keaktifan Peguam</div>
            @if ($peguam->isAktif())
                <p class="tap-title__sub" style="margin:6px 0 12px;">Peguam ini <strong style="color:var(--success);">AKTIF</strong>. Menyahaktifkan akan mengembalikan semua kes aktif beliau untuk agihan semula.</p>
                <form method="POST" action="{{ route('peguam-panel.nyahaktif', $peguam) }}" onsubmit="return confirm('Sahkan nyahaktif peguam ini? Semua kes aktif akan diagih semula.');">
                    @csrf
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
                        <div class="field">
                            <label class="field__label">Sebab Nyahaktif</label>
                            <select name="sebab" class="field__input" required onchange="document.getElementById('sebabLainWrap').style.display=this.value==='{{ \App\Models\PeguamPanel::SEBAB_LAIN }}'?'block':'none';">
                                <option value="" disabled selected>Pilih sebab…</option>
                                @foreach (\App\Models\PeguamPanel::SEBAB_LIST as $s)
                                    <option value="{{ $s }}">{{ $s }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="field" id="sebabLainWrap" style="display:none;">
                            <label class="field__label">Nyatakan (jika Lain-lain)</label>
                            <input type="text" name="sebabLain" class="field__input" maxlength="200">
                        </div>
                    </div>
                    <button type="submit" class="btn btn--ghost" style="margin-top:12px;color:#dc2626;border-color:#dc2626;">Nyahaktif Peguam</button>
                </form>
            @else
                <p class="tap-title__sub" style="margin:6px 0 12px;">Peguam ini <strong style="color:#dc2626;">TIDAK AKTIF</strong> sejak {{ optional($peguam->tarikhTidakAktif)->format('d/m/Y') ?: '—' }} — {{ $peguam->sebabTidakAktif ?: '—' }}.</p>
                <form method="POST" action="{{ route('peguam-panel.aktif', $peguam) }}">
                    @csrf
                    <button type="submit" class="btn btn--primary">Aktifkan Semula</button>
                </form>
            @endif
        </div>
    @endif

    @include('peguam-panel._butiran', ['b' => $b])

    <div class="tap-card" style="margin-top:18px;">
        <div class="tap-card__eyebrow">Kes Ditugaskan ({{ $kes->count() }})</div>
        @forelse ($kes as $k)
            <a href="{{ route('kes.show', $k->id) }}" class="tap-card__row" style="text-decoration:none;">
                <div class="k">{{ $k->no_fail ?: '#'.$k->id }} · {{ $k->nama ?: 'Tanpa Nama' }}</div>
                <div class="v">{{ $k->kategori_kes ?: '—' }} · {{ $k->status ?: 'baru' }}</div>
            </a>
        @empty
            <div class="dash-empty__sub" style="padding:6px 0;">Tiada kes ditugaskan.</div>
        @endforelse
    </div>
@endsection
