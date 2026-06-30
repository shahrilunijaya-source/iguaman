<?php

namespace App\Http\Controllers;

use App\Support\SlotAvailabilityService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Slot availability JSON endpoints — consumed by the Batch 9 appointment wizard
 * (step 3 date/time picker) at integration. Read-only; gated by slot.view.
 */
class SlotController extends Controller
{
    public function __construct(private readonly SlotAvailabilityService $slots) {}

    /** GET /slot/tarikh — open booking dates for a branch. */
    public function availability(Request $request): JsonResponse
    {
        $data = $request->validate([
            'cawangan_id' => ['required', 'integer', 'exists:cawangan,id'],
            'from' => ['nullable', 'date'],
            'days' => ['nullable', 'integer', 'between:1,90'],
        ]);

        $dates = $this->slots->availableDates(
            (int) $data['cawangan_id'],
            $data['from'] ?? null,
            (int) ($data['days'] ?? 30),
        );

        return response()->json(['dates' => $dates]);
    }

    /** GET /slot/masa — open times for a branch on a date. */
    public function times(Request $request): JsonResponse
    {
        $data = $request->validate([
            'cawangan_id' => ['required', 'integer', 'exists:cawangan,id'],
            'tarikh' => ['required', 'date'],
        ]);

        $times = $this->slots->availableTimes(
            (int) $data['cawangan_id'],
            $data['tarikh'],
        );

        return response()->json(['times' => $times]);
    }
}
