@extends('layouts.staff')

@section('title', 'Agihan Kes #'.$kes->id)

@section('content')
<style>
    .ag-grid { display:grid; grid-template-columns:1fr 1fr; gap:14px 18px; }
    .ag-grid .col-2 { grid-column:1/-1; }
    .ag-row { display:flex; justify-content:space-between; gap:12px; padding:6px 0; border-bottom:1px solid var(--line); }
    .ag-row .k { color:var(--mute); font-size:13px; } .ag-row .v { font-weight:600; font-size:13px; text-align:right; }
    .ag-badge { display:inline-block; padding:4px 12px; border-radius:999px; background:rgba(26,111,168,.12); color:var(--brand,#1a6fa8); font-weight:600; font-size:12px; }
    .ag-sec { margin:22px 0 10px; font-weight:600; padding-bottom:6px; border-bottom:1px solid var(--line); }
    .ag-time { font-size:12px; }
    .ag-time li { padding:6px 0; border-bottom:1px dashed var(--line); }
    .radio-row { display:flex; gap:18px; align-items:center; } .radio-row label { display:flex; gap:6px; align-items:center; font-size:13px; }
    .req { color:var(--danger,#dc2626); }
</style>

<div class="tap-head">
    <div>
        <h1 class="tap-head__title">Agihan Kes #{{ $kes->id }}<span class="dot"></span></h1>
        <p class="tap-head__sub">No. Fail: {{ $kes->no_fail ?: '—' }}</p>
    </div>
    <a href="{{ route('kes.show', $kes) }}" class="btn btn--ghost">← Kes</a>
</div>

@if (session('status'))
    <div class="formerr" style="color:var(--success);background:rgba(16,185,129,.08);border-color:rgba(16,185,129,.18);margin-bottom:16px;">{{ session('status') }}</div>
@endif
@if ($errors->any())
    <div class="formerr" style="margin-bottom:16px;">{{ $errors->first() }}</div>
@endif

<div class="tap-card">
    <div class="ag-grid">
        <div class="col-2"><span class="ag-badge">{{ $statusLabel }}</span></div>
        <div class="ag-row"><div class="k">OYD</div><div class="v">{{ $kes->nama ?: '—' }}</div></div>
        <div class="ag-row"><div class="k">No. K/P OYD</div><div class="v">{{ $kes->nokp ?: '—' }}</div></div>
        <div class="ag-row"><div class="k">Cawangan</div><div class="v">{{ $kes->cawangan ?: '—' }}</div></div>
        <div class="ag-row"><div class="k">Peguam Semasa</div><div class="v">{{ $kes->nama_pegawai_yang_dapat_kes ?: '—' }}</div></div>
    </div>

    @if ($rec && $rec->nama_peguampanel)
        <div class="ag-sec">Pemilihan PPUU Semasa</div>
        <div class="ag-grid">
            <div class="ag-row"><div class="k">Peguam Dipilih</div><div class="v">{{ $rec->nama_peguampanel }}</div></div>
            <div class="ag-row"><div class="k">Pilihan</div><div class="v">{{ $rec->pilihan_Agihan === 'B' ? 'B — Negeri Lain' : 'A — Cawangan Sendiri' }} {{ $rec->cawangan_peguampanel ? '('.$rec->cawangan_peguampanel.')' : '' }}</div></div>
            @if ($rec->ulasanPPUU)<div class="ag-row col-2"><div class="k">Ulasan PPUU</div><div class="v">{{ $rec->ulasanPPUU }}</div></div>@endif
            @if ($rec->ulasanPengarah)<div class="ag-row col-2"><div class="k">Ulasan Pengarah</div><div class="v">{{ $rec->ulasanPengarah }}</div></div>@endif
        </div>
    @endif
</div>

{{-- ===== Role-routed action form ===== --}}
@if ($stage === 'belum_masuk')
    <div class="tap-card" style="margin-top:18px;">
        <div class="ag-sec" style="margin-top:0;">Hantar ke Proses Agihan</div>
        <p class="dash-empty__sub" style="margin:0 0 12px;">Kes ini belum dalam proses agihan. Hantar ke spine agihan berperingkat (PPUU → Pengarah → Ketua Pengarah).</p>
        <form method="POST" action="{{ route('agihan.masuk', $kes) }}">
            @csrf
            <button type="submit" class="btn btn--primary">Hantar ke Agihan</button>
        </form>
    </div>

@elseif ($stage === 'ditolak_pengarah')
    <div class="tap-card" style="margin-top:18px;">
        <div class="ag-sec" style="margin-top:0;">Tindakan Pengarah — Agihan Ditolak</div>
        <p class="dash-empty__sub" style="margin:0 0 12px;">Agihan kes ini telah ditolak. Buka semula untuk pertimbangan baharu, atau batalkan agihan (kes kekal dalam rekod tanpa peguam).</p>
        <form method="POST" action="{{ route('agihan.buka-semula', $kes) }}">
            @csrf
            <div class="field col-2">
                <label class="field__label">Ulasan (pilihan)</label>
                <input type="text" name="ulasan" class="field__input" maxlength="255" placeholder="Sebab buka semula">
            </div>
            <button type="submit" class="btn btn--primary" style="margin-top:12px;">Buka Semula untuk Agihan</button>
        </form>
        <form method="POST" action="{{ route('agihan.batal', $kes) }}" style="margin-top:18px;border-top:1px solid var(--line);padding-top:16px;">
            @csrf
            <div class="field col-2">
                <label class="field__label">Sebab Pembatalan <span class="req">*</span></label>
                <input type="text" name="sebab" class="field__input" maxlength="255" required placeholder="Sebab kes tidak akan diagih peguam">
            </div>
            <button type="submit" class="btn btn--ghost" style="margin-top:12px;">Batalkan Agihan</button>
        </form>
    </div>

@elseif ($stage === 'pengarah_baru')
    <div class="tap-card" style="margin-top:18px;">
        <div class="ag-sec" style="margin-top:0;">Tindakan Pengarah — Agihan Baru</div>
        <form method="POST" action="{{ route('agihan.pengarah.terima', $kes) }}">
            @csrf
            <div class="field col-2">
                <label class="field__label">Serah kepada PPUU <span class="req">*</span></label>
                <select name="idPPUU" class="field__input" required>
                    <option value="" disabled selected>Pilih PPUU…</option>
                    @foreach ($ppuuList as $p)
                        <option value="{{ $p->id }}">{{ $p->name }} ({{ $p->role }})</option>
                    @endforeach
                </select>
            </div>
            <button type="submit" class="btn btn--primary" style="margin-top:12px;">Terima &amp; Serah ke PPUU</button>
        </form>
        <form method="POST" action="{{ route('agihan.pengarah.tolak', $kes) }}" style="margin-top:18px;border-top:1px solid var(--line);padding-top:16px;">
            @csrf
            <div class="field col-2">
                <label class="field__label">Sebab Penolakan <span class="req">*</span></label>
                <input type="text" name="sebab" class="field__input" maxlength="255" required placeholder="Nyatakan sebab tolak agihan baru">
            </div>
            <button type="submit" class="btn btn--ghost" style="margin-top:12px;">Tolak Agihan</button>
        </form>
    </div>

@elseif ($stage === 'ppuu_pilih')
    <div class="tap-card" style="margin-top:18px;">
        <div class="ag-sec" style="margin-top:0;">Tindakan PPUU — Pemilihan Peguam Panel</div>
        <form method="POST" action="{{ route('agihan.ppuu.pilih', $kes) }}">
            @csrf
            <div class="ag-grid">
                <div class="field col-2">
                    <label class="field__label">Peguam Panel <span class="req">*</span></label>
                    <select name="peguam_id" class="field__input" required>
                        <option value="" disabled selected>Pilih peguam…</option>
                        {{-- W11: workload-ranked shortlist (least-loaded first). --}}
                        @isset($peguamShortlist)
                            <optgroup label="Disyorkan (beban paling rendah)">
                                @foreach ($peguamShortlist as $p)
                                    <option value="{{ $p['id'] }}">{{ $p['nama'] }} — {{ $p['firma'] ?: 'Firma tidak dinyatakan' }} · beban: {{ $p['beban'] }}</option>
                                @endforeach
                            </optgroup>
                        @endisset
                        <optgroup label="Semua peguam panel">
                            @foreach ($peguamList as $p)
                                <option value="{{ $p->id }}">{{ $p->nama_peguam }} — {{ $p->nama_firma ?: 'Firma tidak dinyatakan' }}</option>
                            @endforeach
                        </optgroup>
                    </select>
                </div>
                <div class="field">
                    <label class="field__label">Pilihan Agihan <span class="req">*</span></label>
                    <div class="radio-row">
                        <label><input type="radio" name="pilihan" value="A" checked> A — Cawangan Sendiri</label>
                        <label><input type="radio" name="pilihan" value="B"> B — Negeri Lain</label>
                    </div>
                </div>
                <div class="field">
                    <label class="field__label">Negeri (jika Pilihan B)</label>
                    <input type="text" name="cawangan" class="field__input" maxlength="100">
                </div>
                <div class="field col-2">
                    <label class="field__label">Ulasan / Syor PPUU</label>
                    <textarea name="ulasan" class="field__input" rows="2" maxlength="350"></textarea>
                </div>
            </div>
            <button type="submit" class="btn btn--primary" style="margin-top:12px;">Hantar untuk Sokongan Pengarah</button>
        </form>
    </div>

@elseif ($stage === 'pengarah_sokong')
    <div class="tap-card" style="margin-top:18px;">
        <div class="ag-sec" style="margin-top:0;">Tindakan Pengarah — Sokongan Pemilihan</div>
        <form method="POST" action="{{ route('agihan.pengarah.keputusan', $kes) }}">
            @csrf
            <div class="field col-2">
                <label class="field__label">Keputusan <span class="req">*</span></label>
                <div class="radio-row">
                    <label><input type="radio" name="keputusan" value="sokong" checked> Disokong → Ketua Pengarah</label>
                    <label><input type="radio" name="keputusan" value="tidak"> Tidak Disokong → PPUU</label>
                </div>
            </div>
            <div class="field col-2" style="margin-top:10px;">
                <label class="field__label">Ulasan (wajib jika tidak disokong)</label>
                <textarea name="ulasan" class="field__input" rows="2" maxlength="600"></textarea>
            </div>
            <button type="submit" class="btn btn--primary" style="margin-top:12px;">Rekod Keputusan</button>
        </form>
    </div>

@elseif ($stage === 'kp_keputusan')
    <div class="tap-card" style="margin-top:18px;">
        <div class="ag-sec" style="margin-top:0;">Tindakan Ketua Pengarah — Kelulusan Muktamad</div>
        <form method="POST" action="{{ route('agihan.kp.keputusan', $kes) }}">
            @csrf
            <div class="field col-2">
                <label class="field__label">Keputusan <span class="req">*</span></label>
                <div class="radio-row">
                    <label><input type="radio" name="keputusan" value="lulus" checked> Diluluskan → Tawar ke Peguam</label>
                    <label><input type="radio" name="keputusan" value="tolak"> Tidak Diluluskan → PPUU</label>
                </div>
            </div>
            <div class="field col-2" style="margin-top:10px;">
                <label class="field__label">Ulasan (wajib jika tidak diluluskan)</label>
                <textarea name="ulasan" class="field__input" rows="2" maxlength="200"></textarea>
            </div>
            <button type="submit" class="btn btn--primary" style="margin-top:12px;">Rekod Keputusan</button>
        </form>
    </div>

@else
    <div class="tap-card" style="margin-top:18px;">
        <div class="dash-empty__sub" style="padding:8px 0;">Tiada tindakan agihan untuk peranan anda pada status semasa kes ini.</div>
    </div>
@endif

{{-- ===== History ===== --}}
@if ($sejarahPpuu->isNotEmpty() || $sejarahPp->isNotEmpty())
    <div class="tap-card" style="margin-top:18px;">
        <div class="ag-sec" style="margin-top:0;">Sejarah Agihan</div>
        <ul class="ag-time" style="list-style:none;padding:0;margin:0;">
            @foreach ($sejarahPpuu as $s)
                <li>[PPUU] {{ \App\Support\StatusAgihan::label($s->statusAgihan) }}
                    @if ($s->nama_peguampanel) · {{ $s->nama_peguampanel }} @endif
                    · <span style="color:var(--mute);">{{ optional($s->createdDate ?? $s->modifiedDate)->format('d/m/Y H:i') }} · {{ $s->status_rekod }}</span></li>
            @endforeach
            @foreach ($sejarahPp as $s)
                <li>[Peguam] {{ \App\Support\StatusAgihan::label($s->status_agihan) }} · {{ $s->nama_pp_lama ?: '—' }}
                    @if ($s->alasan) — {{ $s->alasan }} @endif
                    · <span style="color:var(--mute);">kali {{ $s->permohonan_kali ?: '—' }}</span></li>
            @endforeach
        </ul>
    </div>
@endif
@endsection
