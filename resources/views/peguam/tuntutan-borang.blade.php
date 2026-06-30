@extends('layouts.peguam')

@section('title', 'Failkan Tuntutan')

@section('content')
    <div class="tap-head">
        <div>
            <h1 class="tap-head__title">Failkan Tuntutan<span class="dot"></span></h1>
            <p class="tap-head__sub">Kes: <strong>{{ $kes->no_fail ?? $kes->id }}</strong> — {{ $kes->nama }}</p>
        </div>
        <a href="{{ route('peguam.kes.show', $kes) }}" class="btn">‹ Kembali</a>
    </div>

    @if ($errors->any())
        <div class="formerr" style="margin-bottom:14px;">
            <ul style="margin:0; padding-left:18px;">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
        </div>
    @endif

    <form method="POST" action="{{ route('peguam.tuntutan.store', $kes) }}" class="card" style="padding:18px; display:grid; gap:12px; max-width:560px;">
        @csrf
        <label>Jenis Tuntutan
            <input type="text" name="jenis_tuntutan" value="{{ old('jenis_tuntutan') }}" class="tap-chip" maxlength="100" required>
        </label>
        <label>Keterangan
            <textarea name="keterangan" class="tap-chip" rows="3">{{ old('keterangan') }}</textarea>
        </label>
        <label>Jumlah Tuntutan (RM)
            <input type="number" step="0.01" min="0" name="jumlah_tuntutan" value="{{ old('jumlah_tuntutan') }}" class="tap-chip" required>
        </label>
        <div><button class="btn btn--primary">Hantar Tuntutan</button></div>
    </form>
@endsection
