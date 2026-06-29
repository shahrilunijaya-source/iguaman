@extends('layouts.staff')

@section('title', 'Kemaskini Peguam · '.$peguam->nama_peguam)

@php $val = fn (string $f) => old($f, $peguam->$f); @endphp

@section('content')
    <div class="tap-head">
        <div>
            <h1 class="tap-head__title">Kemaskini Peguam Panel<span class="dot"></span></h1>
            <p class="tap-head__sub">{{ $peguam->nama_peguam }}</p>
        </div>
        <div class="tap-head__cluster">
            <a href="{{ route('peguam-panel.show', $peguam) }}" class="tap-head__btn">Batal</a>
        </div>
    </div>

    @if ($errors->any())
        <div class="formerr" style="margin-bottom:16px;">{{ $errors->first() }}</div>
    @endif

    <form method="POST" action="{{ route('peguam-panel.update', $peguam) }}">
        @csrf @method('PUT')

        <div class="tap-card" style="margin-bottom:18px;">
            <div class="tap-card__eyebrow">Maklumat Peguam</div>
            <div class="wiz-grid">
                <div class="wiz-field wiz-field--span-2">
                    <label class="wiz-field__label">Nama Peguam *</label>
                    <input class="wiz-field__input" name="nama_peguam" value="{{ $val('nama_peguam') }}" maxlength="150" required>
                    @error('nama_peguam') <div class="wiz-field__hint" style="color:var(--danger)">{{ $message }}</div> @enderror
                </div>
                <div class="wiz-field">
                    <label class="wiz-field__label">No. KP *</label>
                    <input class="wiz-field__input" name="kp_peguam" value="{{ $val('kp_peguam') }}" maxlength="20" required>
                </div>
                <div class="wiz-field">
                    <label class="wiz-field__label">Telefon</label>
                    <input class="wiz-field__input" name="tel_peguam" value="{{ $val('tel_peguam') }}" maxlength="20">
                </div>
                <div class="wiz-field wiz-field--span-2">
                    <label class="wiz-field__label">Emel</label>
                    <input type="email" class="wiz-field__input" name="emel_peguam" value="{{ $val('emel_peguam') }}" maxlength="255">
                    @error('emel_peguam') <div class="wiz-field__hint" style="color:var(--danger)">{{ $message }}</div> @enderror
                </div>
            </div>
        </div>

        <div class="tap-card" style="margin-bottom:18px;">
            <div class="tap-card__eyebrow">Firma</div>
            <div class="wiz-grid">
                <div class="wiz-field wiz-field--span-2">
                    <label class="wiz-field__label">Nama Firma</label>
                    <input class="wiz-field__input" name="nama_firma" value="{{ $val('nama_firma') }}" maxlength="255">
                </div>
                <div class="wiz-field wiz-field--span-2">
                    <label class="wiz-field__label">Alamat 1</label>
                    <input class="wiz-field__input" name="alamat_firma_1" value="{{ $val('alamat_firma_1') }}" maxlength="255">
                </div>
                <div class="wiz-field">
                    <label class="wiz-field__label">Alamat 2</label>
                    <input class="wiz-field__input" name="alamat_firma_2" value="{{ $val('alamat_firma_2') }}" maxlength="255">
                </div>
                <div class="wiz-field">
                    <label class="wiz-field__label">Alamat 3</label>
                    <input class="wiz-field__input" name="alamat_firma_3" value="{{ $val('alamat_firma_3') }}" maxlength="255">
                </div>
                <div class="wiz-field">
                    <label class="wiz-field__label">Poskod</label>
                    <input class="wiz-field__input" name="poskod_firma" value="{{ $val('poskod_firma') }}" maxlength="10">
                </div>
                <div class="wiz-field">
                    <label class="wiz-field__label">Negeri</label>
                    <input class="wiz-field__input" name="negeri_firma" value="{{ $val('negeri_firma') }}" maxlength="100">
                </div>
                <div class="wiz-field">
                    <label class="wiz-field__label">Telefon Firma</label>
                    <input class="wiz-field__input" name="tel_firma" value="{{ $val('tel_firma') }}" maxlength="20">
                </div>
            </div>
        </div>

        <div style="display:flex; gap:10px; justify-content:flex-end;">
            <a href="{{ route('peguam-panel.show', $peguam) }}" class="btn btn--ghost">Batal</a>
            <button type="submit" class="btn btn--primary">Simpan</button>
        </div>
    </form>
@endsection
