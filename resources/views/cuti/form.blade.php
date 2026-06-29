@extends('layouts.staff')

@section('title', $mode === 'create' ? 'Tambah Cuti' : 'Kemaskini Cuti')

@php
    $isCreate = $mode === 'create';
    $action = $isCreate ? route('cuti.store') : route('cuti.update', $cuti);
    $val = fn (string $f) => old($f, $cuti->$f);
    $mula = old('tarikh_mula', optional($cuti->tarikh_mula)->format('Y-m-d'));
    $tamat = old('tarikh_tamat', optional($cuti->tarikh_tamat)->format('Y-m-d'));
    $checked = collect(old('negeri', $selected))->map(fn ($v) => (int) $v)->all();
@endphp

@section('content')
    <div class="tap-head">
        <div>
            <h1 class="tap-head__title">{{ $isCreate ? 'Tambah Cuti' : 'Kemaskini Cuti' }}<span class="dot"></span></h1>
            <p class="tap-head__sub">{{ $isCreate ? 'Daftar cuti umum baharu.' : $cuti->nama_cuti }}</p>
        </div>
        <div class="tap-head__cluster">
            <a href="{{ route('cuti.index') }}" class="tap-head__btn">Batal</a>
        </div>
    </div>

    @if ($errors->any())
        <div class="formerr" style="margin-bottom:16px;">{{ $errors->first() }}</div>
    @endif

    <form method="POST" action="{{ $action }}">
        @csrf
        @unless ($isCreate) @method('PUT') @endunless

        <div class="tap-card" style="margin-bottom:18px;">
            <div class="tap-card__eyebrow">Maklumat Cuti</div>
            <div class="wiz-grid">
                <div class="wiz-field wiz-field--span-2">
                    <label class="wiz-field__label">Nama Cuti *</label>
                    <input class="wiz-field__input" name="nama_cuti" value="{{ $val('nama_cuti') }}" maxlength="255" required>
                    @error('nama_cuti') <div class="wiz-field__hint" style="color:var(--danger)">{{ $message }}</div> @enderror
                </div>
                <div class="wiz-field">
                    <label class="wiz-field__label">Tarikh Mula *</label>
                    <input type="date" class="wiz-field__input" name="tarikh_mula" value="{{ $mula }}" required>
                    @error('tarikh_mula') <div class="wiz-field__hint" style="color:var(--danger)">{{ $message }}</div> @enderror
                </div>
                <div class="wiz-field">
                    <label class="wiz-field__label">Tarikh Tamat *</label>
                    <input type="date" class="wiz-field__input" name="tarikh_tamat" value="{{ $tamat }}" required>
                    @error('tarikh_tamat') <div class="wiz-field__hint" style="color:var(--danger)">{{ $message }}</div> @enderror
                </div>
            </div>
        </div>

        <div class="tap-card" style="margin-bottom:18px;">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:10px;">
                <div class="tap-card__eyebrow" style="margin:0;">Negeri Terlibat *</div>
                <label style="font-size:12px; display:inline-flex; gap:6px; align-items:center; cursor:pointer; color:var(--mute);">
                    <input type="checkbox" id="cuti-all"> Pilih semua
                </label>
            </div>
            @error('negeri') <div class="wiz-field__hint" style="color:var(--danger); margin-bottom:8px;">{{ $message }}</div> @enderror
            <div style="display:grid; grid-template-columns: repeat(4, 1fr); gap:8px 14px;">
                @foreach ($negeriList as $id => $nama)
                    <label style="display:flex; gap:8px; align-items:center; font-size:13px; cursor:pointer;">
                        <input type="checkbox" class="cuti-negeri" name="negeri[]" value="{{ $id }}" @checked(in_array((int) $id, $checked, true))>
                        {{ $nama }}
                    </label>
                @endforeach
            </div>
        </div>

        <div style="display:flex; gap:10px; justify-content:flex-end; align-items:center;">
            <a href="{{ route('cuti.index') }}" class="btn btn--ghost">Batal</a>
            <button type="submit" class="btn btn--primary">{{ $isCreate ? 'Tambah' : 'Simpan' }}</button>
        </div>
    </form>

    @unless ($isCreate)
        <form method="POST" action="{{ route('cuti.destroy', $cuti) }}" onsubmit="return confirm('Padam cuti ini?')" style="margin-top:14px;">
            @csrf @method('DELETE')
            <button type="submit" class="btn btn--ghost" style="color:var(--danger);">Padam Cuti</button>
        </form>
    @endunless

    @push('scripts')
        <script>
            (function () {
                var all = document.getElementById('cuti-all');
                var boxes = Array.prototype.slice.call(document.querySelectorAll('.cuti-negeri'));
                function sync() { all.checked = boxes.length > 0 && boxes.every(function (b) { return b.checked; }); }
                all.addEventListener('change', function () { boxes.forEach(function (b) { b.checked = all.checked; }); });
                boxes.forEach(function (b) { b.addEventListener('change', sync); });
                sync();
            })();
        </script>
    @endpush
@endsection
