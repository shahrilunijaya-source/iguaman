@extends('layouts.peguam')

@section('title', 'Profil')

@section('content')
    <div class="tap-head">
        <div>
            <h1 class="tap-head__title">Profil Peguam<span class="dot"></span></h1>
            <p class="tap-head__sub">Maklumat panel &amp; akaun</p>
        </div>
        <a href="{{ route('peguam.profil.edit') }}" class="btn btn--primary">✎ Kemaskini Profil</a>
    </div>

    @if (session('status'))
        <div class="formerr" style="color: var(--success); background: rgba(16,185,129,0.08); border-color: rgba(16,185,129,0.18); margin-bottom: 16px;">
            {{ session('status') }}
        </div>
    @endif

    <div style="display:grid; grid-template-columns: 1fr 1fr; gap:18px;">
        <div class="tap-card">
            <div class="tap-card__eyebrow">Akaun</div>
            <div class="tap-card__row"><div class="k">Nama</div><div class="v">{{ $user->name }}</div></div>
            <div class="tap-card__row"><div class="k">Emel</div><div class="v">{{ $user->email }}</div></div>
            <div class="tap-card__row"><div class="k">ID Peguam</div><div class="v">{{ $user->id_peguam_panel ?: '-' }}</div></div>
            <div class="tap-card__row"><div class="k">Log Masuk Terakhir</div><div class="v">{{ optional($user->last_login_at)->format('d/m/Y H:i') ?: '-' }}</div></div>
        </div>

        <div class="tap-card">
            <div class="tap-card__eyebrow">Rekod Panel</div>
            @if ($profile)
                <div class="tap-card__row"><div class="k">Nama Peguam</div><div class="v">{{ $profile->nama_peguam }}</div></div>
                <div class="tap-card__row"><div class="k">No. KP</div><div class="v">{{ $profile->kp_peguam }}</div></div>
                <div class="tap-card__row"><div class="k">Telefon</div><div class="v">{{ $profile->tel_peguam ?: '-' }}</div></div>
                <div class="tap-card__row"><div class="k">Emel</div><div class="v">{{ $profile->emel_peguam ?: '-' }}</div></div>
                <div class="tap-card__row"><div class="k">Firma</div><div class="v">{{ $profile->nama_firma ?: '-' }}</div></div>
                <div class="tap-card__row"><div class="k">Alamat Firma</div><div class="v">{{ trim(($profile->alamat_firma_1 ?? '').' '.($profile->alamat_firma_2 ?? '').' '.($profile->negeri_firma ?? '')) ?: '-' }}</div></div>
            @else
                <div class="dash-empty__sub" style="padding:8px 0;">Akaun belum dipautkan ke rekod peguam panel.</div>
            @endif
        </div>
    </div>

    @php
        $statusLabel = [
            \App\Models\ButiranPeguamPanel6::LEGACY_AKTIF => 'Aktif',
            \App\Models\ButiranPeguamPanel6::AKTIF => 'Aktif',
            \App\Models\ButiranPeguamPanel6::DROP_MOHON => 'Mohon Gugur (menunggu)',
            \App\Models\ButiranPeguamPanel6::ADD_MOHON => 'Mohon Tambah (menunggu)',
            \App\Models\ButiranPeguamPanel6::DROP_DISOKONG => 'Gugur - disokong Pengarah',
            \App\Models\ButiranPeguamPanel6::ADD_DISOKONG => 'Tambah - disokong Pengarah',
        ];
    @endphp
    <div style="margin-top:18px;">
        <div class="tap-card__eyebrow" style="margin-bottom:12px;">Bidang Pengkhususan</div>
        <div class="tap-card">
            @forelse ($pengkhususan as $row)
                <div class="tap-card__row">
                    <div class="k">{{ $row->category }} - {{ $row->checkbox_value }}</div>
                    <div class="v" style="display:flex;gap:10px;align-items:center;">
                        <span style="font-size:11px;color:var(--mute);">{{ $statusLabel[$row->checkbox_value_status] ?? '-' }}</span>
                        @if (in_array($row->checkbox_value_status, \App\Models\ButiranPeguamPanel6::AKTIF_STATES, true))
                            <form method="POST" action="{{ route('peguam.pengkhususan.drop', $row) }}" onsubmit="return confirm('Mohon gugur bidang ini?');">
                                @csrf
                                <button type="submit" class="btn btn--ghost" style="padding:3px 10px;font-size:11px;color:#dc2626;border-color:#dc2626;">Mohon Gugur</button>
                            </form>
                        @endif
                    </div>
                </div>
            @empty
                <div class="dash-empty__sub" style="padding:6px 0;">Tiada bidang pengkhususan direkodkan.</div>
            @endforelse

            <form method="POST" action="{{ route('peguam.pengkhususan.add') }}" style="margin-top:14px;border-top:1px solid var(--line);padding-top:14px;display:flex;gap:10px;flex-wrap:wrap;align-items:flex-end;">
                @csrf
                <div class="field" style="flex:1;min-width:240px;">
                    <label class="field__label">Mohon Tambah Bidang</label>
                    <select name="bidang_pick" class="field__input" required onchange="const o=this.selectedOptions[0];document.getElementById('pkCat').value=o.dataset.cat||'';document.getElementById('pkVal').value=o.value;">
                        <option value="" disabled selected>Pilih bidang…</option>
                        @foreach ($kategoriMap as $code => $label)
                            @if (($bidang[$code] ?? collect())->isNotEmpty())
                                <optgroup label="{{ $label }}">
                                    @foreach ($bidang[$code] as $b2)
                                        <option value="{{ $b2->deskripsi }}" data-cat="{{ $label }}">{{ $b2->deskripsi }}</option>
                                    @endforeach
                                </optgroup>
                            @endif
                        @endforeach
                    </select>
                    <input type="hidden" name="category" id="pkCat">
                    <input type="hidden" name="checkbox_value" id="pkVal">
                </div>
                <button type="submit" class="btn btn--primary">Mohon Tambah</button>
            </form>
        </div>
    </div>

    <div style="margin-top:18px;">
        <div class="tap-card__eyebrow" style="margin-bottom:12px;">Profil Terperinci</div>
        @include('peguam-panel._butiran', ['b' => $b])
    </div>
@endsection
