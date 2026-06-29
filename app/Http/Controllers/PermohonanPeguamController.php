<?php

namespace App\Http\Controllers;

use App\Models\ButiranPeguamPanel2;
use App\Models\PeguamPanel;
use App\Models\User;
use App\Support\Audit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
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

    /** Tier 1 — PPUU / Pembantu Tadbir initial review (semakan). */
    public function semak(Request $request, ButiranPeguamPanel2 $butiran): RedirectResponse
    {
        if (! $request->user()->can('peguam.semak')) {
            return back()->withErrors(['akses' => 'Hanya PPUU / Pembantu Tadbir boleh menyemak permohonan.']);
        }

        $data = $request->validate([
            'semakan_ppuu' => ['required', 'in:0,1'],
            'ulasan_semakan_ppuu' => ['nullable', 'string', 'max:600'],
        ]);

        $butiran->update($data + ['tarikh_semakan_ppuu' => now()]);

        return redirect()->route('permohonan-peguam.show', $butiran)->with('status', 'Semakan PPUU direkodkan.');
    }

    /** Tier 2 — Pengarah endorsement (requires PPUU semakan first). */
    public function sokong(Request $request, ButiranPeguamPanel2 $butiran): RedirectResponse
    {
        if (! $request->user()->can('peguam.sokong')) {
            return back()->withErrors(['akses' => 'Hanya Pengarah boleh memberi sokongan.']);
        }

        if ($butiran->semakan_ppuu !== '1') {
            return back()->withErrors(['urutan' => 'Permohonan perlu disemak & disokong oleh PPUU terlebih dahulu.']);
        }

        $data = $request->validate([
            'sokonganPengarah' => ['required', 'in:0,1'],
            'ulasan_sokonganPengarah' => ['nullable', 'string', 'max:600'],
        ]);

        $butiran->update($data + ['tarikh_sokonganPengarah' => now()]);

        return redirect()->route('permohonan-peguam.show', $butiran)->with('status', 'Sokongan Pengarah direkodkan.');
    }

    /** Tier 3 — Ketua Pengarah final decision (requires Pengarah sokong first). Approve promotes into peguam_panel. */
    public function keputusan(Request $request, ButiranPeguamPanel2 $butiran): RedirectResponse
    {
        if (! $request->user()->can('peguam.keputusan')) {
            return back()->withErrors(['akses' => 'Hanya Ketua Pengarah boleh membuat keputusan muktamad.']);
        }

        if ($butiran->sokonganPengarah !== '1') {
            return back()->withErrors(['urutan' => 'Permohonan perlu disokong oleh Pengarah terlebih dahulu.']);
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
            $tempPassword = $this->promote($butiran);
            Audit::log('butiran_peguam_panel_2', $butiran->id, Audit::APPROVE, "Permohonan peguam diluluskan: {$butiran->namaPeguam}.");
            $msg = 'Permohonan diluluskan dan peguam ditambah ke panel.';
            if ($tempPassword !== null) {
                $msg .= " Akaun log masuk dijana untuk {$butiran->emelPeguam} — kata laluan sementara: {$tempPassword} (peguam wajib menukar pada log masuk pertama).";
            }
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

    /**
     * Promote an approved application into the panel: create the peguam_panel master
     * (idempotent by kpBaru) AND provision the lawyer's login account.
     * Returns the generated temp password if a new login was created, else null.
     */
    private function promote(ButiranPeguamPanel2 $b): ?string
    {
        if (! PeguamPanel::where('kp_peguam', $b->kpBaru)->exists()) {
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

        return $this->provisionLogin($b);
    }

    /**
     * Create the lawyer's unified login (users) row so an approved panel lawyer can sign in.
     * Idempotent: skips if a login already exists for this IC or email. Returns the plaintext
     * temp password (forced reset on first login) when a new account is created, else null.
     */
    private function provisionLogin(ButiranPeguamPanel2 $b): ?string
    {
        $exists = User::where('id_peguam_panel', $b->kpBaru)
            ->when($b->emelPeguam, fn ($q) => $q->orWhere('email', $b->emelPeguam))
            ->exists();

        if ($exists) {
            return null;
        }

        $temp = Str::password(10, symbols: false);

        User::create([
            'name' => $b->namaPeguam,
            'email' => $b->emelPeguam,
            'password' => $temp, // User casts password => 'hashed' (bcrypt on set)
            'user_type' => User::TYPE_LAWYER,
            'role' => User::ROLE_PEGUAM,
            'id_peguam_panel' => $b->kpBaru,
            'nokp' => $b->kpBaru,
            'is_active' => true,
            'must_change_password' => true,
        ]);

        Audit::log('users', 0, Audit::INSERT, "Akaun log masuk peguam dijana: {$b->emelPeguam} (KP {$b->kpBaru}).");

        return $temp;
    }
}
