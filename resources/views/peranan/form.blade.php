@extends('layouts.staff')

@section('title', $mode === 'create' ? 'Tambah Peranan' : 'Kemaskini Peranan')

@php
    $isCreate = $mode === 'create';
    $action = $isCreate ? route('peranan.store') : route('peranan.update', $role);
@endphp

@section('content')
    <div class="tap-head">
        <div>
            <h1 class="tap-head__title">{{ $isCreate ? 'Tambah Peranan' : 'Kemaskini Peranan' }}<span class="dot"></span></h1>
            <p class="tap-head__sub">{{ $isCreate ? 'Daftar peranan baharu.' : ucfirst($role->name) }}</p>
        </div>
        <div class="tap-head__cluster">
            <a href="{{ route('peranan.index') }}" class="tap-head__btn">Batal</a>
        </div>
    </div>

    @if ($errors->any())
        <div class="formerr" style="margin-bottom:16px;">{{ $errors->first() }}</div>
    @endif

    <form method="POST" action="{{ $action }}">
        @csrf
        @unless ($isCreate) @method('PUT') @endunless

        <div class="tap-card" style="margin-bottom:18px;">
            <div class="tap-card__eyebrow">Maklumat Peranan</div>
            <div class="wiz-grid">
                <div class="wiz-field wiz-field--span-2">
                    <label class="wiz-field__label">Nama Peranan *</label>
                    <input class="wiz-field__input" name="name" value="{{ old('name', $role->name) }}" maxlength="50" required>
                    @error('name') <div class="wiz-field__hint" style="color:var(--danger)">{{ $message }}</div> @enderror
                </div>
            </div>
        </div>

        <div style="display:flex; gap:10px; justify-content:flex-end; align-items:center;">
            <a href="{{ route('peranan.index') }}" class="btn btn--ghost">Batal</a>
            <button type="submit" class="btn btn--primary">{{ $isCreate ? 'Tambah' : 'Simpan' }}</button>
        </div>
    </form>
@endsection
