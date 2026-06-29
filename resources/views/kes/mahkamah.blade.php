@extends('layouts.staff')

@section('title', 'Kes Mahkamah · Kes #'.$kes->id)

@php
    $val = function (string $field, ?string $fmt = null) use ($kes) {
        if ($fmt === 'date') return old($field, optional($kes->$field)->format('Y-m-d'));
        return old($field, $kes->$field);
    };
@endphp

@section('content')
    <div class="tap-nav" style="margin: -24px -20px 18px; border-radius: 0;">
        <a href="{{ route('kes.show', $kes) }}" class="tap-nav__back">← Kes {{ $kes->no_fail ?: '#'.$kes->id }}</a>
        <span class="tap-nav__crumb">Kes Mahkamah</span>
    </div>

    @if (session('status'))
        <div class="formerr" style="color: var(--success); background: rgba(16,185,129,0.08); border-color: rgba(16,185,129,0.18); margin-bottom:14px;">{{ session('status') }}</div>
    @endif

    <div class="tap-head">
        <div>
            <h1 class="tap-head__title">Kes Mahkamah<span class="dot"></span></h1>
            <p class="tap-head__sub">{{ $kes->nama }} · {{ $kes->no_fail ?: '—' }}</p>
        </div>
    </div>

    <form method="POST" action="{{ route('mahkamah.update', $kes) }}">
        @csrf @method('PUT')
        <div class="tap-card" style="margin-bottom:18px;">
            <div class="tap-card__eyebrow">Maklumat Mahkamah</div>
            <div class="wiz-grid">
                <div class="wiz-field">
                    <label class="wiz-field__label">Nama Pihak</label>
                    <input class="wiz-field__input" name="nama_pihak" value="{{ $val('nama_pihak') }}">
                </div>
                <div class="wiz-field">
                    <label class="wiz-field__label">Nama Responden</label>
                    <input class="wiz-field__input" name="nama_responden" value="{{ $val('nama_responden') }}">
                </div>
                <div class="wiz-field">
                    <label class="wiz-field__label">Nama Mahkamah</label>
                    <input class="wiz-field__input" name="nama_mahkamah" value="{{ $val('nama_mahkamah') }}">
                </div>
                <div class="wiz-field">
                    <label class="wiz-field__label">No. Mahkamah</label>
                    <input class="wiz-field__input" name="no_mahkamah" value="{{ $val('no_mahkamah') }}">
                </div>
                <div class="wiz-field">
                    <label class="wiz-field__label">Pegawai Penyiasat</label>
                    <input class="wiz-field__input" name="nama_pegawai_penyiasat" value="{{ $val('nama_pegawai_penyiasat') }}">
                </div>
                <div class="wiz-field">
                    <label class="wiz-field__label">Keputusan Kendali Kes</label>
                    <input class="wiz-field__input" name="keputusan_kendali_kes" value="{{ $val('keputusan_kendali_kes') }}">
                </div>
                <div class="wiz-field">
                    <label class="wiz-field__label">Tarikh Pemfailan Kes</label>
                    <input type="date" class="wiz-field__input" name="tarikh_pemfailan_kes" value="{{ $val('tarikh_pemfailan_kes', 'date') }}">
                </div>
                <div class="wiz-field">
                    <label class="wiz-field__label">Tarikh Pemfailan</label>
                    <input type="date" class="wiz-field__input" name="tarikh_pemfailan" value="{{ $val('tarikh_pemfailan', 'date') }}">
                </div>
                <div class="wiz-field">
                    <label class="wiz-field__label">Tarikh Perintah</label>
                    <input type="date" class="wiz-field__input" name="tarikh_perintah" value="{{ $val('tarikh_perintah', 'date') }}">
                </div>
                <div class="wiz-field">
                    <label class="wiz-field__label">Tarikh Perintah Bersih</label>
                    <input type="date" class="wiz-field__input" name="tarikh_perintah_bersih" value="{{ $val('tarikh_perintah_bersih', 'date') }}">
                </div>
                <div class="wiz-field">
                    <label class="wiz-field__label">Tarikh Serahan Perintah</label>
                    <input type="date" class="wiz-field__input" name="tarikh_serahan_perintah" value="{{ $val('tarikh_serahan_perintah', 'date') }}">
                </div>
                <div class="wiz-field">
                    <label class="wiz-field__label">Kos</label>
                    <input class="wiz-field__input" name="kos" value="{{ $val('kos') }}">
                </div>
                <div class="wiz-field">
                    <label class="wiz-field__label">Kos OYD (RM)</label>
                    <input type="number" class="wiz-field__input" name="kos_oyd" value="{{ $val('kos_oyd') }}">
                </div>
                <div class="wiz-field">
                    <label class="wiz-field__label">Kos Pihak Lawan (RM)</label>
                    <input type="number" class="wiz-field__input" name="kos_pihak_lawan" value="{{ $val('kos_pihak_lawan') }}">
                </div>
            </div>
        </div>
        <div style="display:flex; gap:10px; justify-content:flex-end; margin-bottom:24px;">
            <a href="{{ route('kes.show', $kes) }}" class="btn btn--ghost">Batal</a>
            <button type="submit" class="btn btn--primary">Simpan Mahkamah</button>
        </div>
    </form>

    {{-- ===== Laporan Kes (court reports) ===== --}}
    <div class="tap-card">
        <div class="tap-card__eyebrow">Laporan Kes ({{ $kes->laporanKes->count() }})</div>

        @forelse ($kes->laporanKes as $lap)
            <div class="tap-card__row" style="grid-template-columns: 1fr auto;">
                <div>
                    <div class="v"><strong>{{ $lap->no_kes ?: 'Laporan #'.$lap->id }}</strong> — {{ $lap->status_kes ?: '—' }}</div>
                    <div class="k" style="text-transform:none; margin-top:2px;">{{ $lap->isu ?: ($lap->pihak_pihak ?: '') }} {{ $lap->tarikh_sebutan ? '· sebutan '.optional($lap->tarikh_sebutan)->format('d/m/Y') : '' }}</div>
                </div>
                <form method="POST" action="{{ route('laporan.destroy', [$kes, $lap]) }}" onsubmit="return confirm('Padam laporan ini?')">
                    @csrf @method('DELETE')
                    <button class="tap-row__cta tap-row__cta--ghost" type="submit">Padam</button>
                </form>
            </div>
        @empty
            <div class="dash-empty__sub" style="padding:8px 0;">Tiada laporan kes lagi.</div>
        @endforelse

        <div style="margin-top:16px; padding-top:16px; border-top:1px solid var(--line);">
            <form method="POST" action="{{ route('laporan.store', $kes) }}">
                @csrf
                <div class="wiz-grid">
                    <div class="wiz-field">
                        <label class="wiz-field__label">No. Kes</label>
                        <input class="wiz-field__input" name="no_kes" value="{{ old('no_kes') }}">
                    </div>
                    <div class="wiz-field">
                        <label class="wiz-field__label">Status Kes</label>
                        <input class="wiz-field__input" name="status_kes" value="{{ old('status_kes') }}">
                    </div>
                    <div class="wiz-field">
                        <label class="wiz-field__label">Pihak-Pihak</label>
                        <input class="wiz-field__input" name="pihak_pihak" value="{{ old('pihak_pihak') }}">
                    </div>
                    <div class="wiz-field">
                        <label class="wiz-field__label">Pegawai</label>
                        <input class="wiz-field__input" name="nama_pegawai" value="{{ old('nama_pegawai') }}">
                    </div>
                    <div class="wiz-field">
                        <label class="wiz-field__label">Tarikh Sebutan</label>
                        <input type="date" class="wiz-field__input" name="tarikh_sebutan" value="{{ old('tarikh_sebutan') }}">
                    </div>
                    <div class="wiz-field">
                        <label class="wiz-field__label">Isu</label>
                        <input class="wiz-field__input" name="isu" value="{{ old('isu') }}">
                    </div>
                    <div class="wiz-field wiz-field--span-2">
                        <label class="wiz-field__label">Fakta Ringkas</label>
                        <textarea class="wiz-field__textarea" name="fakta_ringkas">{{ old('fakta_ringkas') }}</textarea>
                    </div>
                    <div class="wiz-field wiz-field--span-2">
                        <label class="wiz-field__label">Ringkasan</label>
                        <textarea class="wiz-field__textarea" name="ringkasan">{{ old('ringkasan') }}</textarea>
                    </div>
                </div>
                <div style="display:flex; justify-content:flex-end; margin-top:12px;">
                    <button type="submit" class="btn btn--primary">+ Tambah Laporan</button>
                </div>
            </form>
        </div>
    </div>
@endsection
