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
        // W10: queue filter by track. Default a criminal-only approver to the JENAYAH
        // queue; everyone else sees all unless they pick a track.
        $jalur = $request->input('jalur', $this->defaultJalur($request));

        $permohonan = ButiranPeguamPanel2::query()
            ->when($status !== null && $status !== '', fn ($w) => $w->where('permohonan_status', $status))
            ->when($jalur !== null && $jalur !== '', fn ($w) => $w->where('jalur_permohonan', $jalur))
            ->orderByDesc('tarikhMohon')
            ->paginate(20)
            ->withQueryString();

        return view('permohonan-peguam.index', [
            'permohonan' => $permohonan,
            'status' => $status,
            'jalur' => $jalur,
            'jalurList' => [ButiranPeguamPanel2::JALUR_SIVIL_SYARIAH, ButiranPeguamPanel2::JALUR_JENAYAH],
            'statusLabels' => self::STATUS,
            'pending' => ButiranPeguamPanel2::where('permohonan_status', '0')->count(),
        ]);
    }

    /** Default queue track from the viewer's permissions (criminal-only approver -> JENAYAH). */
    private function defaultJalur(Request $request): ?string
    {
        $user = $request->user();
        $criminal = $user->can('peguam.sokong.jenayah') || $user->can('peguam.keputusan.jenayah');
        $civil = $user->can('peguam.sokong') || $user->can('peguam.keputusan');

        return ($criminal && ! $civil) ? ButiranPeguamPanel2::JALUR_JENAYAH : null;
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

    /** Tier 2 — Pengarah endorsement (requires PPUU semakan first). W10: criminal
     * applications are endorsed by the Pengarah Pembelaan Awam; civil/syariah by the
     * Pengarah Peguam Panel. */
    public function sokong(Request $request, ButiranPeguamPanel2 $butiran): RedirectResponse
    {
        if (! $request->user()->can($this->sokongPerm($butiran))) {
            return back()->withErrors(['akses' => 'Anda tiada kebenaran menyokong permohonan jalur ini.']);
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
        if (! $request->user()->can($this->keputusanPerm($butiran))) {
            return back()->withErrors(['akses' => 'Anda tiada kebenaran membuat keputusan muktamad bagi jalur ini.']);
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
        // AUTH-07: withdrawal is a decision-level action (was ungated → any staff could withdraw
        // any application), and only a still-pending application may be withdrawn — not one already
        // approved/rejected, which would orphan a live login against a "withdrawn" record.
        if (! $request->user()->can($this->keputusanPerm($butiran))) {
            return back()->withErrors(['akses' => 'Anda tiada kebenaran menarik diri permohonan jalur ini.']);
        }

        if ($butiran->permohonan_status !== '0') {
            return back()->withErrors(['urutan' => 'Hanya permohonan yang belum diputuskan boleh ditarik diri.']);
        }

        $data = $request->validate(['sebabBatal' => ['nullable', 'string', 'max:200']]);

        $butiran->update([
            'permohonan_status' => '3',
            'tarikhBatal' => now()->toDateString(),
            'sebabBatal' => $data['sebabBatal'] ?? null,
        ]);

        Audit::log('butiran_peguam_panel_2', $butiran->id, Audit::UPDATE, "Permohonan peguam ditarik diri: {$butiran->namaPeguam}.");

        return redirect()->route('permohonan-peguam.show', $butiran)->with('status', 'Tarik diri direkodkan.');
    }

    /** Endorsement permission for an application's track (W10). */
    private function sokongPerm(ButiranPeguamPanel2 $butiran): string
    {
        return $butiran->isJenayah() ? 'peguam.sokong.jenayah' : 'peguam.sokong';
    }

    /** Final-decision permission for an application's track (W10). */
    private function keputusanPerm(ButiranPeguamPanel2 $butiran): string
    {
        return $butiran->isJenayah() ? 'peguam.keputusan.jenayah' : 'peguam.keputusan';
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

        $login = User::create([
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

        $login->syncRoles([User::ROLE_PEGUAM]);

        Audit::log('users', 0, Audit::INSERT, "Akaun log masuk peguam dijana: {$b->emelPeguam} (KP {$b->kpBaru}).");

        return $temp;
    }
}
