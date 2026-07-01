<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\KhidmatNasihat;
use App\Models\PeguamPanel;
use App\Support\AgihanLuarService;
use App\Support\PeguamShortlistService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use RuntimeException;

/**
 * W5 - officer surface for assigning a completed Khidmat Nasihat to an external
 * panel lawyer (GRAB pool or direct ASSIGN). Gated permission:agihan.luar.
 * Transport only; the guarded state machine lives in {@see AgihanLuarService}.
 */
class AgihanLuarController extends Controller
{
    public function __construct(private readonly AgihanLuarService $svc) {}

    /** Branch-scoped worklist of SELESAI KNs eligible for / in external-lawyer assignment. */
    public function index(Request $request): View
    {
        $filters = $request->only(['status_agihan_pl', 'q']);

        $kn = $this->svc->listQuery($request->user(), $filters)
            ->paginate(20)
            ->withQueryString();

        return view('agihan-luar.index', [
            'kn' => $kn,
            'filters' => $filters,
        ]);
    }

    /** Open a KN to the grab pool (-> BUKA_GRAB). */
    public function bukaGrab(Request $request, KhidmatNasihat $khidmat): RedirectResponse
    {
        return $this->guarded(
            fn () => $this->svc->bukaGrab($khidmat, $request->user()),
            'KN dibuka untuk grab peguam panel.'
        );
    }

    /** Direct-assign picker: the W11 workload shortlist of active panel lawyers. */
    public function agihForm(Request $request, KhidmatNasihat $khidmat): View|RedirectResponse
    {
        // Branch guard - KN has no CawanganScope; a branch-pinned officer cannot view another branch's KN.
        $branchId = $this->svc->branchFilter($request->user());
        if ($branchId !== null && (int) $khidmat->cawangan_id !== $branchId) {
            return redirect()->route('agihan-luar.index')->with('error', 'KN ini bukan di bawah cawangan anda.');
        }

        $shortlist = app(PeguamShortlistService::class)->shortlist(['limit' => 20]);

        return view('agihan-luar.agih', [
            'kn' => $khidmat,
            'shortlist' => $shortlist,
        ]);
    }

    /** Un-assign a mis-assigned KN (DIAGIH -> available again). */
    public function tarikSemula(Request $request, KhidmatNasihat $khidmat): RedirectResponse
    {
        return $this->guarded(
            fn () => $this->svc->tarikSemula($khidmat, $request->user()),
            'Agihan peguam luar ditarik semula. KN boleh diagihkan semula.'
        );
    }

    /** Direct-assign a chosen lawyer (-> DIAGIH + seed PEGUAM_LUAR ledger row). */
    public function assign(Request $request, KhidmatNasihat $khidmat): RedirectResponse
    {
        $data = $request->validate([
            'id_peguam_panel' => ['required', 'integer', 'exists:peguam_panel,id'],
        ]);

        $peguam = PeguamPanel::findOrFail((int) $data['id_peguam_panel']);

        try {
            $this->svc->assign($khidmat, $peguam, $request->user());
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('agihan-luar.index')
            ->with('status', "KN diagihkan kepada peguam panel {$peguam->nama_peguam}.");
    }

    /** Run a service action; redirect back with success or the guard error message. */
    private function guarded(callable $action, string $success): RedirectResponse
    {
        try {
            $action();
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('status', $success);
    }
}
