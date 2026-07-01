@extends('layouts.staff')

@section('title', 'Khidmat Nasihat · '.($khidmat->no_permohonan ?: $khidmat->nama_mangsa))

@php
    $alamat = collect([$khidmat->alamat_surat1, $khidmat->alamat_surat2, $khidmat->alamat_surat3, $khidmat->poskod])
        ->filter()->implode(', ');
@endphp

@section('content')
    <div class="tap-nav" style="margin: -24px -20px 18px; border-radius: 0;">
        <a href="{{ route('khidmat.index') }}" class="tap-nav__back">← Senarai Khidmat Nasihat</a>
        <span class="tap-nav__crumb">{{ $khidmat->no_permohonan ?: 'Draf' }}</span>
    </div>

    @if (session('status'))
        <div class="formerr" style="color: var(--success); background: rgba(16,185,129,0.08); border-color: rgba(16,185,129,0.18); margin-bottom:14px;">{{ session('status') }}</div>
    @endif

    <div class="tap-title" style="border:1px solid var(--line); border-radius: var(--r-lg); margin-bottom: 18px;">
        <div>
            <h1 class="tap-title__h1">{{ $khidmat->nama_mangsa ?: 'Tanpa Nama' }}<span class="dot"></span></h1>
            <p class="tap-title__sub">No. Permohonan <strong>{{ $khidmat->no_permohonan ?: '—' }}</strong> · <span class="pill pill--received">{{ str_replace('_', ' ', $khidmat->status_kn) }}</span></p>
        </div>
        @can('khidmat.manage')
            <div style="display:flex; gap:8px; flex-wrap:wrap;">
                @if ($khidmat->status_kn === \App\Models\KhidmatNasihat::STATUS_DRAF)
                    <a href="{{ route('khidmat.edit', $khidmat) }}" class="tap-head__btn">Kemaskini Draf</a>
                @endif
                @if (! in_array($khidmat->status_kn, [\App\Models\KhidmatNasihat::STATUS_DRAF, \App\Models\KhidmatNasihat::STATUS_BATAL], true))
                    <a href="{{ route('khidmat.pindah-borang', $khidmat) }}" class="tap-head__btn">⇄ Pindah Cawangan</a>
                @endif
            </div>
        @endcan
    </div>

    <div style="display:grid; grid-template-columns: 1fr 1fr; gap:24px; align-items:start;">
        <div class="tap-card">
            <div class="tap-card__eyebrow">Permohonan</div>
            <div class="tap-card__row"><div class="k">Jenis Permohonan</div><div class="v">{{ str_replace('_', ' ', $khidmat->jenis_permohonan) }}</div></div>
            <div class="tap-card__row"><div class="k">Cawangan</div><div class="v">{{ $khidmat->cawangan->nama ?? '—' }}</div></div>
            <div class="tap-card__row"><div class="k">Kategori</div><div class="v">{{ $khidmat->kategori->jenis_kategori ?? '—' }}</div></div>
            <div class="tap-card__row"><div class="k">Subkategori</div><div class="v">{{ $khidmat->subkategori->nama ?? '—' }}</div></div>
            <div class="tap-card__row"><div class="k">Jenis Kes</div><div class="v">{{ $khidmat->jenis_kes ?: '—' }}</div></div>
            <div class="tap-card__row"><div class="k">Wakil</div><div class="v">{{ $khidmat->nama_wakil ?: '—' }}</div></div>
        </div>

        @if ($khidmat->jenis_permohonan === 'SEBAGAI_WAKIL')
            @php $mahkamah = $khidmat->mahkamah(); @endphp
            <div class="tap-card">
                <div class="tap-card__eyebrow">Wakil &amp; Diwakili</div>
                <div class="tap-card__row"><div class="k">Konteks Wakil</div><div class="v">{{ $khidmat->jenis_wakil ?: '—' }}</div></div>
                <div class="tap-card__row"><div class="k">Nama Wakil</div><div class="v">{{ $khidmat->nama_wakil ?: '—' }} {{ $khidmat->jawatan_wakil ? '· '.$khidmat->jawatan_wakil : '' }}</div></div>
                <div class="tap-card__row"><div class="k">No. Pengenalan Wakil</div><div class="v">{{ $khidmat->no_pengenalan_wakil ?: '—' }}</div></div>
                <div class="tap-card__row"><div class="k">Orang Diwakili</div><div class="v">{{ $khidmat->nama_diwakili ?: '—' }} {{ $khidmat->id_pengenalan_diwakili ? '('.$khidmat->id_pengenalan_diwakili.')' : '' }}</div></div>
                @if ($khidmat->jenis_wakil === 'MAHKAMAH')
                    <div class="tap-card__row"><div class="k">Mahkamah</div><div class="v">{{ $mahkamah->nama_mahkamah ?? '—' }} {{ $khidmat->jenis_mahkamah_pihak ? '('.$khidmat->jenis_mahkamah_pihak.')' : '' }}</div></div>
                @endif
            </div>
        @endif

        <div class="tap-card">
            <div class="tap-card__eyebrow">Mangsa</div>
            <div class="tap-card__row"><div class="k">No. Pengenalan</div><div class="v">{{ $khidmat->id_pengenalan_mangsa ?: '—' }} {{ $khidmat->jenis_pengenalan_mangsa ? '('.$khidmat->jenis_pengenalan_mangsa.')' : '' }}</div></div>
            <div class="tap-card__row"><div class="k">Jantina</div><div class="v">{{ $khidmat->jantina_mangsa ?: '—' }}</div></div>
            <div class="tap-card__row"><div class="k">Umur</div><div class="v">{{ $khidmat->umur_mangsa ?: '—' }}</div></div>
            <div class="tap-card__row"><div class="k">Bangsa / Agama</div><div class="v">{{ $khidmat->bangsa ?: '—' }} {{ $khidmat->agama ? '· '.$khidmat->agama : '' }}</div></div>
            <div class="tap-card__row"><div class="k">Tarikh Lahir</div><div class="v">{{ optional($khidmat->tarikh_lahir_mangsa)->format('d/m/Y') ?: '—' }}</div></div>
            <div class="tap-card__row"><div class="k">Alamat Surat</div><div class="v">{{ $alamat ?: '—' }}</div></div>
        </div>

        <div class="tap-card">
            <div class="tap-card__eyebrow">Bayaran</div>
            <div class="tap-card__row"><div class="k">Jumlah</div><div class="v">RM {{ number_format($khidmat->jumlah_bayaran, 2) }}</div></div>
            <div class="tap-card__row"><div class="k">Status Bayaran</div><div class="v">{{ $khidmat->status_bayaran ? 'Sudah Bayar' : 'Belum Bayar' }}</div></div>
            <div class="tap-card__row"><div class="k">Percuma</div><div class="v">{{ $khidmat->is_percuma ? 'Ya' : 'Tidak' }}</div></div>
            <div class="tap-card__row"><div class="k">Perakuan</div><div class="v">{{ $khidmat->perakuan ? 'Ya' : 'Tidak' }}</div></div>

            {{-- W2: manual iPayment — record a counter payment of the intake fee. --}}
            @if (! $khidmat->is_percuma && (float) $khidmat->jumlah_bayaran > 0 && ! $khidmat->status_bayaran && auth()->user()?->can('khidmat.proses'))
                <form method="POST" action="{{ route('khidmat.bayar', $khidmat) }}" enctype="multipart/form-data" style="margin-top:12px; display:grid; gap:8px;">
                    @csrf
                    <div class="tap-card__eyebrow">Rekod Bayaran (Kaunter)</div>
                    <input type="text" name="nombor_resit" placeholder="No. Resit" aria-label="No. Resit" required class="wiz-field__input">
                    <input type="date" name="tarikh_resit" required class="wiz-field__input">
                    <select name="kaedah_bayaran" required class="wiz-field__select">
                        <option value="TUNAI">Tunai</option>
                        <option value="KAD">Kad</option>
                        <option value="BANK_IN">Bank-In</option>
                        <option value="EWALLET">e-Wallet</option>
                        <option value="IPAYMENT">iPayment</option>
                    </select>
                    <input type="text" name="rujukan_bayaran" placeholder="Rujukan (pilihan)" aria-label="Rujukan (pilihan)" class="wiz-field__input">
                    <input type="file" name="lampiran_resit" accept=".pdf,.jpg,.jpeg,.png" class="wiz-field__input">
                    <button type="submit" class="btn btn--primary">Rekod Bayaran</button>
                    @error('nombor_resit') <div style="color:var(--danger); font-size:12px;">{{ $message }}</div> @enderror
                </form>
            @endif
        </div>

        <div class="tap-card">
            <div class="tap-card__eyebrow">Janji Temu</div>
            @if ($khidmat->temuJanji)
                <div class="tap-card__row"><div class="k">Tarikh</div><div class="v">{{ optional($khidmat->temuJanji->tarikh_temu_janji)->format('d/m/Y') ?: '—' }}</div></div>
                <div class="tap-card__row"><div class="k">Masa</div><div class="v">{{ \Illuminate\Support\Str::of($khidmat->temuJanji->masa_mula)->substr(0, 5) }} – {{ \Illuminate\Support\Str::of($khidmat->temuJanji->masa_akhir)->substr(0, 5) }}</div></div>
                <div class="tap-card__row"><div class="k">Status</div><div class="v"><span class="pill pill--received">{{ str_replace('_', ' ', $khidmat->temuJanji->status) }}</span></div></div>
            @else
                <div class="tap-card__row"><div class="k">Temu Janji</div><div class="v">Belum ditetapkan</div></div>
            @endif
        </div>

        <div class="tap-card">
            <div class="tap-card__eyebrow">Ulasan & Rekod</div>
            <div class="tap-card__row"><div class="k">Ulasan Permohonan</div><div class="v">{{ $khidmat->ulasan_permohonan ?: '—' }}</div></div>
            <div class="tap-card__row"><div class="k">Ulasan Pegawai</div><div class="v">{{ $khidmat->ulasan_pegawai ?: '—' }}</div></div>
            @if ($khidmat->cipta_oleh)
                <div class="tap-card__row"><div class="k">Dicipta Oleh</div><div class="v">{{ $khidmat->cipta_oleh }} · {{ optional($khidmat->created_at)->format('d/m/Y') }}</div></div>
            @endif
            @if ($khidmat->kemaskini_oleh)
                <div class="tap-card__row"><div class="k">Kemaskini Oleh</div><div class="v">{{ $khidmat->kemaskini_oleh }} · {{ optional($khidmat->updated_at)->format('d/m/Y') }}</div></div>
            @endif
        </div>
    </div>
@endsection
