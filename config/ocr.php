<?php

/*
|--------------------------------------------------------------------------
| W13 — OCR document → form prefill (SPIKE)
|--------------------------------------------------------------------------
|
| Configuration seam for the OCR prefill feature. Disabled by default — the
| engine is not wired yet (see docs/spikes/w13-ocr-prefill-spike.md). The
| recommended driver routes OCR through the existing Python microservice
| (keeps legal documents in-house); `textract` is the cloud fallback.
|
*/

return [
    // Master feature flag. While false, the prefill seam returns "belum diaktifkan".
    'enabled' => env('OCR_PREFILL_ENABLED', false),

    // microservice (recommended — PaddleOCR in the Python service) | textract (cloud fallback)
    'driver' => env('OCR_DRIVER', 'microservice'),

    // Python microservice OCR endpoint (proxied like the chatbot).
    'endpoint' => env('OCR_ENDPOINT'),

    'timeout' => (int) env('OCR_TIMEOUT', 30),

    // OCR label (normalised: lowercased, no punctuation) => intake-form field.
    // Drives OcrPrefillService::mapToForm(). Config, not code, so it tracks form changes.
    'field_map' => [
        'nama' => 'nama',
        'name' => 'nama',
        'no kp' => 'nokp',
        'no kad pengenalan' => 'nokp',
        'kad pengenalan' => 'nokp',
        'ic' => 'nokp',
        'alamat' => 'alamat',
        'address' => 'alamat',
        'no telefon' => 'no_tel',
        'telefon' => 'no_tel',
        'jenis kes' => 'jenis_kes',
        'kategori kes' => 'kategori_kes',
    ],
];
