<?php

namespace App\Support;

use App\Models\Bilik;
use App\Models\Cawangan;
use App\Models\PenutupanOperasi;
use App\Models\RefCuti;
use App\Models\SlotTemuJanji;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Slot auto-generation (parity map §4 - "slot auto-generate per branch/room").
 *
 * Given a branch (+optional room) and a date range, create slot_temu_janji rows for
 * every WORKING day at intervals of the branch's tempoh_slot_minit between masa_buka
 * and masa_tutup. A day is skipped when it is:
 *   - a weekend per the branch's hari_minggu config (fallback Sat/Sun);
 *   - a public holiday covering the branch state (ref_cuti + CutiNegeri);
 *   - inside an operational closure (penutupan_operasi) for the branch (+room).
 *
 * Idempotent: an existing (cawangan, bilik, date, masa_mula) slot is never duplicated.
 * Mirrors the exclusion rules of SlotAvailabilityService so generated supply lines up
 * with computed availability. Lead-time (MIN_WORKING_DAYS) is an availability concern,
 * NOT a generation one - slots are generated for the whole working range.
 */
class SlotGenerator
{
    /** Default operating window when the branch leaves masa_buka/masa_tutup unset. */
    public const DEFAULT_OPEN = '09:00';

    public const DEFAULT_CLOSE = '17:00';

    public const DEFAULT_TEMPOH = 30;

    /** Hard cap on the generated range to avoid runaway loops. */
    public const MAX_RANGE_DAYS = 180;

    /**
     * Generate slots for [$from, $to] inclusive.
     *
     * @return array{created: int, skipped_days: int, existing: int}
     */
    public function generate(Cawangan $cawangan, ?Bilik $bilik, string $from, string $to): array
    {
        $start = Carbon::parse($from)->startOfDay();
        $end = Carbon::parse($to)->startOfDay();
        if ($end->lt($start)) {
            [$start, $end] = [$end, $start];
        }
        // Clamp the range defensively.
        if ($start->diffInDays($end) > self::MAX_RANGE_DAYS) {
            $end = $start->copy()->addDays(self::MAX_RANGE_DAYS);
        }

        $weekend = $cawangan->weekendDays() ?? SlotAvailabilityService::WEEKEND;
        $holidays = $this->holidayDates($cawangan->negeri_id);
        $closures = $this->closureRanges($cawangan->id, $bilik?->id);
        $times = $this->slotTimes($cawangan);

        $bilikId = $bilik?->id;
        $existingKeys = $this->existingSlotKeys($cawangan->id, $bilikId, $start, $end);

        $created = 0;
        $existing = 0;
        $skippedDays = 0;
        $rows = [];
        $now = now();

        for ($d = $start->copy(); $d->lte($end); $d->addDay()) {
            $key = $d->toDateString();

            if (in_array($d->dayOfWeekIso, $weekend, true)
                || isset($holidays[$key])
                || $this->isClosed($d, $closures)) {
                $skippedDays++;

                continue;
            }

            foreach ($times as [$mula, $akhir]) {
                $slotKey = $key.'|'.$mula;
                if (isset($existingKeys[$slotKey])) {
                    $existing++;

                    continue;
                }

                $rows[] = [
                    'cawangan_id' => $cawangan->id,
                    'bilik_id' => $bilikId,
                    'tarikh_slot' => $key,
                    'masa_mula' => $mula,
                    'masa_akhir' => $akhir,
                    'is_temujanji' => false,
                    'status_aktif' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
                $existingKeys[$slotKey] = true; // guard against in-batch dupes
                $created++;
            }
        }

        foreach (array_chunk($rows, 500) as $chunk) {
            SlotTemuJanji::insert($chunk);
        }

        return ['created' => $created, 'skipped_days' => $skippedDays, 'existing' => $existing];
    }

    /**
     * Start/end pairs ('H:i') across the branch operating window, stepping tempoh minutes.
     *
     * @return list<array{0: string, 1: string}>
     */
    private function slotTimes(Cawangan $cawangan): array
    {
        $open = Carbon::parse($cawangan->masa_buka ?: self::DEFAULT_OPEN);
        $close = Carbon::parse($cawangan->masa_tutup ?: self::DEFAULT_CLOSE);
        $tempoh = (int) ($cawangan->tempoh_slot_minit ?: self::DEFAULT_TEMPOH);
        if ($tempoh < 1) {
            $tempoh = self::DEFAULT_TEMPOH;
        }

        $times = [];
        $cursor = $open->copy();
        // A slot fits only when its full length lands on/before the closing time.
        while ($cursor->copy()->addMinutes($tempoh)->lte($close)) {
            $mula = $cursor->format('H:i');
            $akhir = $cursor->copy()->addMinutes($tempoh)->format('H:i');
            $times[] = [$mula, $akhir];
            $cursor->addMinutes($tempoh);
        }

        return $times;
    }

    /** PERF-08: memo of ref_cuti for this instance (holidayDates re-scanned it per call). */
    private ?Collection $cutiMemo = null;

    /** 'Y-m-d' => true holiday lookup for the branch state (mirrors SlotAvailabilityService). */
    private function holidayDates(?int $negeriId): array
    {
        if ($negeriId === null) {
            return [];
        }

        $dates = [];
        foreach (($this->cutiMemo ??= RefCuti::all()) as $cuti) {
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

    /**
     * Closure ranges for the branch. A branch-wide closure (bilik_id null) applies to
     * every room; a room-specific closure applies only when generating for that room.
     *
     * @return list<array{start: Carbon, end: Carbon}>
     */
    private function closureRanges(int $cawanganId, ?int $bilikId): array
    {
        return PenutupanOperasi::query()
            ->where('cawangan_id', $cawanganId)
            ->where(function ($q) use ($bilikId) {
                $q->whereNull('bilik_id');
                if ($bilikId !== null) {
                    $q->orWhere('bilik_id', $bilikId);
                }
            })
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

    /**
     * Existing slot keys 'Y-m-d|H:i' for the (branch, room, range) - the idempotency guard.
     * bilik_id null is matched as null so branch-level and room-level supply don't collide.
     */
    private function existingSlotKeys(int $cawanganId, ?int $bilikId, Carbon $start, Carbon $end): array
    {
        return SlotTemuJanji::query()
            ->where('cawangan_id', $cawanganId)
            ->when($bilikId === null, fn ($q) => $q->whereNull('bilik_id'), fn ($q) => $q->where('bilik_id', $bilikId))
            ->whereBetween('tarikh_slot', [$start->toDateString(), $end->toDateString()])
            ->get(['tarikh_slot', 'masa_mula'])
            ->mapWithKeys(function ($row) {
                $date = Carbon::parse($row->tarikh_slot)->toDateString();
                $mula = Carbon::parse($row->masa_mula)->format('H:i');

                return [$date.'|'.$mula => true];
            })
            ->all();
    }
}
