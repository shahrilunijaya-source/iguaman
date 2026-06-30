<?php

namespace App\Http\Controllers;

use App\Models\KhidmatNasihat;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Khidmat Nasihat (legal-advisory applications) — batch 9 foundation slice.
 * Read-only list + detail. The 4-step wizard create flow + eligibility screening
 * (and the Audit::log writes that come with them) land in a later slice.
 */
class KhidmatNasihatController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->only(['q', 'status_kn', 'status_bayaran']);

        $khidmat = KhidmatNasihat::query()
            ->with(['cawangan', 'kategori'])
            ->when($filters['q'] ?? null, fn ($w, $v) => $w->where(fn ($q) => $q
                ->where('no_permohonan', 'like', "%{$v}%")
                ->orWhere('nama_mangsa', 'like', "%{$v}%")))
            ->when($filters['status_kn'] ?? null, fn ($w, $v) => $w->where('status_kn', $v))
            ->when(($filters['status_bayaran'] ?? '') !== '', fn ($w) => $w->where('status_bayaran', $filters['status_bayaran'] === '1'))
            ->orderByDesc('id')
            ->paginate(25)
            ->withQueryString();

        return view('khidmat-nasihat.index', [
            'khidmat' => $khidmat,
            'filters' => $filters,
            'statusList' => KhidmatNasihat::STATUS_KN,
        ]);
    }

    public function show(KhidmatNasihat $khidmat): View
    {
        $khidmat->load(['pengguna', 'cawangan', 'kategori', 'subkategori']);

        return view('khidmat-nasihat.show', ['khidmat' => $khidmat]);
    }
}
