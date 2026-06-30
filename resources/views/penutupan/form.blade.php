@extends('layouts.staff')

@section('title', 'Tambah Penutupan Operasi')

@php
    // Branch -> its rooms, for the cascading room select.
    $bilikByCawangan = $cawanganList->mapWithKeys(fn ($c) => [
        $c->id => $c->bilik->map(fn ($b) => ['id' => $b->id, 'nama' => $b->nama_bilik])->values(),
    ]);
@endphp

@section('content')
    <div class="tap-head">
        <div>
            <h1 class="tap-head__title">Tambah Penutupan Operasi<span class="dot"></span></h1>
            <p class="tap-head__sub">Halang penjanaan/tempahan slot untuk julat tarikh.</p>
        </div>
        <div class="tap-head__cluster">
            <a href="{{ route('penutupan.index') }}" class="tap-head__btn">Batal</a>
        </div>
    </div>

    @if ($errors->any())
        <div class="formerr" style="margin-bottom:16px;">{{ $errors->first() }}</div>
    @endif

    <form method="POST" action="{{ route('penutupan.store') }}">
        @csrf
        <div class="tap-card" style="margin-bottom:18px;">
            <div class="tap-card__eyebrow">Maklumat Penutupan</div>
            <div class="wiz-grid">
                <div class="wiz-field">
                    <label class="wiz-field__label">Cawangan *</label>
                    <select class="wiz-field__input" name="cawangan_id" id="pen-cawangan" required>
                        <option value="">— Pilih cawangan —</option>
                        @foreach ($cawanganList as $c)
                            <option value="{{ $c->id }}" @selected((int) old('cawangan_id') === $c->id)>{{ $c->nama }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="wiz-field">
                    <label class="wiz-field__label">Bilik (pilihan)</label>
                    <select class="wiz-field__input" name="bilik_id" id="pen-bilik">
                        <option value="">— Seluruh cawangan —</option>
                    </select>
                </div>
                <div class="wiz-field">
                    <label class="wiz-field__label">Tarikh Mula *</label>
                    <input type="date" class="wiz-field__input" name="tarikh_mula" value="{{ old('tarikh_mula') }}" required>
                </div>
                <div class="wiz-field">
                    <label class="wiz-field__label">Tarikh Tamat *</label>
                    <input type="date" class="wiz-field__input" name="tarikh_tamat" value="{{ old('tarikh_tamat') }}" required>
                </div>
                <div class="wiz-field wiz-field--span-2">
                    <label class="wiz-field__label">Sebab</label>
                    <input class="wiz-field__input" name="sebab" value="{{ old('sebab') }}" maxlength="255" placeholder="cth. Penyelenggaraan bangunan">
                </div>
            </div>
        </div>

        <div style="display:flex; gap:10px; justify-content:flex-end; align-items:center;">
            <a href="{{ route('penutupan.index') }}" class="btn btn--ghost">Batal</a>
            <button type="submit" class="btn btn--primary">Tambah</button>
        </div>
    </form>

    @push('scripts')
        <script>
            (function () {
                var rooms = @json($bilikByCawangan);
                var oldBilik = @json(old('bilik_id'));
                var cawangan = document.getElementById('pen-cawangan');
                var bilik = document.getElementById('pen-bilik');
                function refresh() {
                    var list = rooms[cawangan.value] || [];
                    bilik.innerHTML = '<option value="">— Seluruh cawangan —</option>';
                    list.forEach(function (r) {
                        var o = document.createElement('option');
                        o.value = r.id; o.textContent = r.nama;
                        if (String(r.id) === String(oldBilik)) o.selected = true;
                        bilik.appendChild(o);
                    });
                }
                cawangan.addEventListener('change', function () { oldBilik = null; refresh(); });
                refresh();
            })();
        </script>
    @endpush
@endsection
