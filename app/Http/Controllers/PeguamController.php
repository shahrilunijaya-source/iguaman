<?php

namespace App\Http\Controllers;

use App\Models\Form;
use App\Models\LaporanKes;
use App\Models\PeguamPanel;
use App\Models\SejarahPeguamPanel;
use App\Support\Audit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

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
            'kes_saya' => $nama ? $this->kesQuery($nama)->where('status_agihan', 'Diterima')->count() : 0,
            'tawaran' => $nama ? $this->kesQuery($nama)->where('status_agihan', 'Ditawarkan')->count() : 0,
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
            ? $this->kesQuery($profile->nama_peguam)->where('status_agihan', 'Ditawarkan')->orderByDesc('tarikh_penugasan_peguam_panel')->get()
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

        $kes->update(['status_agihan' => 'Diterima']);
        Audit::log('forms', $kes->id, Audit::UPDATE, "Tawaran kes diterima oleh peguam {$kes->nama_pegawai_yang_dapat_kes}");

        return redirect()->route('peguam.tawaran')->with('status', 'Tawaran kes diterima.');
    }

    public function tolak(Request $request, Form $kes): RedirectResponse
    {
        $this->authorizeCase($kes);

        $data = $request->validate(['alasan' => ['nullable', 'string', 'max:255']]);

        // Log the declining lawyer, then return the case to the unassigned pool.
        SejarahPeguamPanel::create([
            'id_kes' => $kes->id,
            'nama_pp_lama' => $kes->nama_pegawai_yang_dapat_kes,
            'tarikh_penugasan' => $kes->tarikh_penugasan_peguam_panel,
            'status' => 'T',
            'alasan' => $data['alasan'] ?? 'Tawaran ditolak oleh peguam',
            'kp_pp_lama' => null,
            'modifiedBy' => Auth::user()->name,
            'modifiedDate' => now(),
            'status_agihan' => 'T',
        ]);

        $kes->update([
            'nama_pegawai_yang_dapat_kes' => null,
            'agih_kepada' => null,
            'status_agihan' => 'Ditolak',
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
            'id_kes' => (string) $kes->id,
            'no_fail' => $kes->no_fail,
            'nama_pegawai' => Auth::user()->name,
        ]);

        Audit::log('laporan_kes', $laporan->id, Audit::INSERT, "Laporan kes oleh peguam untuk kes #{$kes->id}");

        return redirect()->route('peguam.kes.show', $kes)->with('status', 'Laporan kes direkodkan.');
    }

    public function profil(): View
    {
        $profile = $this->profile();

        return view('peguam.profil', [
            'profile' => $profile,
            'user' => Auth::user(),
            'b' => $profile?->butiran,
        ]);
    }

    /** Panel-lawyer master record for the signed-in user (links via id_peguam_panel = kp_peguam). */
    private function profile(): ?PeguamPanel
    {
        return Auth::user()->lawyerProfile;
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
}
