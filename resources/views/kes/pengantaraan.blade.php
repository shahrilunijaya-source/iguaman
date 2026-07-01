@extends('layouts.staff')

@section('title', 'Pengantaraan · Kes #'.$kes->id)

@php
    $val = function (string $field, ?string $fmt = null) use ($kes) {
        if ($fmt === 'date') return old($field, optional($kes->$field)->format('Y-m-d'));
        return old($field, $kes->$field);
    };
@endphp

@section('content')
    <div class="tap-nav" style="margin: -24px -20px 18px; border-radius: 0;">
        <a href="{{ route('kes.show', $kes) }}" class="tap-nav__back">← Kes {{ $kes->no_fail ?: '#'.$kes->id }}</a>
        <span class="tap-nav__crumb">Pengantaraan</span>
    </div>

    @if (session('status'))
        <div class="formerr" style="color: var(--success); background: rgba(16,185,129,0.08); border-color: rgba(16,185,129,0.18); margin-bottom:14px;">{{ session('status') }}</div>
    @endif

    <div class="tap-head">
        <div>
            <h1 class="tap-head__title">Pengantaraan<span class="dot"></span></h1>
            <p class="tap-head__sub">{{ $kes->nama }} · {{ $kes->nokp ?: '-' }}</p>
        </div>
    </div>

    <div style="display:grid; grid-template-columns: 1fr 320px; gap: 24px; align-items:start;">
        <div>
            <form method="POST" action="{{ route('pengantaraan.update', $kes) }}">
                @csrf @method('PUT')
                <div class="tap-card" style="margin-bottom:18px;">
                    <div class="tap-card__eyebrow">Maklumat Pengantaraan</div>
                    <div class="wiz-grid">
                        <div class="wiz-field">
                            <label class="wiz-field__label">Status Pengantaraan</label>
                            <input class="wiz-field__input" name="status_pengantaraan" value="{{ $val('status_pengantaraan') }}">
                        </div>
                        <div class="wiz-field">
                            <label class="wiz-field__label">Kategori Kes</label>
                            <input class="wiz-field__input" name="pengantaraan_kategori_kes" value="{{ $val('pengantaraan_kategori_kes') }}">
                        </div>
                        <div class="wiz-field">
                            <label class="wiz-field__label">Pegawai Pengantara</label>
                            <input class="wiz-field__input" name="nama_pegawai" value="{{ $val('nama_pegawai') }}">
                        </div>
                        <div class="wiz-field">
                            <label class="wiz-field__label">Tarikh Penugasan</label>
                            <input type="date" class="wiz-field__input" name="tarikh_penugasan" value="{{ $val('tarikh_penugasan', 'date') }}">
                        </div>
                        <div class="wiz-field">
                            <label class="wiz-field__label">Kaedah Sidang</label>
                            <input class="wiz-field__input" name="kaedah_sidang" value="{{ $val('kaedah_sidang') }}">
                        </div>
                        <div class="wiz-field">
                            <label class="wiz-field__label">Lokasi Pihak Pertama</label>
                            <input class="wiz-field__input" name="lokasi_pihak_pertama" value="{{ $val('lokasi_pihak_pertama') }}">
                        </div>
                        <div class="wiz-field">
                            <label class="wiz-field__label">Lokasi Pihak Kedua</label>
                            <input class="wiz-field__input" name="lokasi_pihak_kedua" value="{{ $val('lokasi_pihak_kedua') }}">
                        </div>
                        <div class="wiz-field">
                            <label class="wiz-field__label">Lokasi Pegawai Pengantara</label>
                            <input class="wiz-field__input" name="lokasi_pegawai_pengantara" value="{{ $val('lokasi_pegawai_pengantara') }}">
                        </div>
                        <div class="wiz-field">
                            <label class="wiz-field__label">Setuju Pengantara</label>
                            <input class="wiz-field__input" name="setuju_pengantara" value="{{ $val('setuju_pengantara') }}">
                        </div>
                        <div class="wiz-field">
                            <label class="wiz-field__label">Tarikh Persetujuan</label>
                            <input type="date" class="wiz-field__input" name="tarikh_persetujuan" value="{{ $val('tarikh_persetujuan', 'date') }}">
                        </div>
                        <div class="wiz-field">
                            <label class="wiz-field__label">Tarikh Persetujuan Pengantaraan</label>
                            <input type="date" class="wiz-field__input" name="tarikh_persetujuan_pengantaraan" value="{{ $val('tarikh_persetujuan_pengantaraan', 'date') }}">
                        </div>
                        <div class="wiz-field">
                            <label class="wiz-field__label">Tarikh Sidang</label>
                            <input type="date" class="wiz-field__input" name="tarikh_sidang" value="{{ $val('tarikh_sidang', 'date') }}">
                        </div>
                        <div class="wiz-field">
                            <label class="wiz-field__label">Status Sidang</label>
                            <input class="wiz-field__input" name="status_sidang" value="{{ $val('status_sidang') }}">
                        </div>
                        <div class="wiz-field">
                            <label class="wiz-field__label">Cara Selesai</label>
                            <input class="wiz-field__input" name="cara_selesai" value="{{ $val('cara_selesai') }}">
                        </div>
                    </div>
                </div>
                <div style="display:flex; gap:10px; justify-content:flex-end;">
                    <a href="{{ route('kes.show', $kes) }}" class="btn btn--ghost">Batal</a>
                    <button type="submit" class="btn btn--primary">Simpan Pengantaraan</button>
                </div>
            </form>
        </div>

        <div style="display:flex; flex-direction:column; gap:14px;">
            <div class="tap-card">
                <div class="tap-card__eyebrow">Agihan Pengantara</div>
                <div class="tap-card__row"><div class="k">No. Pengantaraan</div><div class="v">{{ $kes->no_pengantaraan ?: '-' }}</div></div>
                <div class="tap-card__row"><div class="k">Sumber</div><div class="v">{{ $kes->sumber_pengantaraan ?: '-' }}</div></div>
                <div class="tap-card__row"><div class="k">Pegawai Pengantara</div><div class="v">{{ $kes->nama_pegawai_pengantara ?: 'Belum diagih' }}</div></div>
                @if ($kes->tarikh_agih_pengantara)
                    <div class="tap-card__row"><div class="k">Tarikh Agih</div><div class="v">{{ optional($kes->tarikh_agih_pengantara)->format('d/m/Y') }}</div></div>
                @endif
                @can('pengantaraan.agih')
                    <form method="POST" action="{{ route('pengantaraan.agih', $kes) }}" style="margin-top:10px;" onsubmit="return confirm('Tetapkan pegawai pengantara ini?')">
                        @csrf
                        <div class="field">
                            <label class="field__label">Pegawai Pengantara</label>
                            <select class="field__input" name="id_pegawai_pengantara" required>
                                <option value="">- Pilih pegawai -</option>
                                @foreach ($pegawaiList as $id => $nama)
                                    <option value="{{ $id }}" @selected($kes->id_pegawai_pengantara == $id)>{{ $nama }}</option>
                                @endforeach
                            </select>
                        </div>
                        <button type="submit" class="btn btn--primary btn--block">Agih Pengantara</button>
                    </form>
                @endcan
            </div>

            <div class="tap-card">
                <div class="tap-card__eyebrow">Tangguh Sidang</div>
                <form method="POST" action="{{ route('sidang.tangguh', $kes) }}" class="va-form">
                    @csrf
                    <div class="field">
                        <label class="field__label">Tarikh Sidang Baharu *</label>
                        <input type="date" class="field__input" name="tarikh_sidang" required>
                        @error('tarikh_sidang') <div class="wiz-field__hint" style="color:var(--danger)">{{ $message }}</div> @enderror
                    </div>
                    <div class="field">
                        <label class="field__label">Alasan Tangguh</label>
                        <input class="field__input" name="alasan_tangguh" maxlength="50">
                    </div>
                    <button type="submit" class="btn btn--primary btn--block">Rekod Tangguh</button>
                </form>
            </div>

            <div class="rail-card">
                <div class="rail-card__head"><span class="rail-card__eyebrow">Sejarah Sidang</span></div>
                <div class="audit-list">
                    @forelse ($kes->sejarahSidang->sortByDesc('tarikh_sidang') as $h)
                        <div class="audit-row">
                            <div class="audit-row__dot"></div>
                            <div class="audit-row__body">{{ $h->alasan_tangguh ?: 'Sidang' }}<br><small style="color:var(--mute)">{{ $h->dikemaskini_oleh }}</small></div>
                            <div class="audit-row__when">{{ optional($h->tarikh_sidang)->format('d/m/y') }}</div>
                        </div>
                    @empty
                        <div class="dash-empty__sub">Tiada rekod sidang.</div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
@endsection
