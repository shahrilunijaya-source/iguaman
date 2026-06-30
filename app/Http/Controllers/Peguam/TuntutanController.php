<?php

declare(strict_types=1);

namespace App\Http\Controllers\Peguam;

use App\Http\Controllers\Controller;
use App\Models\Form;
use App\Models\LejarTuntutanBayaran;
use App\Support\LejarTuntutanService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * W15 — lawyer self-service claim ledger (peguam area, gate lawyer.area).
 * A lawyer files claims against cases assigned to them and tracks their status.
 */
class TuntutanController extends Controller
{
    public function __construct(private readonly LejarTuntutanService $svc) {}

    public function index(): View
    {
        $kp = $this->lawyerKp();

        $tuntutan = LejarTuntutanBayaran::query()
            ->with('form')
            ->when($kp, fn ($w) => $w->where('kp_peguam', $kp), fn ($w) => $w->whereRaw('1 = 0'))
            ->orderByDesc('id')
            ->paginate(20);

        return view('peguam.tuntutan-index', compact('tuntutan'));
    }

    /** File a claim against a case currently assigned to the signed-in lawyer. */
    public function create(Form $kes): View
    {
        $this->authorizeCase($kes);

        return view('peguam.tuntutan-borang', compact('kes'));
    }

    public function store(Request $request, Form $kes): RedirectResponse
    {
        $this->authorizeCase($kes);

        $data = $request->validate([
            'jenis_tuntutan' => ['required', 'string', 'max:100'],
            'keterangan' => ['nullable', 'string'],
            'jumlah_tuntutan' => ['required', 'numeric', 'min:0'],
        ]);

        $profile = Auth::user()->lawyerProfile;

        $row = $this->svc->cipta([
            'sumber' => LejarTuntutanBayaran::SUMBER_PEGUAM_LUAR,
            'id_kes' => $kes->id,
            'id_peguam_panel' => $profile?->id,
            'kp_peguam' => $this->lawyerKp(),
            'id_pengguna' => Auth::id(),
            'cawangan' => $kes->cawangan,
            'jenis_tuntutan' => $data['jenis_tuntutan'],
            'keterangan' => $data['keterangan'] ?? null,
            'jumlah_tuntutan' => $data['jumlah_tuntutan'],
            'status_tuntutan' => LejarTuntutanBayaran::STATUS_DIHANTAR,
        ], Auth::user()->name);

        return redirect()->route('peguam.tuntutan.show', $row)->with('status', "Tuntutan {$row->no_tuntutan} dihantar.");
    }

    public function show(LejarTuntutanBayaran $tuntutan): View
    {
        abort_unless($tuntutan->kp_peguam === $this->lawyerKp(), 403, 'Tuntutan ini bukan milik anda.');

        $tuntutan->load('form');

        return view('peguam.tuntutan-show', compact('tuntutan'));
    }

    /** The signed-in lawyer's IC (kpBaru) — the ledger join key. */
    private function lawyerKp(): ?string
    {
        $user = Auth::user();

        return $user->lawyerProfile?->kp_peguam ?: ($user->id_peguam_panel ?: $user->nokp ?: null);
    }

    private function authorizeCase(Form $kes): void
    {
        $profile = Auth::user()->lawyerProfile;
        abort_unless($profile && $kes->nama_pegawai_yang_dapat_kes === $profile->nama_peguam, 403, 'Kes ini bukan milik anda.');
    }
}
