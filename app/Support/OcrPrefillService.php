<?php

namespace App\Support;

use RuntimeException;

/**
 * W13 — OCR document → intake-form prefill (SPIKE / scaffolding).
 *
 * The seam is in place but the OCR engine is NOT wired (see
 * docs/spikes/w13-ocr-prefill-spike.md). `extract()` throws while the feature flag is off so
 * nothing silently pretends to work. `mapToForm()` — the deterministic label→field mapping — is
 * real and unit-testable now, so the contract the engine must satisfy is pinned down.
 */
class OcrPrefillService
{
    public function isEnabled(): bool
    {
        return (bool) config('ocr.enabled', false);
    }

    /**
     * Extract raw key/value pairs from a document at $path.
     *
     * @return array<string, mixed> raw OCR label => value
     *
     * @throws RuntimeException while the feature is disabled or the driver is not implemented
     */
    public function extract(string $path): array
    {
        if (! $this->isEnabled()) {
            throw new RuntimeException('OCR prefill belum diaktifkan (W13 spike — enjin OCR belum disambung).');
        }

        // Phase 2: POST the file to config('ocr.endpoint') (microservice driver) — proxy pattern
        // identical to ChatbotController — and decode the returned key/value JSON. Textract driver
        // would call AnalyzeDocument instead. Neither is implemented in the spike.
        throw new RuntimeException('Pemacu OCR "'.config('ocr.driver').'" belum dilaksanakan.');
    }

    /**
     * Map raw OCR labels to intake-form fields via the config field map. Deterministic + pure —
     * the part of the pipeline that is testable without an engine.
     *
     * @param  array<string, mixed>  $raw
     * @return array<string, mixed> form field => value
     */
    public function mapToForm(array $raw): array
    {
        $map = config('ocr.field_map', []);
        $out = [];

        foreach ($raw as $label => $value) {
            $key = $this->normaliseLabel((string) $label);

            if (isset($map[$key]) && filled($value)) {
                $out[$map[$key]] = is_string($value) ? trim($value) : $value;
            }
        }

        return $out;
    }

    /** Lowercase, strip punctuation, collapse whitespace — so "No. K/P:" matches "no kp". */
    private function normaliseLabel(string $label): string
    {
        $label = str_replace(['.', ':', '/'], ['', '', ' '], $label);

        return trim(strtolower(preg_replace('/\s+/', ' ', $label)));
    }
}
