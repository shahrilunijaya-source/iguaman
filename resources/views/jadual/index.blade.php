@extends('layouts.staff')

@section('title', 'Jadual Janji Temu')

@section('content')
    <div class="tap-head">
        <div>
            <h1 class="tap-head__title">Jadual Janji Temu<span class="dot"></span></h1>
            <p class="tap-head__sub">{{ $cawanganNama ? strtoupper($cawanganNama).' · ' : '' }}{{ $monthLabel }}</p>
        </div>
        <div class="tap-head__cluster">
            <a href="{{ route('jadual.index', ['cawangan_id' => $cawanganId, 'bulan' => $prevMonth]) }}" class="tap-head__btn">← Bulan Lepas</a>
            <a href="{{ route('jadual.index', ['cawangan_id' => $cawanganId, 'bulan' => $nextMonth]) }}" class="tap-head__btn">Bulan Depan →</a>
        </div>
    </div>

    <form method="GET" action="{{ route('jadual.index') }}" class="tap-filters">
        <select name="cawangan_id" class="tap-chip" onchange="this.form.submit()">
            @foreach ($cawanganList as $id => $nama)
                <option value="{{ $id }}" @selected((int) $cawanganId === (int) $id)>{{ $nama }}</option>
            @endforeach
        </select>
        <input type="month" name="bulan" class="tap-chip" value="{{ $month->format('Y-m') }}" onchange="this.form.submit()">
    </form>

    <div class="jt-legend">
        <span><i class="jt-dot jt-dot--open"></i> Beroperasi</span>
        <span><i class="jt-dot jt-dot--weekend"></i> Hujung minggu</span>
        <span><i class="jt-dot jt-dot--holiday"></i> Cuti</span>
        <span><i class="jt-dot jt-dot--closure"></i> Penutupan operasi</span>
        <span><i class="jt-dot jt-dot--booked"></i> Ada janji temu</span>
    </div>

    @if (! $cawanganId)
        <div class="dash-empty" style="border:0">
            <div class="dash-empty__title">Tiada cawangan<span class="dot"></span></div>
            <div class="dash-empty__sub">Daftar cawangan dahulu untuk melihat jadual.</div>
        </div>
    @else
        <div class="jt-cal">
            <div class="jt-cal__head">
                @foreach ($weekdays as $wd)
                    <div class="jt-cal__wd">{{ $wd }}</div>
                @endforeach
            </div>

            @foreach ($weeks as $week)
                <div class="jt-cal__row">
                    @foreach ($week as $cell)
                        @php
                            $cellClass = 'jt-day jt-day--'.$cell['status'];
                            if ($cell['closed']) { $cellClass .= ' is-closed'; }
                            if (! $cell['inMonth']) { $cellClass .= ' is-muted'; }
                        @endphp
                        <div data-day="{{ $cell['date'] }}" class="{{ $cellClass }}">
                            <div class="jt-day__top">
                                <span class="jt-day__num">{{ $cell['day'] }}</span>
                                @if ($cell['closed'])
                                    <span class="jt-day__tag">{{ $cell['statusLabel'] }}</span>
                                @elseif (count($cell['bookings']))
                                    <span class="jt-day__count">{{ count($cell['bookings']) }}</span>
                                @endif
                            </div>
                            <div class="jt-day__body">
                                @foreach ($cell['bookings'] as $b)
                                    <div class="jt-appt jt-appt--{{ strtolower($b['status']) }}" title="{{ $b['bilik'] ?? $b['tempat'] ?? '' }}">
                                        <span class="jt-appt__time">{{ $b['masa'] }}</span>
                                        <span class="jt-appt__status">{{ $b['status'] }}</span>
                                        @if ($b['bilik'])<span class="jt-appt__room">{{ $b['bilik'] }}</span>@endif
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endforeach
                </div>
            @endforeach
        </div>
    @endif

    @push('scripts')
        <style>
            .jt-legend { display:flex; flex-wrap:wrap; gap:16px; margin:8px 0 16px; font-size:12px; color:var(--mute,#6b7280); }
            .jt-legend span { display:inline-flex; align-items:center; gap:6px; }
            .jt-dot { width:10px; height:10px; border-radius:3px; display:inline-block; }
            .jt-dot--open { background:#e9faf6; border:1px solid #00B8A9; }
            .jt-dot--weekend { background:#f1f3f5; border:1px solid #ced4da; }
            .jt-dot--holiday { background:#fff3cd; border:1px solid #f0c000; }
            .jt-dot--closure { background:#fde2e1; border:1px solid #e03131; }
            .jt-dot--booked { background:#00B8A9; }

            .jt-cal { border:1px solid var(--line,#e5e7eb); border-radius:12px; overflow:hidden; background:#fff; }
            .jt-cal__head, .jt-cal__row { display:grid; grid-template-columns:repeat(7, 1fr); }
            .jt-cal__wd { padding:10px 8px; font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:.04em; color:var(--mute,#6b7280); text-align:center; background:#f8f9fa; border-bottom:1px solid var(--line,#e5e7eb); }
            .jt-day { min-height:96px; padding:6px; border-right:1px solid var(--line,#eef0f2); border-bottom:1px solid var(--line,#eef0f2); display:flex; flex-direction:column; gap:4px; }
            .jt-cal__row > .jt-day:last-child { border-right:0; }
            .jt-day.is-muted { background:#fbfbfc; }
            .jt-day.is-muted .jt-day__num { color:#c4c8cc; }
            .jt-day--weekend { background:#f6f7f8; }
            .jt-day--holiday { background:#fffbf0; }
            .jt-day--closure { background:#fef5f4; }
            .jt-day__top { display:flex; align-items:center; justify-content:space-between; }
            .jt-day__num { font-size:13px; font-weight:600; color:#1f2933; }
            .jt-day__tag { font-size:9px; font-weight:700; text-transform:uppercase; letter-spacing:.03em; padding:2px 5px; border-radius:4px; background:#e9ecef; color:#495057; }
            .jt-day--holiday .jt-day__tag { background:#fff3cd; color:#8a6d00; }
            .jt-day--closure .jt-day__tag { background:#fde2e1; color:#b02a25; }
            .jt-day__count { font-size:10px; font-weight:700; background:#00B8A9; color:#fff; border-radius:9px; min-width:18px; height:18px; display:inline-flex; align-items:center; justify-content:center; padding:0 5px; }
            .jt-day__body { display:flex; flex-direction:column; gap:3px; overflow:hidden; }
            .jt-appt { font-size:10px; line-height:1.3; padding:3px 5px; border-radius:5px; background:#e9faf6; border-left:3px solid #00B8A9; display:flex; flex-wrap:wrap; gap:4px; align-items:baseline; }
            .jt-appt--menunggu { background:#fff8e6; border-left-color:#f0a000; }
            .jt-appt--batal { background:#f1f3f5; border-left-color:#adb5bd; }
            .jt-appt__time { font-weight:700; color:#1f2933; }
            .jt-appt__status { font-weight:600; color:#5a6772; text-transform:uppercase; font-size:8px; letter-spacing:.03em; }
            .jt-appt__room { color:#8a93a0; font-size:9px; }
        </style>
    @endpush
@endsection
