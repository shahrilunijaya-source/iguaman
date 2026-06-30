@extends('layouts.staff')

@section('title', 'Pra-isi Borang (OCR)')

@section('content')
    <div class="tap-head">
        <div>
            <h1 class="tap-head__title">Pra-isi Borang dari Dokumen (OCR)<span class="dot"></span></h1>
            <p class="tap-head__sub">Ekstrak medan daripada dokumen imbasan untuk pra-isi borang permohonan.</p>
        </div>
    </div>

    @unless ($enabled)
        <div class="card" style="padding:18px;">
            <h2 style="margin:0 0 8px; font-size:15px;">Ciri ini belum diaktifkan</h2>
            <p style="margin:0; color:var(--muted,#64748b);">
                W13 berada di peringkat <strong>spike</strong> — enjin OCR belum disambung. Lihat
                <code>docs/spikes/w13-ocr-prefill-spike.md</code> untuk keputusan reka bentuk
                (PaddleOCR melalui mikroservis Python; AWS Textract sebagai sandaran) dan pelan fasa.
                Aktifkan dengan <code>OCR_PREFILL_ENABLED=true</code> selepas enjin disambung.
            </p>
        </div>
    @else
        <form id="ocr-form" class="card" style="padding:18px; display:grid; gap:12px; max-width:560px;" enctype="multipart/form-data">
            @csrf
            <label>Dokumen (PDF / imej)
                <input type="file" name="fail" accept=".pdf,.jpg,.jpeg,.png" required class="tap-chip">
            </label>
            <div><button class="btn btn--primary" type="submit">Ekstrak Medan</button></div>
            <pre id="ocr-result" style="display:none; background:#0f172a; color:#e2e8f0; padding:12px; border-radius:8px; overflow:auto;"></pre>
        </form>

        @push('scripts')
        <script>
            document.getElementById('ocr-form').addEventListener('submit', async (e) => {
                e.preventDefault();
                const out = document.getElementById('ocr-result');
                out.style.display = 'block';
                out.textContent = 'Memproses…';
                const res = await fetch('{{ route('ocr.prefill.extract') }}', {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
                    body: new FormData(e.target),
                });
                out.textContent = JSON.stringify(await res.json(), null, 2);
            });
        </script>
        @endpush
    @endunless
@endsection
