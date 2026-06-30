<?php

namespace App\Support;

use App\Models\Cawangan;
use App\Models\PenutupanOperasi;
use App\Models\RefCuti;
use App\Models\SlotTemuJanji;
use Carbon\Carbon;

/**
 * Slot-availability engine (parity map §4 — the hard core of Batch 10).
 *
 * A date is bookable for a branch only when ALL hold:
 *   1. it is on/after the earliest bookable date = today + MIN_WORKING_DAYS working days
 *      (weekends skipped while counting);
 *   2. it is not a weekend (default Sat/Sun — see WEEKEND; branch-specific weekend
 *      config is a later slice);
 *   3. it is not a public holiday covering the branch's state (ref_cuti + CutiNegeri,
 *      matched against cawangan.negeri_id);
 *   4. it is not inside an operational closure (penutupan_operasi) for the branch;
 *   5. at least one open slot exists — a slot_temu_janji row for (branch, date) with
 *      is_temujanji = false and status_aktif = true.
 *
 * Pure/testable: "today" is injectable so tests can fix the reference date.
 * Returns plain arrays (dates 'Y-m-d', times 'H:i') for a JSON date-picker.
 */
class SlotAvailabilityService
{
    /** Minimum lead time in WORKING days (weekends not counted). */
    public const MIN_WORKING_DAYS = 4;

    /**
     * Default weekend day-of-week set in ISO numbering (1=Mon … 7=Sun): Sat(6)+Sun(7).
     * NOTE: ISO literals, NOT Carbon's SATURDAY/SUNDAY constants — Carbon::SUNDAY is 0,
     * which never matches dayOfWeekIso (Sunday = 7) and would leave Sundays bookable.
     * Per-branch override comes from cawangan.hari_minggu (Cawangan::weekendDays());
     * an explicit $weekend argument still wins over both.
     */
    public const WEEKEND = [6, 7];

    /**
     * Available booking dates for a branch, from $from (default = today) over $days.
     *
     * @return list<string> 'Y-m-d' dates, ascending
     */
    public function availableDates(int $cawanganId, ?string $from = null, int $days = 30, ?Carbon $today = null, ?array $weekend = null): array
    {
        $cawangan = Cawangan::find($cawanganId);
        if ($cawangan === null) {
            return [];
        }

        $today = ($today ? $today->copy() : Carbon::today())->startOfDay();
        $weekend = $weekend ?? $cawangan->weekendDays() ?? self::WEEKEND;

        $earliest = $this->earliestBookable($today, $weekend);
        $start = $from ? Carbon::parse($from)->startOfDay() : $today->copy();
        if ($start->lt($earliest)) {
            $start = $earliest->copy();
        }
        $end = $today->copy()->addDays(max(0, $days - 1));

        $negeriId = $cawangan->negeri_id;
        $holidays = $this->holidayDates($negeriId);
        $closures = $this->closureRanges($cawanganId);
        $openDates = $this->openSlotDates($cawanganId);

        $dates = [];
        for ($d = $start->copy(); $d->lte($end); $d->addDay()) {
            $key = $d->toDateString();

            if ($this->isWeekend($d, $weekend)) {
                continue;
            }
            if (isset($holidays[$key])) {
                continue;
            }
            if ($this->isClosed($d, $closures)) {
                continue;
            }
            if (! isset($openDates[$key])) {
                continue;
            }

            $dates[] = $key;
        }

        return $dates;
    }

    /**
     * Open times for a branch on one date. Empty when the date itself is not bookable.
     *
     * @return list<string> 'H:i' start times, ascending
     */
    public function availableTimes(int $cawanganId, string $date, ?Carbon $today = null, ?array $weekend = null): array
    {
        $cawangan = Cawangan::find($cawanganId);
        if ($cawangan === null) {
            return [];
        }

        $today = ($today ? $today->copy() : Carbon::today())->startOfDay();
        $weekend = $weekend ?? $cawangan->weekendDays() ?? self::WEEKEND;
        $day = Carbon::parse($date)->startOfDay();

        // The date must clear every date-level rule before times are offered.
        if ($day->lt($this->earliestBookable($today, $weekend))) {
            return [];
        }
        if ($this->isWeekend($day, $weekend)) {
            return [];
        }
        if (isset($this->holidayDates($cawangan->negeri_id)[$day->toDateString()])) {
            return [];
        }
        if ($this->isClosed($day, $this->closureRanges($cawanganId))) {
            return [];
        }

        return SlotTemuJanji::query()
            ->where('cawangan_id', $cawanganId)
            ->whereDate('tarikh_slot', $day->toDateString())
            ->where('is_temujanji', false)
            ->where('status_aktif', true)
            ->orderBy('masa_mula')
            ->pluck('masa_mula')
            ->map(fn ($t) => Carbon::parse($t)->format('H:i'))
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Per-day open/closed status for a branch over an inclusive date range, for the
     * read-only month calendar (Jadual Janji Temu). Reuses the same exclusion sources
     * as availableDates(); does NOT apply the lead-time window (the calendar shows
     * past + near dates too — booking enforcement stays in availableDates/Times).
     *
     * @return array<string, array{status: string, label: string}>
     *         'Y-m-d' => one of: weekend | holiday | closure | open
     */
    public function dayStatuses(int $cawanganId, string $from, string $to, ?array $weekend = null): array
    {
        $cawangan = Cawangan::find($cawanganId);
        if ($cawangan === null) {
            return [];
        }

        $weekend = $weekend ?? $cawangan->weekendDays() ?? self::WEEKEND;
        $holidays = $this->holidayDates($cawangan->negeri_id);
        $closures = $this->closureRanges($cawanganId);

        $start = Carbon::parse($from)->startOfDay();
        $end = Carbon::parse($to)->startOfDay();

        $out = [];
        for ($d = $start->copy(); $d->lte($end); $d->addDay()) {
            $key = $d->toDateString();

            if ($this->isWeekend($d, $weekend)) {
                $out[$key] = ['status' => 'weekend', 'label' => 'Hujung minggu'];
            } elseif (isset($holidays[$key])) {
                $out[$key] = ['status' => 'holiday', 'label' => 'Cuti'];
            } elseif ($this->isClosed($d, $closures)) {
                $out[$key] = ['status' => 'closure', 'label' => 'Penutupan operasi'];
            } else {
                $out[$key] = ['status' => 'open', 'label' => 'Beroperasi'];
            }
        }

        return $out;
    }

    /** today + MIN_WORKING_DAYS working days (weekends not counted). */
    private function earliestBookable(Carbon $today, array $weekend): Carbon
    {
        $date = $today->copy();
        $counted = 0;
        while ($counted < self::MIN_WORKING_DAYS) {
            $date->addDay();
            if (! $this->isWeekend($date, $weekend)) {
                $counted++;
            }
        }

        return $date;
    }

    private function isWeekend(Carbon $date, array $weekend): bool
    {
        return in_array($date->dayOfWeekIso, $weekend, true);
    }

    /**
     * Holiday dates covering $negeriId, as a 'Y-m-d' => true lookup.
     * A ref_cuti row applies when its idnegeri bitmask (decoded) contains the state.
     */
    private function holidayDates(?int $negeriId): array
    {
        if ($negeriId === null) {
            return [];
        }

        $dates = [];
        foreach (RefCuti::all() as $cuti) {
            if (! in_array($negeriId, CutiNegeri::decode($cuti->idnegeri), true)) {
                continue;
            }
            $start = Carbon::parse($cuti->tarikh_mula)->startOfDay();
            $end = Carbon::parse($cuti->tarikh_tamat ?? $cuti->tarikh_mula)->startOfDay();
            for ($d = $start->copy(); $d->lte($end); $d->addDay()) {
                $dates[$d->toDateString()] = true;
            }
        }

        return $dates;
    }

    /** @return list<array{start: Carbon, end: Carbon}> */
    private function closureRanges(int $cawanganId): array
    {
        return PenutupanOperasi::query()
            ->where('cawangan_id', $cawanganId)
            ->get(['tarikh_mula', 'tarikh_tamat'])
            ->map(fn ($row) => [
                'start' => Carbon::parse($row->tarikh_mula)->startOfDay(),
                'end' => Carbon::parse($row->tarikh_tamat)->startOfDay(),
            ])
            ->all();
    }

    /** @param list<array{start: Carbon, end: Carbon}> $closures */
    private function isClosed(Carbon $date, array $closures): bool
    {
        foreach ($closures as $range) {
            if ($date->betweenIncluded($range['start'], $range['end'])) {
                return true;
            }
        }

        return false;
    }

    /** 'Y-m-d' => true for dates with >=1 open slot for the branch. */
    private function openSlotDates(int $cawanganId): array
    {
        return SlotTemuJanji::query()
            ->where('cawangan_id', $cawanganId)
            ->where('is_temujanji', false)
            ->where('status_aktif', true)
            ->pluck('tarikh_slot')
            ->mapWithKeys(fn ($d) => [Carbon::parse($d)->toDateString() => true])
            ->all();
    }
}
