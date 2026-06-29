<?php

namespace App\Http\Controllers;

use App\Models\Form;
use App\Support\Audit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Gated case-lifecycle decisions (legacy peringkat 2 + 7):
 *  - Peringkat 2: Pengarah/KP approve or reject a permohonan (status transition).
 *  - Peringkat 7: Pengarah/KP officially close the file.
 * Restricted to APPROVER_ROLES; every action is audit-logged.
 */
class KeputusanController extends Controller
{
    private function gate(Request $request): void
    {
        abort_unless(
            $request->user()->can('kes.keputusan'),
            403,
            'Hanya Pengarah / Ketua Pengarah boleh membuat keputusan ini.'
        );
    }

    /** Peringkat 2 — approve (Diterima). */
    public function lulus(Request $request, Form $kes): RedirectResponse
    {
        $this->gate($request);

        $data = $request->validate([
            'kelulusan' => ['nullable', 'string', 'max:20'],
            'sumbangan' => ['nullable', 'string', 'max:20'],
        ]);

        $kes->update([
            'keputusan' => 'Diluluskan',
            'diterima' => 'Ya',
            'status' => 'Diterima',
            'kelulusan' => $data['kelulusan'] ?? null,
            'sumbangan' => $data['sumbangan'] ?? null,
            'tarikh_perakuan' => now()->toDateString(),
            'tarikh_pemakluman' => now()->toDateString(),
            'tarikh_pengarahKemaskini' => now(),
        ]);

        Audit::log('forms', $kes->id, Audit::APPROVE, "Permohonan diluluskan: {$kes->nama}");

        return back()->with('status', 'Permohonan diluluskan.');
    }

    /** Peringkat 2 — reject (Ditolak). */
    public function tolak(Request $request, Form $kes): RedirectResponse
    {
        $this->gate($request);

        $data = $request->validate(['reason' => ['nullable', 'string', 'max:100']]);

        $kes->update([
            'keputusan' => 'Ditolak',
            'diterima' => 'Tidak',
            'status' => 'Ditolak',
            'reason' => $data['reason'] ?? null,
            'tarikh_pemakluman' => now()->toDateString(),
            'tarikh_pengarahKemaskini' => now(),
        ]);

        Audit::log('forms', $kes->id, Audit::REJECT, "Permohonan ditolak: {$kes->nama}");

        return back()->with('status', 'Permohonan ditolak.');
    }

    /** Peringkat 7 — official file closure. */
    public function tutupFail(Request $request, Form $kes): RedirectResponse
    {
        $this->gate($request);

        $data = $request->validate([
            'sebab_tutup_fail' => ['nullable', 'string'],
            'kos' => ['nullable', 'string', 'max:10'],
        ]);

        $kes->update([
            'tarikh_tutup_fail' => now()->toDateString(),
            'status' => 'Fail Tutup',
            'sebab_tutup_fail' => $data['sebab_tutup_fail'] ?? null,
            'kos' => $data['kos'] ?? null,
        ]);

        Audit::log('forms', $kes->id, Audit::UPDATE, "Fail ditutup: {$kes->nama}");

        return back()->with('status', 'Fail ditutup secara rasmi.');
    }
}
