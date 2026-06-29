@extends('layouts.staff')

@section('title', $mode === 'create' ? 'Permohonan Baharu' : 'Kemaskini Kes')

@php
    $val = function (string $field, ?string $fmt = null) use ($kes) {
        if ($fmt === 'date') {
            return old($field, optional($kes->$field)->format('Y-m-d'));
        }
        return old($field, $kes->$field);
    };
    $isCreate = $mode === 'create';
    $action = $isCreate ? route('kes.store') : route('kes.update', $kes);
@endphp

@section('content')
    <div class="tap-head">
        <div>
            <h1 class="tap-head__title">{{ $isCreate ? 'Permohonan Baharu' : 'Kemaskini Kes' }}<span class="dot"></span></h1>
            <p class="tap-head__sub">{{ $isCreate ? 'Daftar permohonan bantuan guaman baharu.' : ($kes->nama.' · '.($kes->no_fail ?: '#'.$kes->id)) }}</p>
        </div>
        <div class="tap-head__cluster">
            <a href="{{ $isCreate ? route('kes.index') : route('kes.show', $kes) }}" class="tap-head__btn">Batal</a>
        </div>
    </div>

    @if ($errors->any())
        <div class="formerr" style="margin-bottom:16px;">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M12 8v4"/><path d="M12 16h.01"/></svg>
            Sila betulkan {{ $errors->count() }} ralat di bawah.
        </div>
    @endif

    <form method="POST" action="{{ $action }}">
        @csrf
        @unless ($isCreate) @method('PUT') @endunless

        {{-- ===== Pemohon ===== --}}
        <div class="tap-card" style="margin-bottom:18px;">
            <div class="tap-card__eyebrow">Pemohon</div>
            <div class="wiz-grid">
                <div class="wiz-field wiz-field--span-2">
                    <label class="wiz-field__label">Nama Pemohon *</label>
                    <input class="wiz-field__input" name="nama" value="{{ $val('nama') }}" required>
                    @error('nama') <div class="wiz-field__hint" style="color:var(--danger)">{{ $message }}</div> @enderror
                </div>
                <div class="wiz-field">
                    <label class="wiz-field__label">No. KP</label>
                    <input class="wiz-field__input" name="nokp" value="{{ $val('nokp') }}">
                    @error('nokp') <div class="wiz-field__hint" style="color:var(--danger)">{{ $message }}</div> @enderror
                </div>
                <div class="wiz-field">
                    <label class="wiz-field__label">Umur</label>
                    <input type="number" class="wiz-field__input" name="umur" value="{{ $val('umur') }}">
                </div>
                <div class="wiz-field">
                    <label class="wiz-field__label">Jantina</label>
                    <select class="wiz-field__select" name="jantina">
                        <option value="">—</option>
                        @foreach (['Lelaki', 'Perempuan'] as $opt)
                            <option value="{{ $opt }}" @selected($val('jantina') === $opt)>{{ $opt }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="wiz-field">
                    <label class="wiz-field__label">Agama</label>
                    <input class="wiz-field__input" name="agama" value="{{ $val('agama') }}">
                </div>
                <div class="wiz-field">
                    <label class="wiz-field__label">Bangsa</label>
                    <input class="wiz-field__input" name="bangsa" value="{{ $val('bangsa') }}">
                </div>
                <div class="wiz-field">
                    <label class="wiz-field__label">Etnik</label>
                    <input class="wiz-field__input" name="etnik" value="{{ $val('etnik') }}">
                </div>
                <div class="wiz-field">
                    <label class="wiz-field__label">OKU</label>
                    <select class="wiz-field__select" name="oku">
                        <option value="">—</option>
                        @foreach (['Ya', 'Tidak'] as $opt)
                            <option value="{{ $opt }}" @selected($val('oku') === $opt)>{{ $opt }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="wiz-field">
                    <label class="wiz-field__label">Nama Penjaga</label>
                    <input class="wiz-field__input" name="nama_penjaga" value="{{ $val('nama_penjaga') }}">
                </div>
                <div class="wiz-field">
                    <label class="wiz-field__label">No. KP Penjaga</label>
                    <input class="wiz-field__input" name="nokp_penjaga" value="{{ $val('nokp_penjaga') }}">
                </div>
            </div>
        </div>

        {{-- ===== Permohonan & Pendaftaran ===== --}}
        <div class="tap-card" style="margin-bottom:18px;">
            <div class="tap-card__eyebrow">Permohonan &amp; Pendaftaran</div>
            <div class="wiz-grid">
                <div class="wiz-field">
                    <label class="wiz-field__label">Cawangan *</label>
                    <select class="wiz-field__select" name="cawangan" required>
                        <option value="">— Pilih —</option>
                        @foreach ($cawanganList as $c)
                            <option value="{{ $c }}" @selected($val('cawangan') === $c)>{{ $c }}</option>
                        @endforeach
                    </select>
                    @error('cawangan') <div class="wiz-field__hint" style="color:var(--danger)">{{ $message }}</div> @enderror
                </div>
                <div class="wiz-field">
                    <label class="wiz-field__label">No. Fail</label>
                    <input class="wiz-field__input" name="no_fail" value="{{ $val('no_fail') }}">
                </div>
                <div class="wiz-field">
                    <label class="wiz-field__label">Tarikh Khidmat Nasihat</label>
                    <input type="date" class="wiz-field__input" name="tarikh_khidmat_nasihat" value="{{ $val('tarikh_khidmat_nasihat', 'date') }}">
                </div>
                <div class="wiz-field">
                    <label class="wiz-field__label">Tarikh Permohonan</label>
                    <input type="date" class="wiz-field__input" name="tarikh_permohonan" value="{{ $val('tarikh_permohonan', 'date') }}">
                </div>
                <div class="wiz-field">
                    <label class="wiz-field__label">Kategori Kes</label>
                    <select class="wiz-field__select" name="kategori_kes">
                        <option value="">—</option>
                        @foreach ($kategoriList as $k)
                            <option value="{{ $k }}" @selected($val('kategori_kes') === $k)>{{ $k }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="wiz-field">
                    <label class="wiz-field__label">Jenis Kes</label>
                    <select class="wiz-field__select" name="jenis_kes">
                        <option value="">—</option>
                        @foreach ($jenisList as $j)
                            <option value="{{ $j }}" @selected($val('jenis_kes') === $j)>{{ $j }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="wiz-field">
                    <label class="wiz-field__label">Jenis Kategori</label>
                    <input class="wiz-field__input" name="jenis_kategori" value="{{ $val('jenis_kategori') }}">
                </div>
                <div class="wiz-field">
                    <label class="wiz-field__label">Jenis Jenayah</label>
                    <input class="wiz-field__input" name="jenis_jenayah" value="{{ $val('jenis_jenayah') }}">
                </div>
                <div class="wiz-field">
                    <label class="wiz-field__label">Taraf</label>
                    <input class="wiz-field__input" name="taraf" value="{{ $val('taraf') }}">
                </div>
                <div class="wiz-field">
                    <label class="wiz-field__label">No. Sistem</label>
                    <input class="wiz-field__input" name="no_sistem" value="{{ $val('no_sistem') }}">
                </div>
                <div class="wiz-field">
                    <label class="wiz-field__label">Pegawai</label>
                    <input class="wiz-field__input" name="nama_pegawai" value="{{ $val('nama_pegawai') }}">
                </div>
            </div>
        </div>

        {{-- ===== Keputusan ===== --}}
        <div class="tap-card" style="margin-bottom:18px;">
            <div class="tap-card__eyebrow">Keputusan</div>
            <div class="wiz-grid">
                <div class="wiz-field">
                    <label class="wiz-field__label">Keputusan</label>
                    <input class="wiz-field__input" name="keputusan" value="{{ $val('keputusan') }}">
                </div>
                <div class="wiz-field">
                    <label class="wiz-field__label">Diterima</label>
                    <input class="wiz-field__input" name="diterima" value="{{ $val('diterima') }}">
                </div>
                <div class="wiz-field">
                    <label class="wiz-field__label">Kelulusan</label>
                    <input class="wiz-field__input" name="kelulusan" value="{{ $val('kelulusan') }}">
                </div>
                <div class="wiz-field">
                    <label class="wiz-field__label">Keputusan Menteri</label>
                    <input class="wiz-field__input" name="keputusan_menteri" value="{{ $val('keputusan_menteri') }}">
                </div>
                <div class="wiz-field">
                    <label class="wiz-field__label">Tarikh Perakuan</label>
                    <input type="date" class="wiz-field__input" name="tarikh_perakuan" value="{{ $val('tarikh_perakuan', 'date') }}">
                </div>
                <div class="wiz-field">
                    <label class="wiz-field__label">Tarikh Pemakluman</label>
                    <input type="date" class="wiz-field__input" name="tarikh_pemakluman" value="{{ $val('tarikh_pemakluman', 'date') }}">
                </div>
                <div class="wiz-field">
                    <label class="wiz-field__label">Sumbangan</label>
                    <input class="wiz-field__input" name="sumbangan" value="{{ $val('sumbangan') }}">
                </div>
                <div class="wiz-field">
                    <label class="wiz-field__label">Nilai Sumbangan (RM)</label>
                    <input type="number" class="wiz-field__input" name="nilai_sumbangan" value="{{ $val('nilai_sumbangan') }}">
                </div>
            </div>
        </div>

        {{-- ===== Penutupan ===== --}}
        <div class="tap-card" style="margin-bottom:18px;">
            <div class="tap-card__eyebrow">Status &amp; Penutupan</div>
            <div class="wiz-grid">
                <div class="wiz-field">
                    <label class="wiz-field__label">Status</label>
                    <input class="wiz-field__input" name="status" value="{{ $val('status') }}">
                </div>
                <div class="wiz-field">
                    <label class="wiz-field__label">Tarikh Selesai</label>
                    <input type="date" class="wiz-field__input" name="tarikh_selesai" value="{{ $val('tarikh_selesai', 'date') }}">
                </div>
                <div class="wiz-field">
                    <label class="wiz-field__label">Sebab Selesai</label>
                    <input class="wiz-field__input" name="sebab_selesai" value="{{ $val('sebab_selesai') }}">
                </div>
                <div class="wiz-field">
                    <label class="wiz-field__label">Tarikh Tutup Fail</label>
                    <input type="date" class="wiz-field__input" name="tarikh_tutup_fail" value="{{ $val('tarikh_tutup_fail', 'date') }}">
                </div>
                <div class="wiz-field wiz-field--span-2">
                    <label class="wiz-field__label">Sebab Tutup Fail</label>
                    <textarea class="wiz-field__textarea" name="sebab_tutup_fail">{{ $val('sebab_tutup_fail') }}</textarea>
                </div>
            </div>
        </div>

        <div style="display:flex; gap:10px; justify-content:flex-end;">
            <a href="{{ $isCreate ? route('kes.index') : route('kes.show', $kes) }}" class="btn btn--ghost">Batal</a>
            <button type="submit" class="btn btn--primary">{{ $isCreate ? 'Daftar Permohonan' : 'Simpan Perubahan' }}</button>
        </div>
    </form>
@endsection
