@extends('layouts.staff')

@section('title', 'Permohonan · '.$p->namaPeguam)

@section('content')
    <div class="tap-nav" style="margin: -24px -20px 18px; border-radius: 0;">
        <a href="{{ route('permohonan-peguam.index') }}" class="tap-nav__back">← Senarai Permohonan</a>
        <span class="tap-nav__crumb">{{ $p->namaPeguam }}</span>
        <div class="tap-nav__cluster">
            <span class="tap-nav__step">{{ $statusLabels[$p->permohonan_status] ?? 'Baharu' }}</span>
        </div>
    </div>

    @if (session('status'))
        <div class="formerr" style="color: var(--success); background: rgba(16,185,129,0.08); border-color: rgba(16,185,129,0.18); margin-bottom:14px;">{{ session('status') }}</div>
    @endif
    @if ($errors->any())
        <div class="formerr" style="margin-bottom:14px;">{{ $errors->first() }}</div>
    @endif

    <div style="display:grid; grid-template-columns: 1fr 340px; gap:24px; align-items:start;">
        <div class="tap-card">
            <div class="tap-card__eyebrow">Maklumat Pemohon</div>
            <div class="tap-card__row"><div class="k">Nama</div><div class="v">{{ $p->namaPeguam }}</div></div>
            <div class="tap-card__row"><div class="k">No. KP</div><div class="v">{{ $p->kpBaru }}</div></div>
            <div class="tap-card__row"><div class="k">Telefon</div><div class="v">{{ $p->noTelBimbit ?: '—' }}</div></div>
            <div class="tap-card__row"><div class="k">Emel</div><div class="v">{{ $p->emelPeguam ?: '—' }}</div></div>
            <div class="tap-card__row"><div class="k">Kelulusan Akademik</div><div class="v">{{ $p->kelulusanAkademik ?: '—' }}</div></div>
            <div class="tap-card__row"><div class="k">Pengalaman</div><div class="v">{{ $p->tahunPengalaman ?: '0' }} tahun · {{ $p->bilanganKes ?: '0' }} kes</div></div>
            <div class="tap-card__row"><div class="k">Keterangan Kes</div><div class="v">{{ $p->keteranganKes ?: '—' }}</div></div>
            <div class="tap-card__row"><div class="k">Tarikh Mohon</div><div class="v">{{ optional($p->tarikhMohon)->format('d/m/Y') ?: '—' }}</div></div>
            @if ($p->permohonan_status === '2' && $p->sebabTidakDiluluskan)
                <div class="tap-card__row"><div class="k">Sebab Tolak</div><div class="v">{{ $p->sebabTidakDiluluskan }}</div></div>
            @endif
            @if ($p->permohonan_status === '3' && $p->sebabBatal)
                <div class="tap-card__row"><div class="k">Sebab Tarik Diri</div><div class="v">{{ $p->sebabBatal }}</div></div>
            @endif
        </div>

        <div style="display:flex; flex-direction:column; gap:14px;">
            {{-- Pengarah endorsement --}}
            <div class="tap-card">
                <div class="tap-card__eyebrow">Sokongan Pengarah</div>
                @if ($p->sokonganPengarah !== null && $p->sokonganPengarah !== '')
                    <p class="vb-sub" style="margin:0 0 10px;">
                        <strong>{{ $p->sokonganPengarah === '1' ? 'Disokong' : 'Tidak Disokong' }}</strong>
                        @if ($p->ulasan_sokonganPengarah) — {{ $p->ulasan_sokonganPengarah }} @endif
                    </p>
                @endif
                @if (auth()->user()->hasRole('pengarah', 'admin'))
                    <form method="POST" action="{{ route('permohonan-peguam.sokong', $p) }}" class="va-form">
                        @csrf
                        <select name="sokonganPengarah" class="field__input" required>
                            <option value="1">Sokong</option>
                            <option value="0">Tidak Sokong</option>
                        </select>
                        <input class="field__input" name="ulasan_sokonganPengarah" placeholder="Ulasan (pilihan)" maxlength="600">
                        <button type="submit" class="btn btn--primary btn--block">Rekod Sokongan</button>
                    </form>
                @else
                    <p class="dash-empty__sub">Hanya Pengarah.</p>
                @endif
            </div>

            {{-- KP/Admin decision --}}
            <div class="tap-card">
                <div class="tap-card__eyebrow">Keputusan</div>
                @if (auth()->user()->hasRole('admin', 'koordinator'))
                    <form method="POST" action="{{ route('permohonan-peguam.keputusan', $p) }}" class="va-form">
                        @csrf
                        <select name="keputusan" class="field__input" required>
                            <option value="lulus">Lulus (tambah ke panel)</option>
                            <option value="tolak">Tidak Lulus</option>
                        </select>
                        <input class="field__input" name="ulasan" placeholder="Ulasan (pilihan)" maxlength="200">
                        <button type="submit" class="btn btn--primary btn--block">Simpan Keputusan</button>
                    </form>
                @else
                    <p class="dash-empty__sub">Hanya Admin / Koordinator.</p>
                @endif
            </div>

            {{-- Tarik diri --}}
            <div class="tap-card">
                <div class="tap-card__eyebrow">Tarik Diri</div>
                <form method="POST" action="{{ route('permohonan-peguam.tarik', $p) }}" class="va-form" onsubmit="return confirm('Rekod tarik diri?')">
                    @csrf
                    <input class="field__input" name="sebabBatal" placeholder="Sebab (pilihan)" maxlength="200">
                    <button type="submit" class="btn btn--ghost btn--block">Rekod Tarik Diri</button>
                </form>
            </div>
        </div>
    </div>
@endsection
