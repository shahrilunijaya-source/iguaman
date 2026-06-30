@extends('layouts.staff')

@section('title', 'Dashboard')

@php
    $canSelenggara = auth()->user()->can('menu.selenggara');

    $modules = array_filter([
        ['route' => 'kes.index', 'icon' => '▤', 'label' => 'Senarai Kes', 'desc' => 'Rekod & cari kes', 'show' => true],
        ['route' => 'kes.create', 'icon' => '＋', 'label' => 'Permohonan Baharu', 'desc' => 'Intake 5-peringkat', 'show' => true],
        ['route' => 'oyd.index', 'icon' => '☺', 'label' => 'OYD', 'desc' => 'Orang Yang Dibantu', 'show' => true],
        ['route' => 'statistik.index', 'icon' => '▦', 'label' => 'Statistik', 'desc' => 'Carta & eksport', 'show' => true],
        ['route' => 'permohonan-peguam.index', 'icon' => '▧', 'label' => 'Permohonan Peguam', 'desc' => 'Sokong & keputusan', 'show' => true],
        ['route' => 'agihan.beban', 'icon' => '▥', 'label' => 'Beban Tugas', 'desc' => 'Beban peguam panel', 'show' => true],
        ['route' => 'pegawai.index', 'icon' => '☰', 'label' => 'Pegawai JBG', 'desc' => 'Daftar pegawai', 'show' => $canSelenggara],
        ['route' => 'audit.index', 'icon' => '≣', 'label' => 'Log Audit', 'desc' => 'Jejak perubahan', 'show' => $canSelenggara],
    ], fn ($m) => $m['show']);

    $auditTone = ['INSERT' => '#00B8A9', 'APPROVE' => '#00B8A9', 'UPDATE' => '#C98A00', 'REJECT' => '#D14343', 'DELETE' => '#D14343'];
@endphp

@section('content')
    <div class="dash-greet">
        <div>
            <h1 class="dash-greet__h1">Selamat datang, {{ auth()->user()->name }}.<span class="dot"></span></h1>
            <p class="dash-greet__sub">Ruang kerja <strong>iGuaman 2in1</strong> — rekod kes &amp; panel peguam dalam satu sistem.</p>
        </div>
    </div>

    {{-- ===== KPIs ===== --}}
    <div class="dash-sec">
        <div class="dash-sec__head">
            <span class="dash-sec__eyebrow">Ringkasan</span>
            <a href="{{ route('statistik.index') }}" class="dash-sec__cta">Statistik penuh →</a>
        </div>
        <div class="dash-kpis">
            <a href="{{ route('kes.index') }}" class="dash-kpi" style="text-decoration:none; color:inherit;">
                <div class="dash-kpi__eyebrow">Jumlah Kes</div>
                <div class="dash-kpi__value">{{ number_format($stats['kes']) }}</div>
                <div class="dash-kpi__sub">{{ number_format($stats['kes_aktif']) }} aktif · {{ number_format($stats['kes_tutup']) }} ditutup</div>
            </a>
            <a href="{{ route('agihan.beban') }}" class="dash-kpi {{ $stats['belum_agih'] > 0 ? 'is-warn' : '' }}" style="text-decoration:none; color:inherit;">
                <div class="dash-kpi__eyebrow">Belum Diagih</div>
                <div class="dash-kpi__value">{{ number_format($stats['belum_agih']) }}</div>
                <div class="dash-kpi__sub">{{ number_format($stats['peguam']) }} peguam panel aktif</div>
            </a>
            <a href="{{ route('permohonan-peguam.index', ['status' => '0']) }}" class="dash-kpi {{ $stats['mohon_peguam'] > 0 ? 'is-warn' : '' }}" style="text-decoration:none; color:inherit;">
                <div class="dash-kpi__eyebrow">Permohonan Peguam</div>
                <div class="dash-kpi__value">{{ number_format($stats['mohon_peguam']) }}</div>
                <div class="dash-kpi__sub">menunggu keputusan</div>
            </a>
            <a href="{{ route('oyd.index') }}" class="dash-kpi" style="text-decoration:none; color:inherit;">
                <div class="dash-kpi__eyebrow">Rekod OYD</div>
                <div class="dash-kpi__value">{{ number_format($stats['oyd']) }}</div>
                <div class="dash-kpi__sub">{{ number_format($stats['pengguna']) }} pengguna staf</div>
            </a>
        </div>
    </div>

    {{-- ===== Perlu Tindakan ===== --}}
    @if ($perluTindakan->isNotEmpty())
        <div class="tap-card" style="margin-bottom:18px; border-left:3px solid #C98A00;">
            <div class="tap-card__eyebrow" style="color:#C98A00;">⚠ Perlu Tindakan ({{ $perluTindakan->count() }})</div>
            <p class="dash-empty__sub" style="margin:0 0 8px;">Kes belum diagih atau permohonan Peguam Panel ditolak — perlu kemaskini.</p>
            @foreach ($perluTindakan as $k)
                <a href="{{ route('agihan.maklumat', $k->id) }}" class="tap-card__row" style="text-decoration:none; align-items:center;">
                    <div class="k">{{ $k->no_fail ?: '#'.$k->id }} · {{ $k->nama ?: 'Tanpa Nama' }} <span style="color:var(--mute); font-size:11px;">· {{ $k->cawangan ?: '—' }}</span></div>
                    <div class="v" style="text-align:right;"><span class="pill pill--overdue">{{ in_array($k->status_agihan, ['9','14']) ? 'PP Ditolak' : 'Belum Diagih' }}</span></div>
                </a>
            @endforeach
        </div>
    @endif

    {{-- ===== Module launcher ===== --}}
    <div class="dash-sec">
        <div class="dash-sec__head">
            <span class="dash-sec__eyebrow">Modul</span>
        </div>
        <div style="display:grid; grid-template-columns: repeat(4, 1fr); gap:14px;">
            @foreach ($modules as $m)
                <a href="{{ route($m['route']) }}" style="text-decoration:none; color:inherit; border:1px solid var(--line); border-radius:var(--r-lg); padding:16px; display:flex; gap:12px; align-items:center; background:#fff; transition:border-color .15s, box-shadow .15s;"
                   onmouseover="this.style.borderColor='var(--teal)'; this.style.boxShadow='0 0 0 3px rgba(0,184,169,0.1)';"
                   onmouseout="this.style.borderColor='var(--line)'; this.style.boxShadow='none';">
                    <span style="width:38px; height:38px; flex:none; border-radius:10px; background:var(--paper-2); color:var(--pine-deep); display:flex; align-items:center; justify-content:center; font-size:18px;">{{ $m['icon'] }}</span>
                    <span style="min-width:0;">
                        <span style="display:block; font-weight:600; font-size:13.5px; color:var(--ink);">{{ $m['label'] }}</span>
                        <span style="display:block; font-size:11.5px; color:var(--mute);">{{ $m['desc'] }}</span>
                    </span>
                </a>
            @endforeach
        </div>
    </div>

    {{-- ===== Recent activity ===== --}}
    <div style="display:grid; grid-template-columns: {{ $canSelenggara ? '1.4fr 1fr' : '1fr' }}; gap:18px; margin-top:6px;">
        <div class="tap-card">
            <div class="tap-card__eyebrow">Kes Terkini</div>
            @forelse ($recentKes as $k)
                <a href="{{ route('kes.show', $k->id) }}" class="tap-card__row" style="text-decoration:none; align-items:center;">
                    <div class="k" style="display:flex; flex-direction:column; gap:2px;">
                        <span style="color:var(--ink); font-weight:500;">{{ $k->nama ?: 'Tanpa Nama' }}</span>
                        <span style="font-size:11px; color:var(--mute);">{{ $k->no_fail ?: '#'.$k->id }} · {{ $k->cawangan ?: '—' }}</span>
                    </div>
                    <div class="v" style="text-align:right;">
                        <span class="pill pill--received">{{ $k->status ?: 'baru' }}</span>
                        <div style="font-size:11px; color:var(--mute); margin-top:3px;">{{ optional($k->tarikh_permohonan)->format('d/m/Y') ?: '—' }}</div>
                    </div>
                </a>
            @empty
                <div class="dash-empty__sub" style="padding:6px 0;">Tiada kes.</div>
            @endforelse
        </div>

        @can('menu.selenggara')
            <div class="tap-card">
                <div class="tap-card__eyebrow">Aktiviti Audit</div>
                @forelse ($recentAudit as $a)
                    <div class="tap-card__row" style="align-items:flex-start;">
                        <div class="k" style="display:flex; gap:8px; align-items:flex-start;">
                            <span style="width:8px; height:8px; border-radius:50%; margin-top:5px; flex:none; background:{{ $auditTone[$a->action_type] ?? '#999' }};"></span>
                            <span style="font-size:12px; color:var(--ink);">{{ \Illuminate\Support\Str::limit($a->remarks ?: $a->action_type.' '.$a->table_name, 48) }}</span>
                        </div>
                        <div class="v" style="font-size:10.5px; color:var(--mute); text-align:right;">{{ optional($a->modified_date)->format('d/m H:i') }}</div>
                    </div>
                @empty
                    <div class="dash-empty__sub" style="padding:6px 0;">Tiada aktiviti.</div>
                @endforelse
            </div>
        @endcan
    </div>
@endsection
