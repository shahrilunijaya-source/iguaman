@extends('layouts.staff')

@section('title', $mode === 'create' ? 'Tambah Cawangan' : 'Kemaskini Cawangan')

@php
    $isCreate = $mode === 'create';
    $action = $isCreate ? route('cawangan.store') : route('cawangan.update', $cawangan);
    $val = fn (string $f) => old($f, $cawangan->$f);
@endphp

@section('content')
    <div class="tap-head">
        <div>
            <h1 class="tap-head__title">{{ $isCreate ? 'Tambah Cawangan' : 'Kemaskini Cawangan' }}<span class="dot"></span></h1>
            <p class="tap-head__sub">{{ $isCreate ? 'Daftar cawangan baharu.' : $cawangan->nama }}</p>
        </div>
        <div class="tap-head__cluster">
            <a href="{{ route('cawangan.index') }}" class="tap-head__btn">Batal</a>
        </div>
    </div>

    @if (session('status'))
        <div class="formerr" style="color: var(--success); background: rgba(16,185,129,0.08); border-color: rgba(16,185,129,0.18); margin-bottom:14px;">{{ session('status') }}</div>
    @endif
    @if ($errors->any())
        <div class="formerr" style="margin-bottom:16px;">{{ $errors->first() }}</div>
    @endif

    <form method="POST" action="{{ $action }}">
        @csrf
        @unless ($isCreate) @method('PUT') @endunless

        <div class="tap-card" style="margin-bottom:18px;">
            <div class="tap-card__eyebrow">Maklumat Cawangan</div>
            <div class="wiz-grid">
                <div class="wiz-field">
                    <label class="wiz-field__label">Jenis *</label>
                    <select class="wiz-field__input" name="jenis" required>
                        @foreach (\App\Models\Cawangan::JENIS as $j)
                            <option value="{{ $j }}" @selected($val('jenis') === $j)>{{ $j }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="wiz-field">
                    <label class="wiz-field__label">Kod</label>
                    <input class="wiz-field__input" name="kod" value="{{ $val('kod') }}" maxlength="20">
                </div>
                <div class="wiz-field wiz-field--span-2">
                    <label class="wiz-field__label">Nama *</label>
                    <input class="wiz-field__input" name="nama" value="{{ $val('nama') }}" maxlength="255" required>
                    @error('nama') <div class="wiz-field__hint" style="color:var(--danger)">{{ $message }}</div> @enderror
                </div>
                <div class="wiz-field">
                    <label class="wiz-field__label">Negeri</label>
                    <select class="wiz-field__input" name="negeri_id">
                        <option value="">—</option>
                        @foreach ($negeriList as $id => $nama)
                            <option value="{{ $id }}" @selected((int) $val('negeri_id') === (int) $id)>{{ $nama }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="wiz-field">
                    <label class="wiz-field__label">No. Telefon</label>
                    <input class="wiz-field__input" name="no_tel" value="{{ $val('no_tel') }}" maxlength="30">
                </div>
                <div class="wiz-field wiz-field--span-2">
                    <label class="wiz-field__label">Alamat 1</label>
                    <input class="wiz-field__input" name="alamat1" value="{{ $val('alamat1') }}" maxlength="255">
                </div>
                <div class="wiz-field wiz-field--span-2">
                    <label class="wiz-field__label">Alamat 2</label>
                    <input class="wiz-field__input" name="alamat2" value="{{ $val('alamat2') }}" maxlength="255">
                </div>
                <div class="wiz-field">
                    <label class="wiz-field__label">Alamat 3</label>
                    <input class="wiz-field__input" name="alamat3" value="{{ $val('alamat3') }}" maxlength="255">
                </div>
                <div class="wiz-field">
                    <label class="wiz-field__label">Poskod</label>
                    <input class="wiz-field__input" name="poskod" value="{{ $val('poskod') }}" maxlength="10">
                </div>
                <div class="wiz-field">
                    <label class="wiz-field__label" style="display:flex; gap:8px; align-items:center; cursor:pointer;">
                        <input type="checkbox" name="status_aktif" value="1" @checked((bool) old('status_aktif', $cawangan->status_aktif ?? true))> Aktif
                    </label>
                </div>
            </div>
        </div>

        <div style="display:flex; gap:10px; justify-content:flex-end; align-items:center;">
            <a href="{{ route('cawangan.index') }}" class="btn btn--ghost">Batal</a>
            <button type="submit" class="btn btn--primary">{{ $isCreate ? 'Tambah' : 'Simpan' }}</button>
        </div>
    </form>

    @unless ($isCreate)
        {{-- Bilik (rooms) --}}
        <div class="tap-card" style="margin-top:18px;">
            <div class="tap-card__eyebrow">Bilik ({{ $cawangan->bilik->count() }})</div>
            <table style="width:100%; border-collapse:collapse; margin-bottom:12px;">
                @forelse ($cawangan->bilik as $bilik)
                    <tr style="border-bottom:1px solid var(--line);">
                        <td style="padding:8px 0;">{{ $bilik->nama_bilik }}</td>
                        <td style="text-align:right;">
                            <form method="POST" action="{{ route('cawangan.bilik.destroy', [$cawangan, $bilik]) }}" onsubmit="return confirm('Padam bilik ini?')">
                                @csrf @method('DELETE')
                                <button type="submit" class="tap-head__btn" style="color:var(--danger);">✕</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td style="padding:8px 0; color:var(--mute);">Tiada bilik.</td></tr>
                @endforelse
            </table>
            <form method="POST" action="{{ route('cawangan.bilik.store', $cawangan) }}" style="display:flex; gap:8px;">
                @csrf
                <input class="wiz-field__input" name="nama_bilik" placeholder="Nama bilik baharu…" aria-label="Nama bilik baharu…" maxlength="255" required style="flex:1;">
                <button type="submit" class="btn btn--ghost">+ Tambah Bilik</button>
            </form>
        </div>

        <form method="POST" action="{{ route('cawangan.destroy', $cawangan) }}" onsubmit="return confirm('Padam cawangan ini? Semua bilik turut dipadam.')" style="margin-top:14px;">
            @csrf @method('DELETE')
            <button type="submit" class="btn btn--ghost" style="color:var(--danger);">Padam Cawangan</button>
        </form>
    @endunless
@endsection
