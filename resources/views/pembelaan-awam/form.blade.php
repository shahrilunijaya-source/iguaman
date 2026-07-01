@extends('layouts.staff')

@section('title', 'Pembelaan Awam Baharu')

@section('content')
    <div class="tap-head">
        <div><h1 class="tap-head__title">Pembelaan Awam Baharu<span class="dot"></span></h1></div>
        <a href="{{ route('pembelaan.index') }}" class="btn">‹ Senarai</a>
    </div>

    @if ($errors->any())
        <div class="formerr" style="margin-bottom:14px;">
            <ul style="margin:0; padding-left:18px;">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
        </div>
    @endif

    <form method="POST" action="{{ route('pembelaan.store') }}" class="card" style="padding:18px; display:grid; gap:12px; max-width:680px;">
        @csrf

        <label>Nama Tertuduh (OYD)
            <input type="text" name="nama" value="{{ old('nama') }}" class="tap-chip" maxlength="150" required>
        </label>
        <label>No. Kad Pengenalan
            <input type="text" name="nokp" value="{{ old('nokp') }}" class="tap-chip" maxlength="20">
        </label>
        <label>Cawangan
            <select name="cawangan" class="tap-chip" required>
                <option value="">— Pilih —</option>
                @foreach ($cawanganList as $c)
                    <option value="{{ $c }}" @selected(old('cawangan') === $c)>{{ $c }}</option>
                @endforeach
            </select>
        </label>
        <label>Jenis Kes (kod)
            <input type="text" name="jenis_kes" value="{{ old('jenis_kes') }}" class="tap-chip" maxlength="5" placeholder="cth. 085" aria-label="cth. 085">
        </label>
        <label>Kategori Kes
            <input type="text" name="kategori_kes" value="{{ old('kategori_kes') }}" class="tap-chip" maxlength="100">
        </label>
        <label>Jenis Permohonan Pembelaan
            <input type="text" name="jenis_pemohonan_pembelaan" value="{{ old('jenis_pemohonan_pembelaan') }}" class="tap-chip" maxlength="80">
        </label>
        <label>No. Pertuduhan
            <input type="text" name="no_pertuduhan" value="{{ old('no_pertuduhan') }}" class="tap-chip" maxlength="100">
        </label>
        <label>Seksyen Kesalahan
            <input type="text" name="seksyen_kesalahan" value="{{ old('seksyen_kesalahan') }}" class="tap-chip" maxlength="150">
        </label>
        <label>Mahkamah
            <input type="text" name="mahkamah_pembelaan" value="{{ old('mahkamah_pembelaan') }}" class="tap-chip" maxlength="150">
        </label>
        <label>Tarikh Pertuduhan
            <input type="date" name="tarikh_pertuduhan" value="{{ old('tarikh_pertuduhan') }}" class="tap-chip">
        </label>
        <label>Tarikh Permohonan
            <input type="date" name="tarikh_permohonan" value="{{ old('tarikh_permohonan') }}" class="tap-chip">
        </label>
        <label style="display:flex; align-items:center; gap:8px;">
            <input type="checkbox" name="is_segera" value="1" @checked(old('is_segera')) style="width:auto;">
            Kes Segera (membolehkan Perakuan Bantuan Guaman Interim)
        </label>

        <div><button class="btn btn--primary">Daftar Pembelaan</button></div>
    </form>
@endsection
