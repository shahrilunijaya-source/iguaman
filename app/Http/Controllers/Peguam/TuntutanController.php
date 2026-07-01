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
 * W15 - lawyer self-service claim ledger (peguam area, gate lawyer.area).
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

        // W5: if a KN-seeded DRAF PEGUAM_LUAR claim already exists for this case, complete it
        // instead of filing a parallel row (the (sumber,id_khidmat_nasihat) unique key cannot
        // catch a free-form row whose id_khidmat_nasihat is NULL).
        $seeded = LejarTuntutanBayaran::where('sumber', LejarTuntutanBayaran::SUMBER_PEGUAM_LUAR)
            ->where('id_kes', $kes->id)
            ->where('kp_peguam', $this->lawyerKp())
            ->where('status_tuntutan', LejarTuntutanBayaran::STATUS_DRAF)
            ->whereNotNull('id_khidmat_nasihat')
            ->first();

        if ($seeded !== null) {
            return redirect()->route('peguam.tuntutan.show', $seeded)
                ->with('status', 'Tuntutan Peguam Luar untuk kes ini telah wujud - sila lengkapkan dan hantar di bawah.');
        }

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

    /**
     * W5 - fill the amount on a KN-seeded DRAF PEGUAM_LUAR claim and submit it (DRAF -> DIHANTAR).
     * The only lawyer-driven way to complete a row seeded at external-lawyer assignment.
     */
    public function lengkap(Request $request, LejarTuntutanBayaran $tuntutan): RedirectResponse
    {
        abort_unless($tuntutan->kp_peguam === $this->lawyerKp(), 403, 'Tuntutan ini bukan milik anda.');
        abort_unless($tuntutan->status_tuntutan === LejarTuntutanBayaran::STATUS_DRAF, 422, 'Tuntutan ini telah dihantar.');

        $data = $request->validate([
            'jenis_tuntutan' => ['required', 'string', 'max:100'],
            'keterangan' => ['nullable', 'string'],
            'jumlah_tuntutan' => ['required', 'numeric', 'min:0.01'],
        ]);

        // transition() fills the extra fields then guards DRAF -> DIHANTAR atomically under a row lock.
        $this->svc->transition($tuntutan, 'hantar', Auth::user()->name, [
            'jenis_tuntutan' => $data['jenis_tuntutan'],
            'keterangan' => $data['keterangan'] ?? null,
            'jumlah_tuntutan' => $data['jumlah_tuntutan'],
        ]);

        return redirect()->route('peguam.tuntutan.show', $tuntutan)
            ->with('status', "Tuntutan {$tuntutan->no_tuntutan} dihantar.");
    }

    /** The signed-in lawyer's IC (kpBaru) - the ledger join key. */
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
