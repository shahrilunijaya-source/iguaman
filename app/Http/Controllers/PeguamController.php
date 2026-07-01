<?php

namespace App\Http\Controllers;

use App\Http\Requests\PeguamProfilUpdateRequest;
use App\Models\ButiranPeguamPanel2;
use App\Models\ButiranPeguamPanel3;
use App\Models\ButiranPeguamPanel4;
use App\Models\ButiranPeguamPanel5;
use App\Models\ButiranPeguamPanel6;
use App\Models\Form;
use App\Models\KhidmatNasihat;
use App\Models\LaporanKes;
use App\Models\PeguamPanel;
use App\Models\RefKes;
use App\Models\RefNegeri;
use App\Models\SejarahPeguamPanel;
use App\Models\UploadedFile;
use App\Support\AgihanLuarService;
use App\Support\Audit;
use App\Support\PeguamProfilUpdateService;
use App\Support\PengkhususanService;
use App\Support\StatusAgihan;
use App\Support\TarikDiriService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use RuntimeException;

// External lawyer (peguam) area — assigned cases, offer accept/reject (tawaran),
// lawyer-side case reporting, and profile.
class PeguamController extends Controller
{
    /** Days a lawyer has to respond to an offer before it is flagged for reassignment. */
    public const OFFER_DEADLINE_DAYS = 7;

    public function dashboard(): View
    {
        $profile = $this->profile();
        $nama = $profile?->nama_peguam;

        $stats = [
            'kes_saya' => $nama ? $this->kesQuery($nama)->whereIn('status_agihan', StatusAgihan::bucketValues([StatusAgihan::DITERIMA]))->count() : 0,
            'tawaran' => $nama ? $this->kesQuery($nama)->whereIn('status_agihan', StatusAgihan::bucketValues([StatusAgihan::DITAWARKAN]))->count() : 0,
            // W5: open-grab KN pool any panel lawyer can self-claim (branch-agnostic).
            'kes_grab' => app(AgihanLuarService::class)->grabPool()->count(),
            'nama' => $nama ?? Auth::user()->name,
        ];

        return view('peguam.dashboard', compact('stats'));
    }

    public function kes(): View
    {
        $profile = $this->profile();

        $kes = $profile
            ? $this->kesQuery($profile->nama_peguam)->orderByDesc('id')->paginate(20)
            : Form::query()->whereRaw('1 = 0')->paginate(20);

        return view('peguam.kes', ['kes' => $kes, 'profile' => $profile]);
    }

    /** Offered cases awaiting this lawyer's accept/reject. */
    public function tawaran(): View
    {
        $profile = $this->profile();

        $tawaran = $profile
            ? $this->kesQuery($profile->nama_peguam)->whereIn('status_agihan', StatusAgihan::bucketValues([StatusAgihan::DITAWARKAN]))->orderByDesc('tarikh_penugasan_peguam_panel')->get()
            : collect();

        return view('peguam.tawaran', [
            'tawaran' => $tawaran,
            'profile' => $profile,
            'deadlineDays' => self::OFFER_DEADLINE_DAYS,
        ]);
    }

    public function terima(Form $kes): RedirectResponse
    {
        $this->authorizeCase($kes);

        // PROC-21: only an active offer (DITAWARKAN) may be accepted — not a case already
        // withdrawn, closed, or bounced back to the pool while the offer screen was open.
        abort_unless(StatusAgihan::normalise($kes->status_agihan) === StatusAgihan::DITAWARKAN, 409, 'Tawaran ini tidak lagi aktif.');

        $kes->update(['status_agihan' => StatusAgihan::DITERIMA]);
        Audit::log('forms', $kes->id, Audit::UPDATE, "Tawaran kes diterima oleh peguam {$kes->nama_pegawai_yang_dapat_kes}");

        return redirect()->route('peguam.tawaran')->with('status', 'Tawaran kes diterima.');
    }

    public function tolak(Request $request, Form $kes): RedirectResponse
    {
        $this->authorizeCase($kes);

        // PROC-21: can only decline an offer that is still active.
        abort_unless(StatusAgihan::normalise($kes->status_agihan) === StatusAgihan::DITAWARKAN, 409, 'Tawaran ini tidak lagi aktif.');

        $data = $request->validate(['alasan' => ['nullable', 'string', 'max:255']]);

        // Log the declining lawyer, then return the case to the unassigned pool.
        SejarahPeguamPanel::create([
            'id_kes' => $kes->id,
            'nama_pp_lama' => $kes->nama_pegawai_yang_dapat_kes,
            'tarikh_penugasan' => $kes->tarikh_penugasan_peguam_panel,
            'status' => SejarahPeguamPanel::STATUS_TOLAK,
            'alasan' => $data['alasan'] ?? 'Tawaran ditolak oleh peguam',
            'kp_pp_lama' => null,
            'modifiedBy' => Auth::user()->name,
            'modifiedDate' => now(),
            'status_agihan' => SejarahPeguamPanel::STATUS_TOLAK,
        ]);

        // Offer declined → bounce back to the PPUU re-pick pool (numeric '4'), the same
        // terminal the Lebih Masa auto-reassignment uses. Surfaces in the SEMULA bucket.
        $kes->update([
            'nama_pegawai_yang_dapat_kes' => null,
            'agih_kepada' => null,
            'status_agihan' => StatusAgihan::PPUU_AGIH_SEMULA,
        ]);

        Audit::log('forms', $kes->id, Audit::UPDATE, 'Tawaran kes ditolak oleh peguam — dikembalikan untuk agihan semula');

        return redirect()->route('peguam.tawaran')->with('status', 'Tawaran ditolak. Kes dikembalikan kepada JBG.');
    }

    /** Lawyer view of one assigned case + court-report (laporan_kes) entry. */
    public function kesShow(Form $kes): View
    {
        $this->authorizeCase($kes);
        $kes->load('laporanKes');

        return view('peguam.kes-show', ['kes' => $kes]);
    }

    /** Lawyer records case progress as a laporan_kes row. */
    public function storeLaporan(Request $request, Form $kes): RedirectResponse
    {
        $this->authorizeCase($kes);

        $data = $request->validate([
            'pihak_pihak' => ['nullable', 'string', 'max:255'],
            'no_kes' => ['nullable', 'string', 'max:30'],
            'tarikh_sebutan' => ['nullable', 'date'],
            'isu' => ['nullable', 'string', 'max:255'],
            'fakta_ringkas' => ['nullable', 'string'],
            'ringkasan' => ['nullable', 'string'],
            'status_kes' => ['nullable', 'string', 'max:100'],
        ]);

        $laporan = LaporanKes::create($data + [
            'id_kes' => $kes->id,
            'no_fail' => $kes->no_fail,
            'nama_pegawai' => Auth::user()->name,
        ]);

        Audit::log('laporan_kes', $laporan->id, Audit::INSERT, "Laporan kes oleh peguam untuk kes #{$kes->id}");

        return redirect()->route('peguam.kes.show', $kes)->with('status', 'Laporan kes direkodkan.');
    }

    /** Lawyer-side "Tarik Diri Mewakili OYD" request form for an assigned case. */
    public function tarikDiriForm(Form $kes): View
    {
        $this->authorizeCase($kes);
        $this->ensureKesDiterima($kes);

        return view('peguam.tarik-diri', ['kes' => $kes, 'reasons' => TarikDiriService::REASONS]);
    }

    /** Submit a withdrawal request (status 2→12) — enters the PPUU→Pengarah→KP review chain. */
    public function tarikDiriStore(Request $request, Form $kes, TarikDiriService $svc): RedirectResponse
    {
        $this->authorizeCase($kes);
        $this->ensureKesDiterima($kes);

        $data = $request->validate([
            'pilihanTarikDiri' => ['required', Rule::in(TarikDiriService::REASONS)],
            'alasan' => ['required', 'string', 'max:600'],
            'tarikhNextBicaraKes' => ['nullable', 'date'],
            'akuanTarikDiri' => ['nullable', 'file', 'mimes:pdf', 'max:5120'],
        ]);

        $svc->ppSubmit($kes, $request->user(), [
            'pilihanTarikDiri' => $data['pilihanTarikDiri'],
            'alasan' => $data['alasan'],
            'tarikhNextBicaraKes' => $data['tarikhNextBicaraKes'] ?? null,
            'kpBaruPP' => $this->lawyerKp(),
        ]);

        if ($request->hasFile('akuanTarikDiri')) {
            $fileName = "akuanTarikDiri_{$kes->id}.pdf";
            $path = $request->file('akuanTarikDiri')->storeAs("tarikdiri/{$kes->id}", $fileName, 'local');
            UploadedFile::create([
                'nama' => Auth::user()->name,
                'doc_type' => 'akuanTarikDiri',
                'id_kes' => $kes->id,
                'file_name' => $fileName,
                'file_path' => $path,
                'file_type' => 'application/pdf',
            ]);
        }

        Audit::log('forms', $kes->id, Audit::UPDATE, "Permohonan tarik diri dihantar oleh peguam {$kes->nama_pegawai_yang_dapat_kes}.");

        return redirect()->route('peguam.kes')->with('status', 'Permohonan tarik diri dihantar untuk semakan JBG.');
    }

    /**
     * W16 — lawyer marks an actively-handled case as done (status 2 → 18 / PP_SELESAI).
     * Writes the lawyer-side closure columns + a SejarahPeguamPanel marker; the file is
     * not officially closed until JBG confirms (KeputusanController::sahkanSelesai → 19).
     */
    public function selesai(Request $request, Form $kes): RedirectResponse
    {
        $this->authorizeCase($kes);
        $this->ensureKesDiterima($kes);
        // A file already closed by JBG (tutupFail leaves status_agihan untouched) must not be re-opened.
        abort_if(filled($kes->tarikh_tutup_fail), 422, 'Fail telah ditutup dan tidak boleh ditandakan selesai.');

        $data = $request->validate(['sebab_selesai' => ['nullable', 'string', 'max:50']]);

        // Record who completed the case before the state moves (mirrors tolak()).
        SejarahPeguamPanel::create([
            'id_kes' => $kes->id,
            'nama_pp_lama' => $kes->nama_pegawai_yang_dapat_kes,
            'tarikh_penugasan' => $kes->tarikh_penugasan_peguam_panel,
            'status' => SejarahPeguamPanel::STATUS_SELESAI,
            'alasan' => $data['sebab_selesai'] ?? 'Kes ditandakan selesai oleh peguam',
            'kp_pp_lama' => $this->lawyerKp(),
            'modifiedBy' => Auth::user()->name,
            'modifiedDate' => now(),
            'status_agihan' => StatusAgihan::PP_SELESAI,
        ]);

        $kes->update([
            'status_agihan' => StatusAgihan::PP_SELESAI,
            'tarikh_selesai' => now()->toDateString(),
            'sebab_selesai' => $data['sebab_selesai'] ?? null,
        ]);

        Audit::log('forms', $kes->id, Audit::UPDATE, "Kes ditandakan selesai oleh peguam {$kes->nama_pegawai_yang_dapat_kes}");

        return redirect()->route('peguam.kes.show', $kes)->with('status', 'Kes ditandakan selesai. Menunggu pengesahan JBG.');
    }

    /** W5 — open-grab Khidmat Nasihat pool any active panel lawyer may self-claim. */
    public function grabSenarai(): View
    {
        $pool = app(AgihanLuarService::class)->grabPool()->get();

        return view('peguam.grab', [
            'pool' => $pool,
            'profile' => $this->profile(),
            'grabDays' => AgihanLuarService::GRAB_DAYS,
        ]);
    }

    /** W5 — claim an open-grab KN for the signed-in lawyer (race-safe in the service). */
    public function grab(KhidmatNasihat $khidmat): RedirectResponse
    {
        $profile = $this->profile();
        abort_if($profile === null, 403, 'Akaun anda belum dipautkan ke rekod peguam panel.');

        try {
            app(AgihanLuarService::class)->grab($khidmat, $profile, Auth::user());
        } catch (RuntimeException $e) {
            return redirect()->route('peguam.grab.index')->with('error', $e->getMessage());
        }

        return redirect()->route('peguam.grab.index')
            ->with('status', 'Kes berjaya di-grab. Sila failkan tuntutan apabila kerja siap.');
    }

    public function profil(): View
    {
        $profile = $this->profile();
        $kp = $this->lawyerKp();

        return view('peguam.profil', [
            'profile' => $profile,
            'user' => Auth::user(),
            'b' => $profile?->butiran,
            // Detailed self-service profile (butiran_peguam_panel_2..5), keyed by IC.
            'p2' => $kp ? ButiranPeguamPanel2::where('kpBaru', $kp)->first() : null,
            'p3' => $kp ? ButiranPeguamPanel3::where('kpBaru', $kp)->first() : null,
            'p4' => $kp ? ButiranPeguamPanel4::where('kpBaru', $kp)->first() : null,
            'p5' => $kp ? ButiranPeguamPanel5::where('kpBaru', $kp)->first() : null,
            'pengkhususan' => $kp ? ButiranPeguamPanel6::where('kpBaru', $kp)->orderBy('category')->get() : collect(),
            'bidang' => $this->bidangOptions(),
            'kategoriMap' => ['JEN' => 'JENAYAH', 'SIV' => 'SIVIL', 'SYA' => 'SYARIAH', 'PG' => 'PENDAMPING GUAMAN'],
        ]);
    }

    /** Lawyer requests to add a practice area (status 4 → Pengarah → KP). */
    public function pengkhususanAdd(Request $request, PengkhususanService $svc): RedirectResponse
    {
        $kp = $this->lawyerKp();
        abort_if($kp === null, 403);

        $data = $request->validate([
            'category' => ['required', 'string', 'max:100'],
            'checkbox_value' => ['required', 'string', 'max:500'],
        ]);

        $row = $svc->requestAdd($kp, $data['category'], $data['checkbox_value'], $request->user());

        return redirect()->route('peguam.profil')->with('status',
            $row ? 'Permohonan tambah bidang dihantar untuk kelulusan.' : 'Bidang ini sudah ada atau sedang dalam proses.');
    }

    /** Lawyer requests to drop one of their practice areas (status 3 → Pengarah → KP). */
    public function pengkhususanDrop(Request $request, ButiranPeguamPanel6 $row, PengkhususanService $svc): RedirectResponse
    {
        abort_unless($row->kpBaru === $this->lawyerKp(), 403, 'Bidang ini bukan milik anda.');

        $svc->requestDrop($row, $request->user());

        return redirect()->route('peguam.profil')->with('status', 'Permohonan gugur bidang dihantar untuk kelulusan.');
    }

    /** Active practice-area options grouped by jenis_kes (for the add form). */
    private function bidangOptions()
    {
        return RefKes::query()
            ->whereIn('jenis_kes', ['JEN', 'SIV', 'SYA', 'PG'])
            ->where(fn ($q) => $q->where('aktif_kes', '1')->orWhereNull('aktif_kes'))
            ->orderBy('jenis_kes')->orderBy('deskripsi')
            ->get(['jenis_kes', 'deskripsi'])
            ->groupBy('jenis_kes');
    }

    /** Self-service profile edit form (legacy profil.php + profilUpdate.php). */
    public function editProfil(): View
    {
        $kp = $this->lawyerKp();
        abort_if($kp === null, 403, 'Akaun anda belum dipautkan ke rekod peguam panel.');

        return view('peguam.profil-edit', [
            'p2' => ButiranPeguamPanel2::firstOrNew(['kpBaru' => $kp]),
            'p3' => ButiranPeguamPanel3::firstOrNew(['kpBaru' => $kp]),
            'p4' => ButiranPeguamPanel4::firstOrNew(['kpBaru' => $kp]),
            'p5' => ButiranPeguamPanel5::firstOrNew(['kpBaru' => $kp]),
            'docs' => UploadedFile::where('kpBaru', $kp)->pluck('doc_type')->all(),
            'negeriList' => RefNegeri::orderBy('nama')->pluck('nama'),
            'banks' => self::BANKS,
        ]);
    }

    /** Persist self-service profile changes across _2/_3/_4/_5 + document re-uploads. */
    public function updateProfil(PeguamProfilUpdateRequest $request, PeguamProfilUpdateService $svc): RedirectResponse
    {
        $kp = $this->lawyerKp();
        abort_if($kp === null, 403, 'Akaun anda belum dipautkan ke rekod peguam panel.');

        $user = Auth::user();
        $svc->update($request, $request->validated(), $kp, $user);

        Audit::log('butiran_peguam_panel_2', 0, Audit::UPDATE, "Profil peguam dikemaskini oleh {$user->name} (KP {$kp})");

        return redirect()->route('peguam.profil')->with('status', 'Profil berjaya dikemaskini.');
    }

    /** Panel-lawyer master record for the signed-in user (links via id_peguam_panel = kp_peguam). */
    private function profile(): ?PeguamPanel
    {
        return Auth::user()->lawyerProfile;
    }

    /** Common Malaysian banks for the payment-account dropdown. */
    private const BANKS = [
        'Maybank', 'CIMB Bank', 'Public Bank', 'RHB Bank', 'Hong Leong Bank', 'AmBank',
        'Bank Islam', 'Bank Rakyat', 'BSN', 'Affin Bank', 'Alliance Bank', 'OCBC Bank',
        'HSBC', 'UOB', 'Standard Chartered', 'Agrobank', 'MBSB Bank',
    ];

    /** The signed-in lawyer's IC (kpBaru) — the key for butiran_peguam_panel_2..6. */
    private function lawyerKp(): ?string
    {
        $user = Auth::user();

        return $user->id_peguam_panel ?: $user->nokp ?: null;
    }

    /** Cases assigned to a lawyer by name. */
    private function kesQuery(string $namaPeguam)
    {
        return Form::where('nama_pegawai_yang_dapat_kes', $namaPeguam);
    }

    /** Abort unless the case is currently assigned to the signed-in lawyer. */
    private function authorizeCase(Form $kes): void
    {
        $profile = $this->profile();
        abort_unless($profile && $kes->nama_pegawai_yang_dapat_kes === $profile->nama_peguam, 403, 'Kes ini bukan milik anda.');
    }

    /** Tarik diri is only allowed while the lawyer is actively handling the case (status Diterima). */
    private function ensureKesDiterima(Form $kes): void
    {
        abort_unless(StatusAgihan::normalise($kes->status_agihan) === StatusAgihan::DITERIMA, 422, 'Tarik diri hanya boleh dimohon untuk kes yang sedang dikendalikan.');
    }
}
