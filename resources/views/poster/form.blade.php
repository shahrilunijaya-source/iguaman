@extends('layouts.staff')

@section('title', $mode === 'create' ? 'Tambah Poster' : 'Kemaskini Poster')

@php
    $isCreate = $mode === 'create';
    $action = $isCreate ? route('poster.store') : route('poster.update', $poster);
    $val = fn (string $f) => old($f, $poster->$f);
@endphp

@section('content')
    <div class="tap-head">
        <div>
            <h1 class="tap-head__title">{{ $isCreate ? 'Tambah Poster' : 'Kemaskini Poster' }}<span class="dot"></span></h1>
            <p class="tap-head__sub">{{ $isCreate ? 'Daftar e-Poster baharu.' : $poster->tajuk_poster }}</p>
        </div>
        <div class="tap-head__cluster">
            <a href="{{ route('poster.index') }}" class="tap-head__btn">Batal</a>
        </div>
    </div>

    @if ($errors->any())
        <div class="formerr" style="margin-bottom:16px;">{{ $errors->first() }}</div>
    @endif

    <form method="POST" action="{{ $action }}" enctype="multipart/form-data">
        @csrf
        @unless ($isCreate) @method('PUT') @endunless

        <div class="tap-card" style="margin-bottom:18px;">
            <div class="tap-card__eyebrow">Maklumat Poster</div>
            <div class="wiz-grid">
                <div class="wiz-field wiz-field--span-2">
                    <label class="wiz-field__label">Tajuk *</label>
                    <input class="wiz-field__input" name="tajuk_poster" value="{{ $val('tajuk_poster') }}" maxlength="255" required>
                    @error('tajuk_poster') <div class="wiz-field__hint" style="color:var(--danger)">{{ $message }}</div> @enderror
                </div>
                <div class="wiz-field wiz-field--span-2">
                    <label class="wiz-field__label">Butiran</label>
                    <textarea class="wiz-field__input" name="details_poster" rows="5">{{ $val('details_poster') }}</textarea>
                    @error('details_poster') <div class="wiz-field__hint" style="color:var(--danger)">{{ $message }}</div> @enderror
                </div>
                <div class="wiz-field">
                    <label class="wiz-field__label">Status</label>
                    <select class="wiz-field__select" name="status_poster">
                        <option value="Aktif" @selected((string) $val('status_poster') === 'Aktif' || $val('status_poster') === null)>Aktif</option>
                        <option value="Tidak Aktif" @selected((string) $val('status_poster') === 'Tidak Aktif')>Tidak Aktif</option>
                    </select>
                </div>
                <div class="wiz-field">
                    <label class="wiz-field__label">Imej{{ $isCreate ? '' : ' (gantikan)' }}</label>
                    <input class="wiz-field__input" type="file" name="imej" accept="image/*">
                    @error('imej') <div class="wiz-field__hint" style="color:var(--danger)">{{ $message }}</div> @enderror
                    @unless ($isCreate)
                        @if ($poster->image_path)
                            <div class="wiz-field__hint">Imej sedia ada dilampirkan.</div>
                        @endif
                    @endunless
                </div>
            </div>
        </div>

        <div style="display:flex; gap:10px; justify-content:flex-end; align-items:center;">
            <a href="{{ route('poster.index') }}" class="btn btn--ghost">Batal</a>
            <button type="submit" class="btn btn--primary">{{ $isCreate ? 'Tambah' : 'Simpan' }}</button>
        </div>
    </form>

    @unless ($isCreate)
        {{-- Separate form — never nest a delete form inside the edit form. --}}
        <form method="POST" action="{{ route('poster.destroy', $poster) }}" onsubmit="return confirm('Padam poster ini?')" style="margin-top:14px;">
            @csrf @method('DELETE')
            <button type="submit" class="btn btn--ghost" style="color:var(--danger);">Padam Poster</button>
        </form>
    @endunless
@endsection
