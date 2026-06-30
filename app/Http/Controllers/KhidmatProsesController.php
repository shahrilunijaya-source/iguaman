<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\KhidmatNasihat;
use App\Models\RefKategoriKn;
use App\Models\User;
use App\Support\KhidmatProsesService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use RuntimeException;

/**
 * Khidmat Nasihat officer processing — batch 11 slices A+B (FE pengesahan-janjitemu
 * / khidmatnasihat officer surface). Gated permission:khidmat.proses throughout.
 *
 * Thin transport layer: branch resolution, list/dashboard rendering, and the
 * assign-PKN + appointment transitions all live in {@see KhidmatProsesService}.
 * A RuntimeException from the service (illegal transition / missing appointment)
 * is converted to a redirect-back-with-error here.
 */
class KhidmatProsesController extends Controller
{
    public function __construct(private readonly KhidmatProsesService $svc) {}

    /** Branch-scoped officer worklist + dashboard count tiles (slice A). */
    public function index(Request $request): View
    {
        $filters = $request->only(['status_kn', 'id_pegawai_kn', 'id_kategori', 'dari', 'hingga', 'q']);

        $khidmat = $this->svc->listQuery($request->user(), $filters)
            ->paginate(25)
            ->withQueryString();

        $counts = $this->svc->dashboardCounts($this->svc->branchFilter($request->user()));

        return view('khidmat-nasihat.proses-index', [
            'khidmat' => $khidmat,
            'filters' => $filters,
            'counts' => $counts,
            'statusList' => KhidmatNasihat::STATUS_KN,
            'kategoriList' => RefKategoriKn::where('aktif', true)->orderBy('jenis_kategori')->get(['id', 'jenis_kategori']),
            'pegawaiList' => $this->pegawaiList(),
        ]);
    }

    /** Assign an advisory officer (PKN): BAHARU -> DALAM_PROSES (slice B). */
    public function assign(Request $request, KhidmatNasihat $khidmat): RedirectResponse
    {
        $data = $request->validate([
            'id_pegawai_kn' => ['required', 'integer', 'exists:users,id'],
        ]);

        return $this->guarded(
            fn () => $this->svc->assignPkn($khidmat, (int) $data['id_pegawai_kn'], $request->user()->name),
            'Pegawai Khidmat Nasihat ditetapkan. Status: Dalam Proses.'
        );
    }

    /** Accept the linked appointment: MENUNGGU -> DISAHKAN. */
    public function terima(Request $request, KhidmatNasihat $khidmat): RedirectResponse
    {
        return $this->guarded(
            fn () => $this->svc->terima($khidmat, $request->user()->name),
            'Janji temu disahkan.'
        );
    }

    /** Reject the linked appointment: MENUNGGU -> BATAL (with reason). */
    public function tolak(Request $request, KhidmatNasihat $khidmat): RedirectResponse
    {
        $data = $request->validate([
            'ulasan_pegawai' => ['nullable', 'string', 'max:1000'],
        ]);

        return $this->guarded(
            fn () => $this->svc->tolak($khidmat, $data['ulasan_pegawai'] ?? null, $request->user()->name),
            'Janji temu ditolak / dibatalkan.'
        );
    }

    /** Mark attendance: DISAHKAN -> HADIR | TIDAK_HADIR. */
    public function kehadiran(Request $request, KhidmatNasihat $khidmat): RedirectResponse
    {
        $data = $request->validate([
            'hadir' => ['required', 'in:0,1'],
        ]);

        return $this->guarded(
            fn () => $this->svc->kehadiran($khidmat, $data['hadir'] === '1', $request->user()->name),
            'Kehadiran direkodkan.'
        );
    }

    /** Complete: appointment HADIR -> SELESAI and khidmat_nasihat -> SELESAI. */
    public function selesai(Request $request, KhidmatNasihat $khidmat): RedirectResponse
    {
        return $this->guarded(
            fn () => $this->svc->selesai($khidmat, $request->user()->name),
            'Permohonan selesai.'
        );
    }

    /**
     * Buka Kes (slice C): open a litigation case (forms row) from a SELESAI KN.
     * On success redirect to the new case; on a guard failure redirect back.
     */
    public function bukaKes(Request $request, KhidmatNasihat $khidmat): RedirectResponse
    {
        try {
            $form = $this->svc->bukaKes($khidmat, $request->user());
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('kes.show', $form)
            ->with('status', 'Kes dibuka. No. Fail: '.$form->no_fail);
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

    /** Active staff users assignable as PKN (id => name) for the filter + assign select. */
    private function pegawaiList()
    {
        return User::query()
            ->where('user_type', User::TYPE_STAFF)
            ->where('is_active', true)
            ->orderBy('name')
            ->pluck('name', 'id');
    }
}
