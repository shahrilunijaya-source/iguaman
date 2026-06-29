@extends('layouts.staff')

@section('title', 'Agih Peguam · Kes #'.$kes->id)

@section('content')
    <div class="tap-nav" style="margin: -24px -20px 18px; border-radius: 0;">
        <a href="{{ route('kes.show', $kes) }}" class="tap-nav__back">← Kes {{ $kes->no_fail ?: '#'.$kes->id }}</a>
        <span class="tap-nav__crumb">Agihan Peguam</span>
    </div>

    <div class="tap-head">
        <div>
            <h1 class="tap-head__title">Agih Peguam Panel<span class="dot"></span></h1>
            <p class="tap-head__sub">{{ $kes->nama }} · {{ $kes->no_fail ?: '—' }}</p>
        </div>
    </div>

    <div style="display:grid; grid-template-columns: 1fr 320px; gap:24px; align-items:start;">
        <div class="tap-card">
            <div class="tap-card__eyebrow">Penugasan</div>

            @if ($kes->nama_pegawai_yang_dapat_kes)
                <div class="tap-card__row">
                    <div class="k">Peguam Semasa</div>
                    <div class="v"><strong>{{ $kes->nama_pegawai_yang_dapat_kes }}</strong> · {{ optional($kes->tarikh_penugasan_peguam_panel)->format('d/m/Y') }}</div>
                </div>
            @else
                <div class="dash-empty__sub" style="padding:6px 0 12px;">Belum diagih kepada mana-mana peguam.</div>
            @endif

            <form method="POST" action="{{ route('agihan.store', $kes) }}" style="margin-top:14px;">
                @csrf
                <div class="field" style="margin-bottom:12px;">
                    <label class="field__label">Pilih Peguam Panel *</label>
                    <select name="peguam_id" class="field__input" required>
                        <option value="">— Pilih —</option>
                        @foreach ($peguamList as $p)
                            <option value="{{ $p->id }}">{{ $p->nama_peguam }} @if($p->nama_firma) — {{ $p->nama_firma }} @endif</option>
                        @endforeach
                    </select>
                    @error('peguam_id') <div class="wiz-field__hint" style="color:var(--danger)">{{ $message }}</div> @enderror
                </div>

                @if ($kes->nama_pegawai_yang_dapat_kes)
                    <div class="field" style="margin-bottom:12px;">
                        <label class="field__label">Alasan Agihan Semula</label>
                        <input class="field__input" name="alasan" maxlength="255" value="{{ old('alasan') }}">
                    </div>
                @endif

                <button type="submit" class="btn btn--primary">{{ $kes->nama_pegawai_yang_dapat_kes ? 'Agih Semula' : 'Agih Peguam' }}</button>
            </form>
        </div>

        <div class="rail-card">
            <div class="rail-card__head"><span class="rail-card__eyebrow">Sejarah Agihan</span></div>
            <div class="audit-list">
                @forelse ($sejarah as $h)
                    <div class="audit-row">
                        <div class="audit-row__dot"></div>
                        <div class="audit-row__body"><strong>{{ $h->nama_pp_lama ?: '—' }}</strong><br><small style="color:var(--mute)">{{ $h->alasan ?: $h->status_agihan }} · {{ $h->modifiedBy }}</small></div>
                        <div class="audit-row__when">{{ optional($h->modifiedDate)->format('d/m/y') ?: optional($h->tarikh_penugasan)->format('d/m/y') }}</div>
                    </div>
                @empty
                    <div class="dash-empty__sub">Tiada sejarah agihan.</div>
                @endforelse
            </div>
        </div>
    </div>
@endsection
