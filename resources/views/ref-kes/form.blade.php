@extends('layouts.staff')

@section('title', $mode === 'create' ? 'Tambah Jenis Kes' : 'Kemaskini Jenis Kes')

@php
    $isCreate = $mode === 'create';
    $action = $isCreate ? route('ref-kes.store') : route('ref-kes.update', $refKes);
    $val = fn (string $f) => old($f, $refKes->$f);
    $tarikh = old('tarikh_kuatkuasa', optional($refKes->tarikh_kuatkuasa)->format('Y-m-d'));
@endphp

@section('content')
    <div class="tap-head">
        <div>
            <h1 class="tap-head__title">{{ $isCreate ? 'Tambah Jenis Kes' : 'Kemaskini Jenis Kes' }}<span class="dot"></span></h1>
            <p class="tap-head__sub">{{ $isCreate ? 'Daftar jenis kes baharu.' : $refKes->jenis_kes }}</p>
        </div>
        <div class="tap-head__cluster">
            <a href="{{ route('ref-kes.index') }}" class="tap-head__btn">Batal</a>
        </div>
    </div>

    @if ($errors->any())
        <div class="formerr" style="margin-bottom:16px;">{{ $errors->first() }}</div>
    @endif

    <form method="POST" action="{{ $action }}">
        @csrf
        @unless ($isCreate) @method('PUT') @endunless

        <div class="tap-card" style="margin-bottom:18px;">
            <div class="tap-card__eyebrow">Maklumat Jenis Kes</div>
            <div class="wiz-grid">
                <div class="wiz-field">
                    <label class="wiz-field__label">ID Kes *</label>
                    <input class="wiz-field__input" name="id_kes" value="{{ $val('id_kes') }}" maxlength="20" required>
                    @error('id_kes') <div class="wiz-field__hint" style="color:var(--danger)">{{ $message }}</div> @enderror
                </div>
                <div class="wiz-field">
                    <label class="wiz-field__label">Jenis *</label>
                    <input class="wiz-field__input" name="jenis_kes" value="{{ $val('jenis_kes') }}" maxlength="5" required>
                    @error('jenis_kes') <div class="wiz-field__hint" style="color:var(--danger)">{{ $message }}</div> @enderror
                </div>
                <div class="wiz-field">
                    <label class="wiz-field__label">Kategori</label>
                    <input class="wiz-field__input" name="kategori_kes" value="{{ $val('kategori_kes') }}" maxlength="100">
                    @error('kategori_kes') <div class="wiz-field__hint" style="color:var(--danger)">{{ $message }}</div> @enderror
                </div>
                <div class="wiz-field">
                    <label class="wiz-field__label">Tarikh Kuatkuasa</label>
                    <input type="date" class="wiz-field__input" name="tarikh_kuatkuasa" value="{{ $tarikh }}">
                    @error('tarikh_kuatkuasa') <div class="wiz-field__hint" style="color:var(--danger)">{{ $message }}</div> @enderror
                </div>
                <div class="wiz-field wiz-field--span-2">
                    <label class="wiz-field__label">Deskripsi *</label>
                    <input class="wiz-field__input" name="deskripsi" value="{{ $val('deskripsi') }}" maxlength="500" required>
                    @error('deskripsi') <div class="wiz-field__hint" style="color:var(--danger)">{{ $message }}</div> @enderror
                </div>
                <div class="wiz-field">
                    <label class="wiz-field__label">Status</label>
                    <select class="wiz-field__select" name="aktif_kes">
                        <option value="1" @selected((string) $val('aktif_kes') === '1' || $val('aktif_kes') === null)>Aktif</option>
                        <option value="0" @selected((string) $val('aktif_kes') === '0')>Tidak Aktif</option>
                    </select>
                </div>
            </div>
        </div>

        <div style="display:flex; gap:10px; justify-content:flex-end; align-items:center;">
            <a href="{{ route('ref-kes.index') }}" class="btn btn--ghost">Batal</a>
            <button type="submit" class="btn btn--primary">{{ $isCreate ? 'Tambah' : 'Simpan' }}</button>
        </div>
    </form>

    @unless ($isCreate)
        {{-- Separate form — never nest a delete form inside the edit form. --}}
        <form method="POST" action="{{ route('ref-kes.destroy', $refKes) }}" onsubmit="return confirm('Padam jenis kes ini?')" style="margin-top:14px;">
            @csrf @method('DELETE')
            <button type="submit" class="btn btn--ghost" style="color:var(--danger);">Padam Jenis Kes</button>
        </form>
    @endunless
@endsection
