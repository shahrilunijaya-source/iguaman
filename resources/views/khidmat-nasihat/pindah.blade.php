@extends('layouts.staff')

@section('title', 'Pindah Cawangan — '.($khidmat->no_permohonan ?: 'KN #'.$khidmat->id))

@section('content')
<div class="tap-head">
    <div>
        <h1 class="tap-head__title">Pindah Cawangan<span class="dot"></span></h1>
        <p class="tap-head__sub">Pindahkan permohonan Khidmat Nasihat ini ke cawangan lain. Cawangan asal kekal melihat rekod ini sehingga cawangan tujuan mengesahkan terima.</p>
    </div>
    <a href="{{ route('khidmat.show', $khidmat) }}" class="btn">‹ Kembali</a>
</div>

@if (session('error'))
    <div class="formerr" style="margin-bottom:14px;">{{ session('error') }}</div>
@endif
@if ($errors->any())
    <div class="formerr" style="margin-bottom:14px;">{{ $errors->first() }}</div>
@endif

<div class="tap-card" style="max-width:640px;">
    <dl style="display:grid; grid-template-columns:160px 1fr; gap:8px 16px; margin:0 0 18px;">
        <dt>No. Permohonan</dt><dd>{{ $khidmat->no_permohonan ?: '—' }}</dd>
        <dt>Mangsa</dt><dd>{{ $khidmat->nama_mangsa ?: '—' }}</dd>
        <dt>Status</dt><dd>{{ str_replace('_', ' ', $khidmat->status_kn) }}</dd>
        <dt>Cawangan Semasa</dt><dd><strong>{{ optional($khidmat->cawangan)->nama ?: '—' }}</strong></dd>
    </dl>

    @if ($pending)
        <div class="formerr" style="color:var(--pine-deep,#0d2e48); background:var(--paper-2,#eef4f3); border-color:rgba(26,111,168,.2);">
            Rekod ini sudah dipindahkan ke <strong>{{ $pending->cawangan_tujuan }}</strong> pada
            {{ optional($pending->tarikh_pindah)->format('d/m/Y H:i') }} dan menunggu pengesahan terima.
            Tidak boleh dipindahkan semula sehingga pemindahan itu selesai diproses.
        </div>
    @else
        <form method="POST" action="{{ route('khidmat.pindah', $khidmat) }}" onsubmit="return confirm('Pindahkan Khidmat Nasihat ini ke cawangan dipilih?')">
            @csrf
            <div class="field" style="margin-bottom:14px;">
                <label class="field__label" for="cawangan_tujuan_id">Cawangan Tujuan</label>
                <select name="cawangan_tujuan_id" id="cawangan_tujuan_id" class="field__input" required>
                    <option value="">— Pilih cawangan —</option>
                    @foreach ($cawanganList as $c)
                        @if ((int) $c->id !== (int) $khidmat->cawangan_id)
                            <option value="{{ $c->id }}" @selected(old('cawangan_tujuan_id') == $c->id)>{{ $c->nama }}</option>
                        @endif
                    @endforeach
                </select>
            </div>
            <div class="field" style="margin-bottom:18px;">
                <label class="field__label" for="sebab">Sebab Pemindahan</label>
                <textarea name="sebab" id="sebab" class="field__input" rows="3" maxlength="1000" required placeholder="Cth: pemohon berpindah / bidang kuasa cawangan tujuan." aria-label="Cth: pemohon berpindah / bidang kuasa cawangan tujuan.">{{ old('sebab') }}</textarea>
            </div>
            <button type="submit" class="btn btn--primary">Pindahkan Khidmat Nasihat</button>
        </form>
    @endif
</div>
@endsection
