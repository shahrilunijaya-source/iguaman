<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Exports\LejarTuntutanExport;
use App\Models\Form;
use App\Models\LejarTuntutanBayaran;
use App\Support\LejarTuntutanService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Maatwebsite\Excel\Facades\Excel;
use RuntimeException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * W15 — claim ledger (lejar tuntutan bayaran), officer + approval surface.
 * Thin transport; lifecycle lives in {@see LejarTuntutanService}. The lawyer
 * self-service side is {@see Peguam\TuntutanController}.
 */
class LejarTuntutanController extends Controller
{
    public function __construct(private readonly LejarTuntutanService $svc) {}

    /** Branch-scoped ledger list + dashboard tiles. Gate: tuntutan.view. */
    public function index(Request $request): View
    {
        $filters = $request->only(['status_tuntutan', 'sumber', 'q']);

        $tuntutan = $this->svc->listQuery($request->user(), $filters)
            ->paginate(25)
            ->withQueryString();

        $counts = $this->svc->dashboardCounts($this->svc->branchFilter($request->user()));

        return view('lejar-tuntutan.index', [
            'tuntutan' => $tuntutan,
            'filters' => $filters,
            'counts' => $counts,
            'statusList' => array_keys(LejarTuntutanBayaran::STATUS_LABELS),
        ]);
    }

    public function show(LejarTuntutanBayaran $tuntutan): View
    {
        $tuntutan->load(['form', 'peguam', 'khidmatNasihat', 'pengguna']);

        return view('lejar-tuntutan.show', compact('tuntutan'));
    }

    public function create(Request $request): View
    {
        return view('lejar-tuntutan.borang', [
            'tuntutan' => new LejarTuntutanBayaran(['status_tuntutan' => LejarTuntutanBayaran::STATUS_DRAF]),
            'sumberList' => [
                LejarTuntutanBayaran::SUMBER_KN,
                LejarTuntutanBayaran::SUMBER_PEMBELAAN_AWAM,
                LejarTuntutanBayaran::SUMBER_MEDIASI,
                LejarTuntutanBayaran::SUMBER_PEGUAM_LUAR,
                LejarTuntutanBayaran::SUMBER_LAIN,
            ],
        ]);
    }

    /** Gate: tuntutan.manage. */
    public function store(Request $request): RedirectResponse
    {
        $data = $this->validatePayload($request);

        $row = $this->svc->cipta($data, $request->user()->name);

        return redirect()->route('tuntutan.show', $row)->with('status', "Tuntutan {$row->no_tuntutan} dicipta.");
    }

    /** Gate: tuntutan.manage. */
    public function update(Request $request, LejarTuntutanBayaran $tuntutan): RedirectResponse
    {
        $data = $this->validatePayload($request);
        $data['kemaskini_oleh'] = $request->user()->name;
        $tuntutan->update($data);

        return redirect()->route('tuntutan.show', $tuntutan)->with('status', 'Tuntutan dikemas kini.');
    }

    /** DRAF -> DIHANTAR. Gate: tuntutan.manage. */
    public function hantar(Request $request, LejarTuntutanBayaran $tuntutan): RedirectResponse
    {
        return $this->guarded(fn () => $this->svc->transition($tuntutan, 'hantar', $request->user()->name), 'Tuntutan dihantar.');
    }

    /** DIHANTAR -> SEMAKAN. Gate: tuntutan.semak. */
    public function semak(Request $request, LejarTuntutanBayaran $tuntutan): RedirectResponse
    {
        return $this->guarded(fn () => $this->svc->transition($tuntutan, 'semak', $request->user()->name), 'Tuntutan dalam semakan.');
    }

    /** SEMAKAN -> DILULUS. Gate: tuntutan.lulus. */
    public function lulus(Request $request, LejarTuntutanBayaran $tuntutan): RedirectResponse
    {
        $data = $request->validate([
            'jumlah_diluluskan' => ['nullable', 'numeric', 'min:0'],
            'ulasan_pelulus' => ['nullable', 'string', 'max:1000'],
        ]);

        return $this->guarded(fn () => $this->svc->transition($tuntutan, 'lulus', $request->user()->name, [
            'jumlah_diluluskan' => $data['jumlah_diluluskan'] ?? $tuntutan->jumlah_tuntutan,
            'ulasan_pelulus' => $data['ulasan_pelulus'] ?? null,
            'diluluskan_oleh' => $request->user()->name,
            'tarikh_lulus' => now(),
        ]), 'Tuntutan diluluskan.');
    }

    /** -> DITOLAK. Gate: tuntutan.lulus. */
    public function tolak(Request $request, LejarTuntutanBayaran $tuntutan): RedirectResponse
    {
        $data = $request->validate(['ulasan_pelulus' => ['required', 'string', 'max:1000']]);

        return $this->guarded(fn () => $this->svc->transition($tuntutan, 'tolak', $request->user()->name, [
            'ulasan_pelulus' => $data['ulasan_pelulus'],
            'diluluskan_oleh' => $request->user()->name,
        ]), 'Tuntutan ditolak.');
    }

    /** DILULUS -> DIBAYAR + receipt (G-M3 fix). Gate: tuntutan.bayar. */
    public function bayar(Request $request, LejarTuntutanBayaran $tuntutan): RedirectResponse
    {
        $data = $request->validate([
            'nombor_resit' => ['required', 'string', 'max:50'],
            'tarikh_resit' => ['required', 'date'],
            'kaedah_bayaran' => ['required', 'string', 'max:50'],
            'rujukan_bayaran' => ['nullable', 'string', 'max:100'],
            'jumlah_bayaran' => ['required', 'numeric', 'min:0'],
        ]);

        return $this->guarded(fn () => $this->svc->transition($tuntutan, 'bayar', $request->user()->name, $data + [
            'status_bayaran' => true,
            'tarikh_bayar' => now(),
        ]), 'Bayaran direkodkan.');
    }

    /** Gate: tuntutan.view. */
    public function eksport(Request $request): BinaryFileResponse
    {
        $filters = $request->only(['status_tuntutan', 'sumber', 'q']);
        $query = $this->svc->listQuery($request->user(), $filters);

        return Excel::download(new LejarTuntutanExport($query), 'lejar-tuntutan-'.now()->format('Ymd-His').'.xlsx');
    }

    /**
     * @return array<string,mixed>
     */
    private function validatePayload(Request $request): array
    {
        return $request->validate([
            'sumber' => ['required', 'in:KN,PEMBELAAN_AWAM,MEDIASI,PEGUAM_LUAR,LAIN'],
            'sumber_id' => ['nullable', 'integer'],
            'id_kes' => ['nullable', 'integer', 'exists:forms,id'],
            'kp_peguam' => ['nullable', 'string', 'max:20'],
            'jenis_tuntutan' => ['nullable', 'string', 'max:100'],
            'keterangan' => ['nullable', 'string'],
            'jumlah_tuntutan' => ['required', 'numeric', 'min:0'],
        ]);
    }

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
