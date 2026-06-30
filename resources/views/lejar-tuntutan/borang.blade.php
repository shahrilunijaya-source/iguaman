@extends('layouts.staff')

@section('title', 'Tuntutan Baharu')

@section('content')
    <div class="tap-head">
        <div><h1 class="tap-head__title">Tuntutan Baharu<span class="dot"></span></h1></div>
        <a href="{{ route('tuntutan.index') }}" class="btn">‹ Senarai</a>
    </div>

    @if ($errors->any())
        <div class="formerr" style="margin-bottom:14px;">
            <ul style="margin:0; padding-left:18px;">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
        </div>
    @endif

    <form method="POST" action="{{ route('tuntutan.store') }}" class="card" style="padding:18px; display:grid; gap:12px; max-width:640px;">
        @csrf
        <label>Sumber
            <select name="sumber" class="tap-chip" required>
                @foreach ($sumberList as $s)
                    <option value="{{ $s }}" @selected(old('sumber') === $s)>{{ str_replace('_', ' ', $s) }}</option>
                @endforeach
            </select>
        </label>
        <label>No. Fail Kes (id forms, jika ada)
            <input type="number" name="id_kes" value="{{ old('id_kes') }}" class="tap-chip">
        </label>
        <label>KP Peguam
            <input type="text" name="kp_peguam" value="{{ old('kp_peguam') }}" class="tap-chip" maxlength="20">
        </label>
        <label>Jenis Tuntutan
            <input type="text" name="jenis_tuntutan" value="{{ old('jenis_tuntutan') }}" class="tap-chip" maxlength="100">
        </label>
        <label>Keterangan
            <textarea name="keterangan" class="tap-chip" rows="3">{{ old('keterangan') }}</textarea>
        </label>
        <label>Jumlah Tuntutan (RM)
            <input type="number" step="0.01" min="0" name="jumlah_tuntutan" value="{{ old('jumlah_tuntutan', 0) }}" class="tap-chip" required>
        </label>
        <div><button class="btn btn--primary">Simpan Tuntutan</button></div>
    </form>
@endsection
