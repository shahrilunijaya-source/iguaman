<?php

namespace App\Http\Controllers;

use App\Models\Form;
use App\Models\PeguamPanel;
use App\Models\SejarahPeguamPanel;
use App\Models\SejarahPpuu;
use App\Models\User;
use App\Support\AgihanService;
use App\Support\StatusAgihan;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * 3-tier case-assignment workflow UI (legacy maklumat-agihan-baru host + ppuu/pengarah/
 * ketuapengarah role forms). One detail page routes by role + current status into the
 * right tier action; all state transitions go through AgihanService.
 */
class AgihanSpineController extends Controller
{
    /** bucket key → (status codes, title). */
    private const BUCKETS = [
        'baru' => [StatusAgihan::BUCKET_BARU, 'Pengagihan Baru'],
        'semasa' => [StatusAgihan::BUCKET_SEMASA, 'Pengagihan Semasa'],
        'semula' => [StatusAgihan::BUCKET_SEMULA, 'Pengagihan Semula'],
    ];

    /** Work-queue list for an assignment bucket (baru / semasa / semula). */
    public function senarai(string $bucket): View
    {
        abort_unless(isset(self::BUCKETS[$bucket]), 404);
        [$codes, $title] = self::BUCKETS[$bucket];

        $kes = Form::query()
            ->whereIn('status_agihan', StatusAgihan::bucketValues($codes))
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        return view('agihan.senarai', [
            'kes' => $kes,
            'bucket' => $bucket,
            'title' => $title,
            'buckets' => self::BUCKETS,
        ]);
    }

    /** Role-routed assignment detail + action form for a case. */
    public function show(Form $kes): View
    {
        $status = StatusAgihan::normalise($kes->status_agihan);
        $user = request()->user();
        $rec = SejarahPpuu::aktif($kes->id);

        return view('agihan.maklumat', [
            'kes' => $kes,
            'status' => $status,
            'statusLabel' => StatusAgihan::label($kes->status_agihan),
            'rec' => $rec,
            'stage' => $this->stage($status, $user),
            'ppuuList' => User::whereIn('role', [User::ROLE_PPUU, User::ROLE_KOORDINATOR, User::ROLE_PEMBANTU_TADBIR, User::ROLE_ADMIN])
                ->where('is_active', true)->orderBy('name')->get(['id', 'name', 'role']),
            'peguamList' => PeguamPanel::orderBy('nama_peguam')->get(['id', 'nama_peguam', 'kp_peguam', 'nama_firma']),
            'sejarahPpuu' => SejarahPpuu::where('id_kes', $kes->id)->orderByDesc('id')->get(),
            'sejarahPp' => SejarahPeguamPanel::where('id_kes', $kes->id)->orderByDesc('id')->get(),
        ]);
    }

    /** Tier 1 — Pengarah accepts a new case + assigns a PPUU (0→8). */
    public function pengarahTerima(Request $request, Form $kes, AgihanService $svc): RedirectResponse
    {
        $data = $request->validate(['idPPUU' => ['required', 'integer', 'exists:users,id']]);
        $this->ensureStatus($kes, [StatusAgihan::BARU_PENGARAH]);

        $svc->pengarahTerima($kes, $request->user(), (int) $data['idPPUU']);

        return back()->with('status', 'Agihan baru diterima — diserah kepada PPUU untuk pemilihan peguam.');
    }

    /** Tier 1 — Pengarah rejects a new case (0→9). */
    public function pengarahTolak(Request $request, Form $kes, AgihanService $svc): RedirectResponse
    {
        $data = $request->validate(['sebab' => ['required', 'string', 'max:255']]);
        $this->ensureStatus($kes, [StatusAgihan::BARU_PENGARAH]);

        $svc->pengarahTolakBaru($kes, $request->user(), $data['sebab']);

        return back()->with('status', 'Agihan baru ditolak.');
    }

    /** Tier 2 — PPUU picks a panel lawyer (8→10, or re-pick from 4/15). */
    public function ppuuPilih(Request $request, Form $kes, AgihanService $svc): RedirectResponse
    {
        $data = $request->validate([
            'peguam_id' => ['required', 'integer', 'exists:peguam_panel,id'],
            'pilihan' => ['required', 'in:A,B'],
            'cawangan' => ['nullable', 'string', 'max:100'],
            'ulasan' => ['nullable', 'string', 'max:350'],
        ]);
        $this->ensureStatus($kes, [StatusAgihan::DIAGIH_PPUU, StatusAgihan::PPUU_AGIH_SEMULA, StatusAgihan::KELULUSAN_KP_SEMULA]);

        $peguam = PeguamPanel::findOrFail($data['peguam_id']);
        $svc->ppuuPilih($kes, $request->user(), [
            'pilihan' => $data['pilihan'],
            'cawangan' => $data['cawangan'] ?? null,
            'namaPP' => $peguam->nama_peguam,
            'kpPP' => $peguam->kp_peguam,
            'ulasan' => $data['ulasan'] ?? null,
        ]);

        return back()->with('status', "Peguam {$peguam->nama_peguam} dipilih — dihantar untuk sokongan Pengarah.");
    }

    /** Tier 2 — Pengarah endorses (→13) or rejects (→4) the PPUU pick. */
    public function pengarahKeputusan(Request $request, Form $kes, AgihanService $svc): RedirectResponse
    {
        $data = $request->validate([
            'keputusan' => ['required', 'in:sokong,tidak'],
            'ulasan' => ['nullable', 'string', 'max:600', 'required_if:keputusan,tidak'],
        ]);
        $this->ensureStatus($kes, [StatusAgihan::SOKONGAN_PENGARAH]);

        if ($data['keputusan'] === 'sokong') {
            $svc->pengarahSokong($kes, $request->user(), $data['ulasan'] ?? null);
            $msg = 'Pemilihan disokong — dihantar untuk kelulusan Ketua Pengarah.';
        } else {
            $svc->pengarahTidakSokong($kes, $request->user(), $data['ulasan']);
            $msg = 'Pemilihan tidak disokong — dikembalikan kepada PPUU.';
        }

        return back()->with('status', $msg);
    }

    /** Tier 3 — Ketua Pengarah approves (→1 offer) or rejects (→15) the assignment. */
    public function kpKeputusan(Request $request, Form $kes, AgihanService $svc): RedirectResponse
    {
        $data = $request->validate([
            'keputusan' => ['required', 'in:lulus,tolak'],
            'ulasan' => ['nullable', 'string', 'max:200', 'required_if:keputusan,tolak'],
        ]);
        $this->ensureStatus($kes, [StatusAgihan::KELULUSAN_KP]);

        if ($data['keputusan'] === 'lulus') {
            $svc->kpLulus($kes, $request->user(), $data['ulasan'] ?? null);
            $msg = 'Agihan diluluskan — ditawarkan kepada peguam panel.';
        } else {
            $svc->kpTolak($kes, $request->user(), $data['ulasan']);
            $msg = 'Agihan tidak diluluskan — dikembalikan kepada PPUU.';
        }

        return back()->with('status', $msg);
    }

    /** Which tier form the current user may act on, given the case status. */
    private function stage(?string $status, User $user): ?string
    {
        $is = fn (...$roles) => $user->hasRole(...$roles);

        return match (true) {
            $status === StatusAgihan::BARU_PENGARAH && $is(User::ROLE_PENGARAH, User::ROLE_ADMIN) => 'pengarah_baru',
            in_array($status, [StatusAgihan::DIAGIH_PPUU, StatusAgihan::PPUU_AGIH_SEMULA, StatusAgihan::KELULUSAN_KP_SEMULA], true)
                && $is(User::ROLE_PPUU, User::ROLE_KOORDINATOR, User::ROLE_ADMIN) => 'ppuu_pilih',
            $status === StatusAgihan::SOKONGAN_PENGARAH && $is(User::ROLE_PENGARAH, User::ROLE_ADMIN) => 'pengarah_sokong',
            $status === StatusAgihan::KELULUSAN_KP && $is(User::ROLE_KETUA_PENGARAH, User::ROLE_ADMIN) => 'kp_keputusan',
            default => null,
        };
    }

    /** Guard against acting on a case that has moved past the expected status (double-submit / stale page). */
    private function ensureStatus(Form $kes, array $allowed): void
    {
        abort_unless(in_array(StatusAgihan::normalise($kes->status_agihan), $allowed, true), 422, 'Status agihan kes telah berubah. Sila muat semula.');
    }
}
