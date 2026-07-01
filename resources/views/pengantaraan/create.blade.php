@extends('layouts.staff')

@section('title', 'Pendaftaran Pengantaraan Terus')

@section('content')
    <div class="tap-nav" style="margin: -24px -20px 18px; border-radius: 0;">
        <a href="{{ route('pengantaraan.senarai') }}" class="tap-nav__back">← Senarai Pengantaraan</a>
        <span class="tap-nav__crumb">Pendaftaran Terus</span>
    </div>

    @if ($errors->any())
        <div class="formerr" style="margin-bottom:14px;">{{ $errors->first() }}</div>
    @endif

    <div class="tap-card" style="max-width:720px;">
        <div class="tap-card__eyebrow">Pendaftaran Pengantaraan Terus (TERUS)</div>
        <p class="dash-empty__sub" style="margin:2px 0 14px;">Permohonan pengantaraan secara terus - bukan daripada kes litigasi. No. pengantaraan sendiri dijana automatik.</p>

        <form method="POST" action="{{ route('pengantaraan.store') }}">
            @csrf
            <div class="wiz-grid">
                <div class="wiz-field">
                    <label class="wiz-field__label">Nama Pemohon <span style="color:var(--danger)">*</span></label>
                    <input class="wiz-field__input" name="nama" value="{{ old('nama') }}" required>
                </div>
                <div class="wiz-field">
                    <label class="wiz-field__label">No. Pengenalan</label>
                    <input class="wiz-field__input" name="nokp" value="{{ old('nokp') }}" maxlength="20">
                </div>
                <div class="wiz-field">
                    <label class="wiz-field__label">Cawangan <span style="color:var(--danger)">*</span></label>
                    <select class="wiz-field__input" name="cawangan" required>
                        <option value="">- Pilih cawangan -</option>
                        @foreach ($cawanganList as $c)
                            <option value="{{ $c }}" @selected(old('cawangan') === $c)>{{ $c }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="wiz-field">
                    <label class="wiz-field__label">Jenis Kes</label>
                    <select class="wiz-field__input" name="jenis_kes">
                        <option value="">-</option>
                        @foreach ($jenisList as $j)
                            <option value="{{ $j }}" @selected(old('jenis_kes') === $j)>{{ $j }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="wiz-field">
                    <label class="wiz-field__label">Kategori Kes</label>
                    <input class="wiz-field__input" name="kategori_kes" value="{{ old('kategori_kes') }}" maxlength="100">
                </div>
                <div class="wiz-field">
                    <label class="wiz-field__label">Kategori Pengantaraan</label>
                    <input class="wiz-field__input" name="pengantaraan_kategori_kes" value="{{ old('pengantaraan_kategori_kes') }}">
                </div>
                <div class="wiz-field">
                    <label class="wiz-field__label">Tarikh Permohonan</label>
                    <input type="date" class="wiz-field__input" name="tarikh_permohonan" value="{{ old('tarikh_permohonan') }}">
                </div>
            </div>

            <div style="display:flex; gap:10px; justify-content:flex-end; margin-top:16px;">
                <a href="{{ route('pengantaraan.senarai') }}" class="btn btn--ghost">Batal</a>
                <button type="submit" class="btn btn--primary">Daftar Pengantaraan</button>
            </div>
        </form>
    </div>
@endsection
