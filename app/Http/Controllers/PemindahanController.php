<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\PemindahanCawangan;
use App\Support\TransferCawanganService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use RuntimeException;

/**
 * W7 + W3 - branch-transfer inbox. The destination branch accepts (acknowledge)
 * or rejects (reverse the label) a pending transfer of a case or advisory.
 * Initiation lives on KesController / KhidmatNasihatController; this is the
 * shared accept/reject surface over the polymorphic pemindahan_cawangan ledger.
 * Gated permission:kes.pindah (branch managers).
 */
class PemindahanController extends Controller
{
    public function __construct(private readonly TransferCawanganService $svc) {}

    public function index(Request $request): View
    {
        $user = $request->user();

        $pindahan = $this->svc->listForUser($user)->paginate(20)->withQueryString();

        // Flag rows this branch may act on (pending + destination is mine).
        $pindahan->getCollection()->each(
            fn (PemindahanCawangan $p) => $p->setAttribute('boleh_act', $this->svc->canActOn($p, $user))
        );

        return view('pemindahan.index', ['pindahan' => $pindahan]);
    }

    public function terima(Request $request, PemindahanCawangan $pindah): RedirectResponse
    {
        try {
            $this->svc->terima($pindah, $request->user());
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('status', 'Pemindahan diterima.');
    }

    public function tolak(Request $request, PemindahanCawangan $pindah): RedirectResponse
    {
        $data = $request->validate([
            'sebab_tolak' => ['required', 'string', 'max:1000'],
        ], [
            'sebab_tolak.required' => 'Sila nyatakan sebab penolakan.',
        ]);

        try {
            $this->svc->tolak($pindah, $data['sebab_tolak'], $request->user());
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('status', 'Pemindahan ditolak - rekod dikembalikan ke cawangan asal.');
    }
}
