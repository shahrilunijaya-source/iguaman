<?php

namespace App\Http\Controllers;

use App\Models\ButiranPeguamPanel2;
use App\Models\PeguamPanel;
use App\Models\User;
use App\Support\Audit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

// Lawyer panel application workflow (butiran_peguam_panel_2):
// apply (status 0) -> Pengarah endorse -> KP/Admin decide (approve promotes to peguam_panel) -> or tarik diri.
class PermohonanPeguamController extends Controller
{
    /** permohonan_status code -> label. */
    public const STATUS = ['0' => 'Baharu', '1' => 'Lulus', '2' => 'Tidak Lulus', '3' => 'Tarik Diri'];

    public function index(Request $request): View
    {
        $status = $request->input('status');

        $permohonan = ButiranPeguamPanel2::query()
            ->when($status !== null && $status !== '', fn ($w) => $w->where('permohonan_status', $status))
            ->orderByDesc('tarikhMohon')
            ->paginate(20)
            ->withQueryString();

        return view('permohonan-peguam.index', [
            'permohonan' => $permohonan,
            'status' => $status,
            'statusLabels' => self::STATUS,
            'pending' => ButiranPeguamPanel2::where('permohonan_status', '0')->count(),
        ]);
    }

    public function show(ButiranPeguamPanel2 $butiran): View
    {
        return view('permohonan-peguam.show', [
            'p' => $butiran,
            'statusLabels' => self::STATUS,
        ]);
    }

    /** Pengarah endorsement. */
    public function sokong(Request $request, ButiranPeguamPanel2 $butiran): RedirectResponse
    {
        if (! $request->user()->hasRole(User::ROLE_PENGARAH, User::ROLE_ADMIN)) {
            return back()->withErrors(['akses' => 'Hanya Pengarah boleh memberi sokongan.']);
        }

        $data = $request->validate([
            'sokonganPengarah' => ['required', 'in:0,1'],
            'ulasan_sokonganPengarah' => ['nullable', 'string', 'max:600'],
        ]);

        $butiran->update($data + ['tarikh_sokonganPengarah' => now()]);

        return redirect()->route('permohonan-peguam.show', $butiran)->with('status', 'Sokongan Pengarah direkodkan.');
    }

    /** KP/Admin final decision. Approve promotes the applicant into peguam_panel. */
    public function keputusan(Request $request, ButiranPeguamPanel2 $butiran): RedirectResponse
    {
        if (! $request->user()->hasRole(User::ROLE_ADMIN, User::ROLE_KOORDINATOR)) {
            return back()->withErrors(['akses' => 'Hanya Admin / Koordinator boleh membuat keputusan.']);
        }

        $data = $request->validate([
            'keputusan' => ['required', 'in:lulus,tolak'],
            'ulasan' => ['nullable', 'string', 'max:200'],
        ]);

        if ($data['keputusan'] === 'lulus') {
            $butiran->update([
                'permohonan_status' => '1',
                'ulasan_keputusanKP' => $data['ulasan'] ?? null,
                'tarikh_keputusanKP' => now(),
            ]);
            $this->promote($butiran);
            Audit::log('butiran_peguam_panel_2', $butiran->id, Audit::APPROVE, "Permohonan peguam diluluskan: {$butiran->namaPeguam}.");
            $msg = 'Permohonan diluluskan dan peguam ditambah ke panel.';
        } else {
            $butiran->update([
                'permohonan_status' => '2',
                'sebabTidakDiluluskan' => $data['ulasan'] ?? null,
                'tarikhTidakDiluluskan' => now()->toDateString(),
            ]);
            Audit::log('butiran_peguam_panel_2', $butiran->id, Audit::REJECT, "Permohonan peguam tidak diluluskan: {$butiran->namaPeguam}.");
            $msg = 'Permohonan tidak diluluskan.';
        }

        return redirect()->route('permohonan-peguam.show', $butiran)->with('status', $msg);
    }

    public function tarikDiri(Request $request, ButiranPeguamPanel2 $butiran): RedirectResponse
    {
        $data = $request->validate(['sebabBatal' => ['nullable', 'string', 'max:200']]);

        $butiran->update([
            'permohonan_status' => '3',
            'tarikhBatal' => now()->toDateString(),
            'sebabBatal' => $data['sebabBatal'] ?? null,
        ]);

        return redirect()->route('permohonan-peguam.show', $butiran)->with('status', 'Tarik diri direkodkan.');
    }

    /** Create a peguam_panel master record from an approved application (idempotent by kpBaru). */
    private function promote(ButiranPeguamPanel2 $b): void
    {
        if (PeguamPanel::where('kp_peguam', $b->kpBaru)->exists()) {
            return;
        }

        PeguamPanel::create([
            'nama_peguam' => $b->namaPeguam,
            'kp_peguam' => $b->kpBaru,
            'tel_peguam' => $b->noTelBimbit ?: '-',
            'emel_peguam' => $b->emelPeguam ?: '-',
            'nama_firma' => $b->namaFirma ?? '-',
            'alamat_firma_1' => '-',
            'alamat_firma_2' => '-',
            'poskod_firma' => '-',
            'negeri_firma' => '-',
            'tel_firma' => '-',
            'tarikh_penugasan_peguam_panel' => now()->toDateString(),
        ]);
    }
}
