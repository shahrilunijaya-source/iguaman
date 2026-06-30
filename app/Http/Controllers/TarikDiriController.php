<?php

namespace App\Http\Controllers;

use App\Models\Form;
use App\Models\User;
use App\Support\StatusAgihan;
use App\Support\TarikDiriService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Staff-side review of lawyer "Tarik Diri Mewakili OYD" requests: the PPUU → Pengarah →
 * Ketua Pengarah chain (12→16→17→6/2). Lawyers initiate withdrawal in PeguamController;
 * all transitions run through TarikDiriService.
 */
class TarikDiriController extends Controller
{
    /** Queue of in-flight withdrawal requests (status 12/16/17). */
    public function senarai(): View
    {
        $kes = Form::query()
            ->whereIn('status_agihan', StatusAgihan::BUCKET_TARIK_DIRI)
            ->orderByDesc('id')
            ->paginate(20);

        return view('tarik-diri.senarai', ['kes' => $kes]);
    }

    /** Role-routed review page for one withdrawal request. */
    public function show(Form $kes): View
    {
        $status = StatusAgihan::normalise($kes->status_agihan);
        $user = request()->user();

        return view('tarik-diri.maklumat', [
            'kes' => $kes,
            'status' => $status,
            'statusLabel' => StatusAgihan::label($kes->status_agihan),
            'rec' => TarikDiriService::aktif($kes->id),
            'stage' => $this->stage($status, $user),
        ]);
    }

    /** Stage 2 — PPUU review (12→16). */
    public function ppuu(Request $request, Form $kes, TarikDiriService $svc): RedirectResponse
    {
        $data = $request->validate(['ulasan' => ['required', 'string', 'max:350']]);
        $this->ensureStatus($kes, StatusAgihan::DALAM_PROSES_TARIK_DIRI);

        $svc->ppuuSemak($kes, $request->user(), $data['ulasan']);

        return back()->with('status', 'Semakan PPUU direkodkan — dihantar kepada Pengarah.');
    }

    /** Stage 3 — Pengarah review (16→17). */
    public function pengarah(Request $request, Form $kes, TarikDiriService $svc): RedirectResponse
    {
        $data = $request->validate(['ulasan' => ['required', 'string', 'max:350']]);
        $this->ensureStatus($kes, StatusAgihan::SEMAKAN_PENGARAH_TD);

        $svc->pengarahSemak($kes, $request->user(), $data['ulasan']);

        return back()->with('status', 'Semakan Pengarah direkodkan — dihantar kepada Ketua Pengarah.');
    }

    /** Stage 4 — Ketua Pengarah decision (17→6 lulus / 2 tolak). */
    public function kp(Request $request, Form $kes, TarikDiriService $svc): RedirectResponse
    {
        $data = $request->validate([
            'keputusan' => ['required', 'in:lulus,tolak'],
            'ulasan' => ['nullable', 'string', 'max:350', 'required_if:keputusan,tolak'],
        ]);
        $this->ensureStatus($kes, StatusAgihan::SEMAKAN_KP_TD);

        $approve = $data['keputusan'] === 'lulus';
        $svc->kpKeputusan($kes, $request->user(), $approve, $data['ulasan'] ?? '');

        return back()->with('status', $approve
            ? 'Tarik diri diluluskan — kes dikembalikan untuk agihan semula.'
            : 'Tarik diri tidak diluluskan — peguam meneruskan kes.');
    }

    private function stage(?string $status, User $user): ?string
    {
        $is = fn (array $roles) => $user->hasRole($roles);

        return match (true) {
            $status === StatusAgihan::DALAM_PROSES_TARIK_DIRI && $is([User::ROLE_PPUU, User::ROLE_KOORDINATOR, User::ROLE_ADMIN]) => 'ppuu',
            $status === StatusAgihan::SEMAKAN_PENGARAH_TD && $is([User::ROLE_PENGARAH, User::ROLE_ADMIN]) => 'pengarah',
            $status === StatusAgihan::SEMAKAN_KP_TD && $is([User::ROLE_KETUA_PENGARAH, User::ROLE_ADMIN]) => 'kp',
            default => null,
        };
    }

    private function ensureStatus(Form $kes, string $expected): void
    {
        abort_unless(StatusAgihan::normalise($kes->status_agihan) === $expected, 422, 'Status tarik diri telah berubah. Sila muat semula.');
    }
}
