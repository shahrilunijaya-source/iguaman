# W13 — OCR document extraction → online-form prefill (SPIKE)

**Status:** Spike (decision only). No live OCR engine wired. The seam (config flag,
`OcrPrefillService`, feature-flagged controller/route) is scaffolded **off** so the integration
point exists and can be code-reviewed before any engine/cost is committed.

**Wish:** "Extract received documents → prefill online form" — when an officer uploads a scanned
permohonan / supporting document, read its fields (name, IC, address, case type…) and pre-populate
the Khidmat Nasihat / Kes intake form to cut manual keying + transcription errors.

## Why spike-first

OCR quality and cost vary wildly by engine and document quality (clean PDF vs phone photo vs
handwriting). Committing an engine before measuring accuracy on **real JBG forms** risks shipping a
feature that mis-keys legal records — worse than manual entry. This spike picks the engine + host
and defines an accuracy bar to clear before turning the seam on.

## Options evaluated

| Engine | Accuracy (typed forms) | Structured K/V form extraction | Cost | Host fit (Hostinger shared) | Data privacy |
|--------|------------------------|-------------------------------|------|-----------------------------|--------------|
| **Tesseract** (open source) | Good on clean scans, weak on noise/handwriting | No — raw text only; needs custom layout parsing | Free | ❌ needs `tesseract` binary; cannot install on shared hosting | In-house ✅ |
| **PaddleOCR** (open source) | Better than Tesseract on noisy input; layout-aware | Partial (structure module) | Free | ❌ Python/native deps; not on shared PHP host | In-house ✅ |
| **AWS Textract** | High | ✅ `AnalyzeDocument` returns key/value pairs | ~USD 1.50/1k pages (text), ~USD 50/1k (forms) | ✅ HTTP API, no server binary | ❌ legal docs leave the country/JBG |
| **Google Document AI / Azure Form Recognizer** | High | ✅ | Comparable to Textract | ✅ HTTP API | ❌ external |

### Host reality
- Hostinger shared hosting **cannot run native OCR binaries** (Tesseract/PaddleOCR). The deploy
  pipeline is composer-install + migrate only; no `apt`/native installs.
- The system **already runs a Python microservice** (the AI@JBG chatbot, proxied by Laravel —
  see `ChatbotController`). That service *can* host Tesseract/PaddleOCR and expose an internal OCR
  endpoint, called by Laravel exactly like the chatbot proxy.

## Recommendation

**Route OCR through the existing Python microservice, not a cloud API.**

1. Keeps legal documents **in-house** (no third-party data-residency/privacy exposure — important for
   bantuan-guaman records).
2. Avoids Hostinger's no-native-binary limit (the binary lives in the Python service).
3. Reuses the proven chatbot proxy pattern (`ChatbotController` → microservice over HTTP, throttled).
4. No per-page cloud cost; PaddleOCR (layout-aware) gives K/V extraction good enough to prefill.

Fallback if microservice accuracy is insufficient on real forms: **AWS Textract `AnalyzeDocument`**
behind the same `OcrPrefillService` contract — but only after a privacy/cost sign-off, since legal
docs would leave JBG.

## Accuracy bar (must clear before enabling)

On a sample of ~50 real JBG permohonan/supporting documents:
- ≥ 95% field-level accuracy on **No. KP** and **Nama** (the identity fields — a wrong IC is worse
  than a blank one).
- ≥ 80% on free-text fields (alamat, butiran kes).
- Officer **always reviews + confirms** the prefilled form before save — OCR prefills, never
  auto-commits. The form is a draft proposal, not a record.

## Architecture (when built)

```
Officer uploads doc ─▶ OcrPrefillController@extract (feature-flagged)
                         │
                         ▼
                   OcrPrefillService::extract($path)
                         │  POST {file} ──▶ Python microservice /ocr/extract  (PaddleOCR)
                         │  ◀── raw OCR key/value JSON
                         ▼
                   OcrPrefillService::mapToForm($raw)   (label → forms/KN field map, config-driven)
                         │
                         ▼
            Prefilled (draft) intake form ─▶ officer reviews + confirms ─▶ normal store()
```

- `config/ocr.php` — `enabled` flag (default false), `driver` (microservice|textract), endpoint,
  and the `field_map` (OCR label → form field).
- `App\Support\OcrPrefillService` — `isEnabled()`, `extract($path)` (calls the driver), `mapToForm($raw)`
  (label→field mapping). Stubbed now: throws `RuntimeException` when disabled.
- `App\Http\Controllers\OcrPrefillController` — upload form + `extract` action, gated on `kes.create`
  and the feature flag; returns "OCR belum diaktifkan" while off.

## Phased plan

1. **Spike (this):** decision + scaffolding off. ✅
2. **Microservice OCR endpoint:** add PaddleOCR `/ocr/extract` to the Python service; measure accuracy
   on the 50-doc sample against the bar above.
3. **Wire `OcrPrefillService` (microservice driver)** + the `field_map`; turn the flag on behind the
   officer-review step. Prefill the KN/Kes draft only.
4. **Iterate the field map** per document type; add Textract fallback only if the bar isn't met and
   a privacy/cost sign-off is obtained.

## Risks

- Accuracy on phone-photo / handwritten forms may not clear the bar → keep manual entry as the path.
- Microservice availability becomes a dependency of intake → must degrade gracefully (flag off = plain
  manual form, no breakage).
- Field-map drift as forms change → map is config, not code.
