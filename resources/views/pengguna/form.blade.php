@extends('layouts.staff')

@section('title', $mode === 'create' ? 'Tambah Pengguna' : 'Kemaskini Pengguna')

@php
    use App\Http\Controllers\UserController;

    $isCreate = $mode === 'create';
    $action = $isCreate ? route('pengguna.store') : route('pengguna.update', $user);
    $val = fn (string $f) => old($f, $user->$f);
    // is_active casts to bool; default new accounts to active.
    $activeVal = old('is_active', $user->is_active === null ? '1' : ($user->is_active ? '1' : '0'));
@endphp

@section('content')
    <div class="tap-head">
        <div>
            <h1 class="tap-head__title">{{ $isCreate ? 'Tambah Pengguna' : 'Kemaskini Pengguna' }}<span class="dot"></span></h1>
            <p class="tap-head__sub">{{ $isCreate ? 'Daftar akaun pengguna baharu.' : $user->name }}</p>
        </div>
        <div class="tap-head__cluster">
            <a href="{{ route('pengguna.index') }}" class="tap-head__btn">Batal</a>
        </div>
    </div>

    @if ($errors->any())
        <div class="formerr" style="margin-bottom:16px;">{{ $errors->first() }}</div>
    @endif

    <form method="POST" action="{{ $action }}">
        @csrf
        @unless ($isCreate) @method('PUT') @endunless

        <div class="tap-card" style="margin-bottom:18px;">
            <div class="tap-card__eyebrow">Maklumat Pengguna</div>
            <div class="wiz-grid">
                <div class="wiz-field wiz-field--span-2">
                    <label class="wiz-field__label">Nama *</label>
                    <input class="wiz-field__input" name="name" value="{{ $val('name') }}" maxlength="255" required>
                    @error('name') <div class="wiz-field__hint" style="color:var(--danger)">{{ $message }}</div> @enderror
                </div>
                <div class="wiz-field">
                    <label class="wiz-field__label">Emel *</label>
                    <input class="wiz-field__input" type="email" name="email" value="{{ $val('email') }}" maxlength="255" required>
                    @error('email') <div class="wiz-field__hint" style="color:var(--danger)">{{ $message }}</div> @enderror
                </div>
                <div class="wiz-field">
                    <label class="wiz-field__label">Username</label>
                    <input class="wiz-field__input" name="username" value="{{ $val('username') }}" maxlength="255">
                    @error('username') <div class="wiz-field__hint" style="color:var(--danger)">{{ $message }}</div> @enderror
                </div>
                <div class="wiz-field">
                    <label class="wiz-field__label">Peranan *</label>
                    <select class="wiz-field__select" name="role" required>
                        @foreach (UserController::ROLES as $value => $label)
                            <option value="{{ $value }}" @selected((string) $val('role') === (string) $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                    @error('role') <div class="wiz-field__hint" style="color:var(--danger)">{{ $message }}</div> @enderror
                </div>
                <div class="wiz-field">
                    <label class="wiz-field__label">Jenis Pengguna *</label>
                    <select class="wiz-field__select" name="user_type" required>
                        <option value="staff" @selected((string) $val('user_type') === 'staff' || $val('user_type') === null)>Staf</option>
                        <option value="lawyer" @selected((string) $val('user_type') === 'lawyer')>Peguam</option>
                    </select>
                    @error('user_type') <div class="wiz-field__hint" style="color:var(--danger)">{{ $message }}</div> @enderror
                </div>
                <div class="wiz-field">
                    <label class="wiz-field__label">Cawangan</label>
                    <input class="wiz-field__input" name="cawangan" value="{{ $val('cawangan') }}" maxlength="50">
                    @error('cawangan') <div class="wiz-field__hint" style="color:var(--danger)">{{ $message }}</div> @enderror
                </div>
                <div class="wiz-field">
                    <label class="wiz-field__label">No. KP</label>
                    <input class="wiz-field__input" name="nokp" value="{{ $val('nokp') }}" maxlength="20">
                    @error('nokp') <div class="wiz-field__hint" style="color:var(--danger)">{{ $message }}</div> @enderror
                </div>
                <div class="wiz-field">
                    <label class="wiz-field__label">Status</label>
                    <select class="wiz-field__select" name="is_active">
                        <option value="1" @selected((string) $activeVal === '1')>Aktif</option>
                        <option value="0" @selected((string) $activeVal === '0')>Tidak Aktif</option>
                    </select>
                </div>
                <div class="wiz-field wiz-field--span-2">
                    <label class="wiz-field__label">Kata Laluan {{ $isCreate ? '*' : '' }}</label>
                    <input class="wiz-field__input" type="password" name="password" autocomplete="new-password" minlength="8" {{ $isCreate ? 'required' : '' }}>
                    <div class="wiz-field__hint">{{ $isCreate ? 'Minimum 8 aksara.' : 'Biar kosong untuk kekal.' }}</div>
                    @error('password') <div class="wiz-field__hint" style="color:var(--danger)">{{ $message }}</div> @enderror
                </div>
            </div>
        </div>

        <div style="display:flex; gap:10px; justify-content:flex-end; align-items:center;">
            <a href="{{ route('pengguna.index') }}" class="btn btn--ghost">Batal</a>
            <button type="submit" class="btn btn--primary">{{ $isCreate ? 'Tambah' : 'Simpan' }}</button>
        </div>
    </form>

    @unless ($isCreate)
        {{-- Separate form - never nest a delete form inside the edit form. --}}
        <form method="POST" action="{{ route('pengguna.destroy', $user) }}" onsubmit="return confirm('Padam pengguna ini?')" style="margin-top:14px;">
            @csrf @method('DELETE')
            <button type="submit" class="btn btn--ghost" style="color:var(--danger);">Padam Pengguna</button>
        </form>
    @endunless
@endsection
