<?php

namespace App\Http\Controllers;

use App\Support\OcrPrefillService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

/**
 * W13 — OCR document → form prefill (SPIKE). Feature-flagged via config('ocr.enabled').
 * While off, the form explains the spike status and `extract` returns 503; the deterministic
 * field-mapping path is exercised once an engine is wired (see the spike doc).
 */
class OcrPrefillController extends Controller
{
    public function form(OcrPrefillService $svc): View
    {
        return view('ocr.prefill', ['enabled' => $svc->isEnabled()]);
    }

    public function extract(Request $request, OcrPrefillService $svc): JsonResponse
    {
        if (! $svc->isEnabled()) {
            return response()->json(['ok' => false, 'message' => 'OCR prefill belum diaktifkan.'], 503);
        }

        $request->validate([
            'fail' => ['required', 'file', 'max:25600', 'mimes:pdf,jpg,jpeg,png'],
        ]);

        $path = $request->file('fail')->store('ocr-temp', 'local');

        try {
            $raw = $svc->extract(Storage::disk('local')->path($path));

            return response()->json(['ok' => true, 'fields' => $svc->mapToForm($raw)]);
        } catch (\Throwable $e) {
            return response()->json(['ok' => false, 'message' => $e->getMessage()], 422);
        } finally {
            Storage::disk('local')->delete($path);
        }
    }
}
