@extends('layouts.staff')

@section('title', 'Penjanaan Slot Janji Temu')

@php
    $hariLabels = [1 => 'Isnin', 2 => 'Selasa', 3 => 'Rabu', 4 => 'Khamis', 5 => 'Jumaat', 6 => 'Sabtu', 7 => 'Ahad'];
    $currentWeekend = $selected ? ($selected->weekendDays() ?? [6, 7]) : [6, 7];
@endphp

@section('content')
    <div class="tap-head">
        <div>
            <h1 class="tap-head__title">Penjanaan Slot<span class="dot"></span></h1>
            <p class="tap-head__sub">Jana slot janji temu mengikut cawangan, bilik &amp; penetapan sesi.</p>
        </div>
    </div>

    @if (session('status'))
        <div class="formerr" style="color: var(--success); background: rgba(16,185,129,0.08); border-color: rgba(16,185,129,0.18); margin-bottom:14px;">{{ session('status') }}</div>
    @endif
    @if ($errors->any())
        <div class="formerr" style="margin-bottom:16px;">{{ $errors->first() }}</div>
    @endif

    {{-- Branch picker --}}
    <form method="GET" action="{{ route('slot.index') }}" class="tap-card" style="margin-bottom:18px;">
        <div class="tap-card__eyebrow">Pilih Cawangan</div>
        <div style="display:flex; gap:10px; align-items:flex-end;">
            <div class="wiz-field" style="flex:1;">
                <label class="wiz-field__label">Cawangan</label>
                <select class="wiz-field__input" name="cawangan_id" onchange="this.form.submit()">
                    <option value="">- Pilih cawangan -</option>
                    @foreach ($cawanganList as $c)
                        <option value="{{ $c->id }}" @selected($selected && $selected->id === $c->id)>{{ $c->nama }}</option>
                    @endforeach
                </select>
            </div>
            <button type="submit" class="btn btn--ghost" style="height:42px;">Pilih</button>
        </div>
    </form>

    @if ($selected)
        {{-- Summary --}}
        <div class="tap-card" style="margin-bottom:18px;">
            <div class="tap-card__eyebrow">Ringkasan Slot · {{ $selected->nama }}</div>
            <div style="display:flex; gap:24px; flex-wrap:wrap; font-size:13px;">
                <div><strong style="font-size:20px;">{{ number_format($summary['jumlah'] ?? 0) }}</strong><br><span style="color:var(--mute);">Jumlah slot</span></div>
                <div><strong style="font-size:20px;">{{ number_format($summary['ditempah'] ?? 0) }}</strong><br><span style="color:var(--mute);">Telah ditempah</span></div>
                <div><strong style="font-size:20px;">{{ ($summary['mula'] ?? null) ? \Illuminate\Support\Carbon::parse($summary['mula'])->format('d/m/Y') : '-' }}</strong><br><span style="color:var(--mute);">Slot terawal</span></div>
                <div><strong style="font-size:20px;">{{ ($summary['tamat'] ?? null) ? \Illuminate\Support\Carbon::parse($summary['tamat'])->format('d/m/Y') : '-' }}</strong><br><span style="color:var(--mute);">Slot terakhir</span></div>
            </div>
        </div>

        {{-- Penetapan sesi --}}
        <form method="POST" action="{{ route('slot.sesi', $selected) }}" class="tap-card" style="margin-bottom:18px;">
            @csrf @method('PUT')
            <div class="tap-card__eyebrow">Penetapan Sesi (Janji Temu)</div>
            <div class="wiz-grid">
                <div class="wiz-field wiz-field--span-2">
                    <label class="wiz-field__label">Hari Hujung Minggu (slot tidak dijana)</label>
                    <div style="display:flex; gap:14px; flex-wrap:wrap; padding-top:4px;">
                        @foreach ($hariLabels as $iso => $label)
                            <label style="display:flex; gap:6px; align-items:center; font-size:13px; cursor:pointer;">
                                <input type="checkbox" name="hari_minggu[]" value="{{ $iso }}" @checked(in_array($iso, collect(old('hari_minggu', $currentWeekend))->map(fn ($v) => (int) $v)->all(), true))>
                                {{ $label }}
                            </label>
                        @endforeach
                    </div>
                </div>
                <div class="wiz-field">
                    <label class="wiz-field__label">Masa Buka</label>
                    <input type="time" class="wiz-field__input" name="masa_buka" value="{{ old('masa_buka', $selected->masa_buka ? \Illuminate\Support\Carbon::parse($selected->masa_buka)->format('H:i') : '09:00') }}">
                </div>
                <div class="wiz-field">
                    <label class="wiz-field__label">Masa Tutup</label>
                    <input type="time" class="wiz-field__input" name="masa_tutup" value="{{ old('masa_tutup', $selected->masa_tutup ? \Illuminate\Support\Carbon::parse($selected->masa_tutup)->format('H:i') : '17:00') }}">
                </div>
                <div class="wiz-field">
                    <label class="wiz-field__label">Tempoh Slot (minit) *</label>
                    <input type="number" class="wiz-field__input" name="tempoh_slot_minit" value="{{ old('tempoh_slot_minit', $selected->tempoh_slot_minit ?: 30) }}" min="5" max="240" required>
                </div>
            </div>
            <div style="display:flex; justify-content:flex-end; margin-top:12px;">
                <button type="submit" class="btn btn--primary">Simpan Penetapan</button>
            </div>
        </form>

        {{-- Generate --}}
        <form method="POST" action="{{ route('slot.generate') }}" class="tap-card" style="margin-bottom:18px;" onsubmit="return confirm('Jana slot untuk julat tarikh ini?')">
            @csrf
            <input type="hidden" name="cawangan_id" value="{{ $selected->id }}">
            <div class="tap-card__eyebrow">Jana Slot</div>
            <div class="wiz-grid">
                <div class="wiz-field">
                    <label class="wiz-field__label">Bilik</label>
                    <select class="wiz-field__input" name="bilik_id">
                        <option value="">- Cawangan (tanpa bilik) -</option>
                        @foreach ($bilikList as $b)
                            <option value="{{ $b->id }}">{{ $b->nama_bilik }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="wiz-field">
                    <label class="wiz-field__label">Dari Tarikh *</label>
                    <input type="date" class="wiz-field__input" name="from" value="{{ old('from') }}" required>
                </div>
                <div class="wiz-field">
                    <label class="wiz-field__label">Hingga Tarikh *</label>
                    <input type="date" class="wiz-field__input" name="to" value="{{ old('to') }}" required>
                </div>
            </div>
            <div style="display:flex; justify-content:flex-end; margin-top:12px;">
                <button type="submit" class="btn btn--primary">Jana Slot</button>
            </div>
        </form>

        {{-- Teardown --}}
        <form method="POST" action="{{ route('slot.destroy') }}" class="tap-card" onsubmit="return confirm('Padam slot belum ditempah dalam julat ini?')">
            @csrf @method('DELETE')
            <input type="hidden" name="cawangan_id" value="{{ $selected->id }}">
            <div class="tap-card__eyebrow">Padam Slot (belum ditempah)</div>
            <div class="wiz-grid">
                <div class="wiz-field">
                    <label class="wiz-field__label">Bilik</label>
                    <select class="wiz-field__input" name="bilik_id">
                        <option value="">- Cawangan (tanpa bilik) -</option>
                        @foreach ($bilikList as $b)
                            <option value="{{ $b->id }}">{{ $b->nama_bilik }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="wiz-field">
                    <label class="wiz-field__label">Dari Tarikh *</label>
                    <input type="date" class="wiz-field__input" name="from" required>
                </div>
                <div class="wiz-field">
                    <label class="wiz-field__label">Hingga Tarikh *</label>
                    <input type="date" class="wiz-field__input" name="to" required>
                </div>
            </div>
            <div style="display:flex; justify-content:flex-end; margin-top:12px;">
                <button type="submit" class="btn btn--ghost" style="color:var(--danger);">Padam Slot</button>
            </div>
        </form>
    @endif
@endsection
