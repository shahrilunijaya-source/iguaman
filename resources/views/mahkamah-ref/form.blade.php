@extends('layouts.staff')

@php
    $label = $jenis === 'syariah' ? 'Syariah' : 'Sivil';
    $isCreate = $mode === 'create';
    $action = $isCreate
        ? route('mahkamah-ref.store', ['jenis' => $jenis])
        : route('mahkamah-ref.update', ['jenis' => $jenis, 'id' => $mahkamah->id]);
    $val = fn (string $f) => old($f, $mahkamah->$f);
@endphp

@section('title', ($isCreate ? 'Tambah Mahkamah ' : 'Kemaskini Mahkamah ') . $label)

@section('content')
    <div class="tap-head">
        <div>
            <h1 class="tap-head__title">{{ $isCreate ? 'Tambah Mahkamah' : 'Kemaskini Mahkamah' }} {{ $label }}<span class="dot"></span></h1>
            <p class="tap-head__sub">{{ $isCreate ? 'Daftar mahkamah ' . $label . ' baharu.' : $mahkamah->nama_mahkamah }}</p>
        </div>
        <div class="tap-head__cluster">
            <a href="{{ route('mahkamah-ref.index', ['jenis' => $jenis]) }}" class="tap-head__btn">Batal</a>
        </div>
    </div>

    @if ($errors->any())
        <div class="formerr" style="margin-bottom:16px;">{{ $errors->first() }}</div>
    @endif

    <form method="POST" action="{{ $action }}">
        @csrf
        @unless ($isCreate) @method('PUT') @endunless

        <div class="tap-card" style="margin-bottom:18px;">
            <div class="tap-card__eyebrow">Maklumat Mahkamah</div>
            <div class="wiz-grid">
                <div class="wiz-field wiz-field--span-2">
                    <label class="wiz-field__label">Nama Mahkamah *</label>
                    <input class="wiz-field__input" name="nama_mahkamah" value="{{ $val('nama_mahkamah') }}" maxlength="70" required>
                    @error('nama_mahkamah') <div class="wiz-field__hint" style="color:var(--danger)">{{ $message }}</div> @enderror
                </div>
                <div class="wiz-field">
                    <label class="wiz-field__label">Negeri *</label>
                    <input class="wiz-field__input" name="negeri_mahkamah" value="{{ $val('negeri_mahkamah') }}" maxlength="70" required>
                    @error('negeri_mahkamah') <div class="wiz-field__hint" style="color:var(--danger)">{{ $message }}</div> @enderror
                </div>
                <div class="wiz-field">
                    <label class="wiz-field__label">Lokaliti *</label>
                    <input class="wiz-field__input" name="lokaliti_mahkamah" value="{{ $val('lokaliti_mahkamah') }}" maxlength="50" required>
                    @error('lokaliti_mahkamah') <div class="wiz-field__hint" style="color:var(--danger)">{{ $message }}</div> @enderror
                </div>
                <div class="wiz-field wiz-field--span-2">
                    <label class="wiz-field__label">Jenis Mahkamah</label>
                    <input class="wiz-field__input" name="jenis_mahkamah" value="{{ $val('jenis_mahkamah') }}" maxlength="100">
                    @error('jenis_mahkamah') <div class="wiz-field__hint" style="color:var(--danger)">{{ $message }}</div> @enderror
                </div>
            </div>
        </div>

        <div style="display:flex; gap:10px; justify-content:flex-end; align-items:center;">
            <a href="{{ route('mahkamah-ref.index', ['jenis' => $jenis]) }}" class="btn btn--ghost">Batal</a>
            <button type="submit" class="btn btn--primary">{{ $isCreate ? 'Tambah' : 'Simpan' }}</button>
        </div>
    </form>

    @unless ($isCreate)
        {{-- Separate form - never nest a delete form inside the edit form. --}}
        <form method="POST" action="{{ route('mahkamah-ref.destroy', ['jenis' => $jenis, 'id' => $mahkamah->id]) }}" onsubmit="return confirm('Padam mahkamah ini?')" style="margin-top:14px;">
            @csrf @method('DELETE')
            <button type="submit" class="btn btn--ghost" style="color:var(--danger);">Padam Mahkamah</button>
        </form>
    @endunless
@endsection
