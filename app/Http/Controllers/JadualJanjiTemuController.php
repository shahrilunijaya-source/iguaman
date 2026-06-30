<?php

namespace App\Http\Controllers;

use App\Models\Cawangan;
use App\Models\TemuJanji;
use App\Support\SlotAvailabilityService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Jadual Janji Temu — read-only month calendar of booked appointments per branch
 * (Batch 10 slice 3, FE janjitemu/jadual-janji-temu).
 *
 * Renders temu_janji bookings on a month grid for one cawangan and marks the days
 * SlotAvailabilityService excludes (weekend / state holiday / operational closure)
 * via its dayStatuses() helper. Gated permission:slot.view (read-only).
 */
class JadualJanjiTemuController extends Controller
{
    public function __construct(private readonly SlotAvailabilityService $slots) {}

    public function index(Request $request): View
    {
        $cawanganList = Cawangan::orderBy('nama')->pluck('nama', 'id')->all();

        $cawanganId = (int) $request->input('cawangan_id', (int) array_key_first($cawanganList));
        $cawangan = $cawanganId ? Cawangan::find($cawanganId) : null;

        $month = $this->parseMonth($request->input('bulan'));
        $monthStart = $month->copy()->startOfMonth();
        $monthEnd = $month->copy()->endOfMonth();

        // Calendar grid: full weeks (Mon–Sun) covering the month.
        $gridStart = $monthStart->copy()->startOfWeek(Carbon::MONDAY);
        $gridEnd = $monthEnd->copy()->endOfWeek(Carbon::SUNDAY);

        $statuses = $cawangan
            ? $this->slots->dayStatuses($cawangan->id, $gridStart->toDateString(), $gridEnd->toDateString())
            : [];

        $bookings = $cawangan ? $this->bookingsByDate($cawangan->id, $monthStart, $monthEnd) : [];

        $weeks = $this->buildWeeks($gridStart, $gridEnd, $monthStart->month, $statuses, $bookings);

        return view('jadual.index', [
            'cawanganList' => $cawanganList,
            'cawanganId' => $cawangan?->id,
            'cawanganNama' => $cawangan?->nama,
            'month' => $monthStart,
            'monthLabel' => $this->malayMonth($monthStart),
            'prevMonth' => $monthStart->copy()->subMonth()->format('Y-m'),
            'nextMonth' => $monthStart->copy()->addMonth()->format('Y-m'),
            'weekdays' => ['Isn', 'Sel', 'Rab', 'Kha', 'Jum', 'Sab', 'Ahd'],
            'weeks' => $weeks,
        ]);
    }

    /** Booked appointments for the month, grouped 'Y-m-d' => list of view rows. */
    private function bookingsByDate(int $cawanganId, Carbon $start, Carbon $end): array
    {
        $rows = TemuJanji::query()
            ->with('slot.bilik:id,nama_bilik')
            ->where('cawangan_id', $cawanganId)
            ->whereDate('tarikh_temu_janji', '>=', $start->toDateString())
            ->whereDate('tarikh_temu_janji', '<=', $end->toDateString())
            ->where('status', '!=', 'BATAL')
            ->orderBy('tarikh_temu_janji')
            ->orderBy('masa_mula')
            ->get();

        $grouped = [];
        foreach ($rows as $row) {
            $key = Carbon::parse($row->tarikh_temu_janji)->toDateString();
            $grouped[$key][] = [
                'masa' => Carbon::parse($row->masa_mula)->format('H:i'),
                'status' => $row->status,
                'bilik' => $row->slot?->bilik?->nama_bilik,
                'tempat' => $row->tempat,
            ];
        }

        return $grouped;
    }

    /** @return list<list<array>> weeks of 7 day-cells */
    private function buildWeeks(Carbon $gridStart, Carbon $gridEnd, int $month, array $statuses, array $bookings): array
    {
        $weeks = [];
        $week = [];

        for ($d = $gridStart->copy(); $d->lte($gridEnd); $d->addDay()) {
            $key = $d->toDateString();
            $status = $statuses[$key]['status'] ?? 'open';

            $week[] = [
                'date' => $key,
                'day' => (int) $d->format('j'),
                'inMonth' => (int) $d->month === $month,
                'status' => $status,
                'statusLabel' => $statuses[$key]['label'] ?? '',
                'closed' => $status !== 'open',
                'bookings' => $bookings[$key] ?? [],
            ];

            if (count($week) === 7) {
                $weeks[] = $week;
                $week = [];
            }
        }

        if ($week !== []) {
            $weeks[] = $week;
        }

        return $weeks;
    }

    private function parseMonth(?string $bulan): Carbon
    {
        if ($bulan && preg_match('/^\d{4}-\d{2}$/', $bulan)) {
            return Carbon::createFromFormat('Y-m', $bulan)->startOfMonth();
        }

        return Carbon::today()->startOfMonth();
    }

    private function malayMonth(Carbon $date): string
    {
        $names = [1 => 'Januari', 'Februari', 'Mac', 'April', 'Mei', 'Jun', 'Julai', 'Ogos', 'September', 'Oktober', 'November', 'Disember'];

        return $names[$date->month].' '.$date->year;
    }
}
