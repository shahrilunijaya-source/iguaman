<?php

namespace App\Http\Controllers;

use App\Models\Form;
use App\Models\LejarTuntutanBayaran;
use App\Models\PeguamPanel;
use App\Support\Audit;
use App\Support\KesKnSyncService;
use App\Support\LejarTuntutanService;
use App\Support\StatusAgihan;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

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

        // PROC-12: don't re-decide a case that already has an outcome (re-POST / stale button).
        abort_if(in_array($kes->status, ['Diterima', 'Ditolak', 'Fail Tutup'], true), 409, 'Permohonan ini telah diputuskan.');

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

        // PROC-12: don't re-decide a case that already has an outcome.
        abort_if(in_array($kes->status, ['Diterima', 'Ditolak', 'Fail Tutup'], true), 409, 'Permohonan ini telah diputuskan.');

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

        // PROC-12: block double-close — re-closing would re-seed the claim ledger and re-push the KN.
        abort_if($kes->status === 'Fail Tutup', 409, 'Fail ini telah ditutup.');

        $data = $request->validate([
            'sebab_tutup_fail' => ['nullable', 'string'],
            'kos' => ['nullable', 'string', 'max:10'],
        ]);

        // CODE-01: closure + ledger seed + KN sync are one unit of work — commit together or roll back.
        DB::transaction(function () use ($kes, $request, $data) {
            $kes->update([
                'tarikh_tutup_fail' => now()->toDateString(),
                'status' => 'Fail Tutup',
                'sebab_tutup_fail' => $data['sebab_tutup_fail'] ?? null,
                'kos' => $data['kos'] ?? null,
            ]);

            Audit::log('forms', $kes->id, Audit::UPDATE, "Fail ditutup: {$kes->nama}");

            // W9: seed a Pembelaan Awam claim-ledger row on close (idempotent).
            $this->seedPembelaanLedger($kes, $request->user()->name);

            // W12: propagate closure back to the originating Khidmat Nasihat, if any.
            app(KesKnSyncService::class)->pushToKn($kes, KesKnSyncService::STATE_DITUTUP, $request->user()->name);
        });

        return back()->with('status', 'Fail ditutup secara rasmi.');
    }

    /**
     * W16 — JBG confirmation queue: cases a panel lawyer marked selesai (status 18),
     * awaiting an officer's final confirmation + file closure.
     */
    public function senaraiSelesai(Request $request): View
    {
        $this->gate($request);

        $kes = Form::query()
            ->whereIn('status_agihan', StatusAgihan::bucketValues([StatusAgihan::PP_SELESAI]))
            ->orderByDesc('tarikh_selesai')
            ->paginate(20)
            ->withQueryString();

        return view('keputusan.selesai', ['kes' => $kes]);
    }

    /** W16 — JBG confirms a lawyer's selesai (18 → 19): close the file + sync KN + enable closure PDF. */
    public function sahkanSelesai(Request $request, Form $kes): RedirectResponse
    {
        $this->gate($request);
        $this->ensureSelesaiPending($kes);

        // CODE-01: confirm-close + ledger seed + KN sync are one unit of work — commit together or roll back.
        DB::transaction(function () use ($kes, $request) {
            $kes->update([
                'status_agihan' => StatusAgihan::KES_DITUTUP,
                'status' => 'Fail Tutup',
                // Preserve an existing official closure date — never re-stamp a legally meaningful field.
                'tarikh_tutup_fail' => filled($kes->tarikh_tutup_fail) ? $kes->tarikh_tutup_fail : now()->toDateString(),
            ]);

            Audit::log('forms', $kes->id, Audit::APPROVE, "Penyelesaian kes disahkan & fail ditutup: {$kes->nama}");

            // W9: seed a Pembelaan Awam claim-ledger row on JBG-confirmed close (idempotent).
            $this->seedPembelaanLedger($kes, $request->user()->name);

            // W12: propagate closure back to the originating Khidmat Nasihat, if any.
            app(KesKnSyncService::class)->pushToKn($kes, KesKnSyncService::STATE_DITUTUP, $request->user()->name);
        });

        return redirect()->route('keputusan.selesai')->with('status', 'Penyelesaian kes disahkan. Fail ditutup.');
    }

    /** W16 — JBG rejects a lawyer's selesai (18 → 2): case returns to active handling. */
    public function tolakSelesai(Request $request, Form $kes): RedirectResponse
    {
        $this->gate($request);
        $this->ensureSelesaiPending($kes);

        $data = $request->validate(['reason' => ['nullable', 'string', 'max:255']]);

        $kes->update([
            'status_agihan' => StatusAgihan::DITERIMA,
            'tarikh_selesai' => null,
            'sebab_selesai' => null,
        ]);

        Audit::log('forms', $kes->id, Audit::REJECT,
            'Pengesahan selesai ditolak — kes dikembalikan kepada peguam'.($data['reason'] ?? null ? ": {$data['reason']}" : '').": {$kes->nama}");

        return redirect()->route('keputusan.selesai')->with('status', 'Pengesahan ditolak. Kes dikembalikan kepada peguam.');
    }

    /** Guard: the case must currently be a lawyer-marked-selesai awaiting confirmation (status 18). */
    private function ensureSelesaiPending(Form $kes): void
    {
        abort_unless(
            StatusAgihan::normalise($kes->status_agihan) === StatusAgihan::PP_SELESAI,
            422,
            'Status kes telah berubah. Sila muat semula senarai.'
        );
    }

    /**
     * W9 — on closure of a Pembelaan Awam case, seed a DRAF claim-ledger row
     * (sumber = PEMBELAAN_AWAM) so the assigned panel lawyer can file a claim against the
     * file. No-op for civil cases. Idempotent: PEMBELAAN_AWAM rows have no DB-unique guard,
     * so an existence check prevents duplicates across the two close paths.
     */
    private function seedPembelaanLedger(Form $kes, string $actor): void
    {
        if (! $kes->is_pembelaan_awam) {
            return;
        }

        $exists = LejarTuntutanBayaran::where('sumber', LejarTuntutanBayaran::SUMBER_PEMBELAAN_AWAM)
            ->where('id_kes', $kes->id)
            ->exists();

        if ($exists) {
            return;
        }

        // Resolve the assigned panel lawyer (stored by name on the case) for the claim.
        $peguam = filled($kes->nama_pegawai_yang_dapat_kes)
            ? PeguamPanel::where('nama_peguam', $kes->nama_pegawai_yang_dapat_kes)->first()
            : null;

        app(LejarTuntutanService::class)->cipta([
            'sumber' => LejarTuntutanBayaran::SUMBER_PEMBELAAN_AWAM,
            'sumber_id' => $kes->id,
            'id_kes' => $kes->id,
            'id_peguam_panel' => $peguam?->id,
            'kp_peguam' => $peguam?->kp_peguam,
            'cawangan' => $kes->cawangan,
            'jenis_tuntutan' => 'Pembelaan Awam',
            'keterangan' => 'Tuntutan bagi fail pembelaan awam '.($kes->no_fail ?? ('#'.$kes->id)),
            'jumlah_tuntutan' => 0,
        ], $actor);
    }
}
