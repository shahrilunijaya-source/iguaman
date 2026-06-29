@extends('layouts.staff')

@section('title', $mode === 'create' ? 'Tambah Pegawai' : 'Kemaskini Pegawai')

@php
    $isCreate = $mode === 'create';
    $action = $isCreate ? route('pegawai.store') : route('pegawai.update', $pegawai);
    $val = fn (string $f) => old($f, $pegawai->$f);
@endphp

@section('content')
    <div class="tap-head">
        <div>
            <h1 class="tap-head__title">{{ $isCreate ? 'Tambah Pegawai' : 'Kemaskini Pegawai' }}<span class="dot"></span></h1>
            <p class="tap-head__sub">{{ $isCreate ? 'Daftar pegawai JBG baharu.' : $pegawai->nama }}</p>
        </div>
        <div class="tap-head__cluster">
            <a href="{{ route('pegawai.index') }}" class="tap-head__btn">Batal</a>
        </div>
    </div>

    @if ($errors->any())
        <div class="formerr" style="margin-bottom:16px;">{{ $errors->first() }}</div>
    @endif

    <form method="POST" action="{{ $action }}">
        @csrf
        @unless ($isCreate) @method('PUT') @endunless

        <div class="tap-card" style="margin-bottom:18px;">
            <div class="tap-card__eyebrow">Maklumat Pegawai</div>
            <div class="wiz-grid">
                <div class="wiz-field wiz-field--span-2">
                    <label class="wiz-field__label">Nama *</label>
                    <input class="wiz-field__input" name="nama" value="{{ $val('nama') }}" maxlength="50" required>
                    @error('nama') <div class="wiz-field__hint" style="color:var(--danger)">{{ $message }}</div> @enderror
                </div>
                <div class="wiz-field">
                    <label class="wiz-field__label">Jawatan</label>
                    <input class="wiz-field__input" name="jawatan" value="{{ $val('jawatan') }}" maxlength="50">
                </div>
                <div class="wiz-field">
                    <label class="wiz-field__label">Bahagian</label>
                    <input class="wiz-field__input" name="bahagian" value="{{ $val('bahagian') }}" maxlength="50">
                </div>
                <div class="wiz-field">
                    <label class="wiz-field__label">Cawangan</label>
                    <input class="wiz-field__input" name="cawangan" value="{{ $val('cawangan') }}" maxlength="50">
                </div>
                <div class="wiz-field">
                    <label class="wiz-field__label">Jenis Pegawai</label>
                    <input class="wiz-field__input" name="jenis_pegawai" value="{{ $val('jenis_pegawai') }}" maxlength="255">
                </div>
                <div class="wiz-field">
                    <label class="wiz-field__label">Status</label>
                    <select class="wiz-field__select" name="status_aktif">
                        <option value="1" @selected((string) $val('status_aktif') === '1' || $val('status_aktif') === null)>Aktif</option>
                        <option value="0" @selected((string) $val('status_aktif') === '0')>Tidak Aktif</option>
                    </select>
                </div>
            </div>
        </div>

        <div style="display:flex; gap:10px; justify-content:flex-end; align-items:center;">
            <a href="{{ route('pegawai.index') }}" class="btn btn--ghost">Batal</a>
            <button type="submit" class="btn btn--primary">{{ $isCreate ? 'Tambah' : 'Simpan' }}</button>
        </div>
    </form>

    @unless ($isCreate)
        {{-- Separate form — never nest a delete form inside the edit form. --}}
        <form method="POST" action="{{ route('pegawai.destroy', $pegawai) }}" onsubmit="return confirm('Padam pegawai ini?')" style="margin-top:14px;">
            @csrf @method('DELETE')
            <button type="submit" class="btn btn--ghost" style="color:var(--danger);">Padam Pegawai</button>
        </form>
    @endunless
@endsection
