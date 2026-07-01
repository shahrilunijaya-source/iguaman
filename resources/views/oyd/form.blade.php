@extends('layouts.staff')

@section('title', $mode === 'create' ? 'Tambah OYD' : 'Kemaskini OYD')

@php
    $isCreate = $mode === 'create';
    $action = $isCreate ? route('oyd.store') : route('oyd.update', $oyd);
    $val = fn (string $f) => old($f, $oyd->$f);
@endphp

@section('content')
    <div class="tap-head">
        <div>
            <h1 class="tap-head__title">{{ $isCreate ? 'Tambah OYD' : 'Kemaskini OYD' }}<span class="dot"></span></h1>
            <p class="tap-head__sub">{{ $isCreate ? 'Daftar rekod Orang Yang Dibantu baharu.' : ($oyd->nama_oyd.' · '.$oyd->kp_oyd) }}</p>
        </div>
        <div class="tap-head__cluster">
            <a href="{{ $isCreate ? route('oyd.index') : route('oyd.show', $oyd) }}" class="tap-head__btn">Batal</a>
        </div>
    </div>

    @if ($errors->any())
        <div class="formerr" style="margin-bottom:16px;">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M12 8v4"/><path d="M12 16h.01"/></svg>
            {{ $errors->first() }}
        </div>
    @endif

    <form method="POST" action="{{ $action }}">
        @csrf
        @unless ($isCreate) @method('PUT') @endunless

        {{-- Identiti --}}
        <div class="tap-card" style="margin-bottom:18px;">
            <div class="tap-card__eyebrow">Identiti</div>
            <div class="wiz-grid">
                <div class="wiz-field wiz-field--span-2">
                    <label class="wiz-field__label">Nama OYD *</label>
                    <input class="wiz-field__input" name="nama_oyd" value="{{ $val('nama_oyd') }}" required>
                    @error('nama_oyd') <div class="wiz-field__hint" style="color:var(--danger)">{{ $message }}</div> @enderror
                </div>
                <div class="wiz-field">
                    <label class="wiz-field__label">No. KP *</label>
                    <input class="wiz-field__input" name="kp_oyd" value="{{ $val('kp_oyd') }}" maxlength="12" required>
                    @error('kp_oyd') <div class="wiz-field__hint" style="color:var(--danger)">{{ $message }}</div> @enderror
                </div>
                <div class="wiz-field">
                    <label class="wiz-field__label">Umur</label>
                    <input type="number" class="wiz-field__input" name="umur_oyd" value="{{ $val('umur_oyd') }}" min="0" max="150">
                </div>
                <div class="wiz-field">
                    <label class="wiz-field__label">Jantina</label>
                    <select class="wiz-field__select" name="jantina_oyd">
                        <option value="">-</option>
                        @foreach (['Lelaki', 'Perempuan'] as $opt)
                            <option value="{{ $opt }}" @selected($val('jantina_oyd') === $opt)>{{ $opt }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="wiz-field">
                    <label class="wiz-field__label">Agama</label>
                    <input class="wiz-field__input" name="agama_oyd" value="{{ $val('agama_oyd') }}" maxlength="20">
                </div>
                <div class="wiz-field">
                    <label class="wiz-field__label">Agama (Lain)</label>
                    <input class="wiz-field__input" name="agamaLain_oyd" value="{{ $val('agamaLain_oyd') }}" maxlength="20">
                </div>
                <div class="wiz-field">
                    <label class="wiz-field__label">OKU</label>
                    <select class="wiz-field__select" name="oku_oyd">
                        <option value="">-</option>
                        @foreach (['Ya', 'Tidak'] as $opt)
                            <option value="{{ $opt }}" @selected($val('oku_oyd') === $opt)>{{ $opt }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="wiz-field">
                    <label class="wiz-field__label">Bangsa</label>
                    <input class="wiz-field__input" name="bangsa_oyd" value="{{ $val('bangsa_oyd') }}" maxlength="50">
                </div>
                <div class="wiz-field">
                    <label class="wiz-field__label">Etnik</label>
                    <input class="wiz-field__input" name="etnik_oyd" value="{{ $val('etnik_oyd') }}" maxlength="50">
                </div>
            </div>
        </div>

        {{-- Alamat --}}
        <div class="tap-card" style="margin-bottom:18px;">
            <div class="tap-card__eyebrow">Alamat</div>
            <div class="wiz-grid">
                <div class="wiz-field wiz-field--span-2">
                    <label class="wiz-field__label">Alamat 1</label>
                    <input class="wiz-field__input" name="alamat_oyd1" value="{{ $val('alamat_oyd1') }}" maxlength="255">
                </div>
                <div class="wiz-field wiz-field--span-2">
                    <label class="wiz-field__label">Alamat 2</label>
                    <input class="wiz-field__input" name="alamat_oyd2" value="{{ $val('alamat_oyd2') }}" maxlength="255">
                </div>
                <div class="wiz-field">
                    <label class="wiz-field__label">Alamat 3</label>
                    <input class="wiz-field__input" name="alamat_oyd3" value="{{ $val('alamat_oyd3') }}" maxlength="100">
                </div>
                <div class="wiz-field">
                    <label class="wiz-field__label">Poskod</label>
                    <input class="wiz-field__input" name="poskod_oyd" value="{{ $val('poskod_oyd') }}" maxlength="10">
                </div>
                <div class="wiz-field">
                    <label class="wiz-field__label">Bandar</label>
                    <input class="wiz-field__input" name="bandar_oyd" value="{{ $val('bandar_oyd') }}" maxlength="100">
                </div>
                <div class="wiz-field">
                    <label class="wiz-field__label">Negeri</label>
                    <input class="wiz-field__input" name="negeri_oyd" value="{{ $val('negeri_oyd') }}" maxlength="100">
                </div>
            </div>
        </div>

        {{-- Hubungan --}}
        <div class="tap-card" style="margin-bottom:18px;">
            <div class="tap-card__eyebrow">Hubungan</div>
            <div class="wiz-grid">
                <div class="wiz-field">
                    <label class="wiz-field__label">No. Telefon</label>
                    <input class="wiz-field__input" name="notelefon_oyd" value="{{ $val('notelefon_oyd') }}" maxlength="20">
                </div>
                <div class="wiz-field">
                    <label class="wiz-field__label">Emel</label>
                    <input type="email" class="wiz-field__input" name="email_oyd" value="{{ $val('email_oyd') }}" maxlength="50">
                    @error('email_oyd') <div class="wiz-field__hint" style="color:var(--danger)">{{ $message }}</div> @enderror
                </div>
            </div>
        </div>

        <div style="display:flex; gap:10px; justify-content:flex-end;">
            <a href="{{ $isCreate ? route('oyd.index') : route('oyd.show', $oyd) }}" class="btn btn--ghost">Batal</a>
            <button type="submit" class="btn btn--primary">{{ $isCreate ? 'Tambah OYD' : 'Simpan Perubahan' }}</button>
        </div>
    </form>
@endsection
