<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\MaklumBalasRequest;
use App\Models\KhidmatNasihat;
use App\Models\MaklumBalas;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Arr;
use Illuminate\View\View;

/**
 * Public satisfaction-feedback flow (batch 12 slice 1). A citizen opens a link
 * after their advisory appointment is SELESAI — no login. One feedback per KN,
 * enforced by the DB unique index plus an app-level guard. Throttled at routes.
 */
class MaklumBalasController extends Controller
{
    public function show(string $no_permohonan): View
    {
        $kn = $this->resolveKn($no_permohonan);

        if ($kn->status_kn !== KhidmatNasihat::STATUS_SELESAI) {
            return view('maklum-balas.belum-tersedia', ['kn' => $kn]);
        }

        if ($kn->maklumBalas()->exists()) {
            return view('maklum-balas.terima-kasih', ['kn' => $kn]);
        }

        return view('maklum-balas.borang', ['kn' => $kn]);
    }

    public function store(MaklumBalasRequest $request, string $no_permohonan): RedirectResponse
    {
        $kn = $this->resolveKn($no_permohonan);

        // Re-check eligibility server-side (the show gate is advisory only).
        if ($kn->status_kn !== KhidmatNasihat::STATUS_SELESAI) {
            return redirect()
                ->route('maklum-balas.show', $no_permohonan)
                ->with('maklum_balas_notis', 'Maklum balas belum tersedia untuk permohonan ini.');
        }

        if ($kn->maklumBalas()->exists()) {
            return redirect()
                ->route('maklum-balas.show', $no_permohonan)
                ->with('maklum_balas_notis', 'Maklum balas telah dihantar sebelum ini. Terima kasih.');
        }

        $data = Arr::only($request->validated(), [
            'soalan_1a', 'soalan_1b', 'soalan_1c', 'soalan_1d', 'soalan_1e',
            'soalan_1_lain_lain', 'soalan_2a', 'soalan_cadangan',
        ]);

        try {
            MaklumBalas::create($data + [
                'khidmat_nasihat_id' => $kn->id,
                'dihantar_dari_ip' => $request->ip(),
            ]);
        } catch (QueryException $e) {
            // Race: a concurrent submission won the unique index. Treat as success.
            if (! $this->isDuplicateEntry($e)) {
                throw $e;
            }
        }

        return redirect()
            ->route('maklum-balas.show', $no_permohonan)
            ->with('maklum_balas_berjaya', true);
    }

    private function resolveKn(string $noPermohonan): KhidmatNasihat
    {
        return KhidmatNasihat::where('no_permohonan', $noPermohonan)->firstOrFail();
    }

    private function isDuplicateEntry(QueryException $e): bool
    {
        return ($e->errorInfo[1] ?? null) === 1062; // MySQL ER_DUP_ENTRY
    }
}
