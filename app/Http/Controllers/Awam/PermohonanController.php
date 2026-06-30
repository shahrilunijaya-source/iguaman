<?php

namespace App\Http\Controllers\Awam;

use App\Http\Controllers\Controller;
use App\Http\Requests\Awam\AwamPermohonanRequest;
use App\Http\Requests\Awam\AwamRescheduleRequest;
use App\Models\Cawangan;
use App\Models\KhidmatNasihat;
use App\Models\RefKategoriKn;
use App\Models\RefNegeri;
use App\Support\Audit;
use App\Support\KhidmatBayaran;
use App\Support\KhidmatNasihatService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class PermohonanController extends Controller
{
    public function __construct(private readonly KhidmatNasihatService $service) {}

    public function saringan(): View
    {
        return view('awam.permohonan.saringan', ['outcome' => session('awam_saringan')]);
    }

    public function saringanSemak(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'saringan_jenis' => ['required', 'in:sivil_syariah,pendamping_jenayah'],
            'tiada_nasihat_terdahulu' => ['required', 'in:Ya,Tidak'],
            'tiada_perkara_dikecualikan' => ['required', 'in:Ya,Tidak'],
            'pendapatan_bawah_had' => ['nullable', 'in:Ya,Tidak'],
            'terima_terma' => ['accepted'],
        ]);

        $jenis = $data['saringan_jenis'] === 'pendamping_jenayah'
            ? KhidmatNasihat::SARINGAN_PENDAMPING
            : KhidmatNasihat::SARINGAN_SIVIL_SYARIAH;
        $isSivilSyariah = $jenis === KhidmatNasihat::SARINGAN_SIVIL_SYARIAH;

        $eligible = $data['tiada_nasihat_terdahulu'] === 'Ya' && $data['tiada_perkara_dikecualikan'] === 'Ya';
        if (! $eligible) {
            return redirect()->route('awam.permohonan.saringan')
                ->with('saringan_gagal', 'Anda tidak layak memohon kerana tidak memenuhi syarat kelayakan.');
        }

        $request->session()->put('awam_saringan', [
            'jenis' => $jenis,
            'lulus' => true,
            'sumbangan' => $isSivilSyariah && ($data['pendapatan_bawah_had'] ?? 'Ya') === 'Tidak',
        ]);

        return redirect()->route('awam.permohonan.create');
    }

    public function create(): View
    {
        return view('awam.permohonan.form', [
            'outcome' => session('awam_saringan'),
            'cawanganList' => Cawangan::where('status_aktif', true)->orderBy('nama')->get(['id', 'nama', 'kod', 'negeri_id']),
            'kategoriList' => RefKategoriKn::where('aktif', true)->orderBy('jenis_kategori')->get(),
            'negeriList' => RefNegeri::orderBy('nama')->pluck('nama', 'id')->all(),
        ]);
    }

    public function store(AwamPermohonanRequest $request): RedirectResponse
    {
        if ($request->isHantar()) {
            abort_unless(session('awam_saringan.lulus') === true, 403, 'Saringan kelayakan diperlukan.');
        }

        $khidmat = DB::transaction(function () use ($request) {
            $kn = $this->service->create($this->mapInput($request));
            if ($request->isHantar()) {
                $this->service->bookSlot(
                    $kn,
                    $request->validated()['tarikh_temu_janji'],
                    $request->validated()['masa_temu_janji'],
                    $request->user()->name,
                );
            }

            return $kn;
        });

        if ($request->isHantar()) {
            $request->session()->forget('awam_saringan');
        }

        Audit::log('khidmat_nasihat', $khidmat->id, Audit::INSERT,
            "Permohonan awam: {$khidmat->no_permohonan} ({$khidmat->nama_mangsa})");

        return redirect()->route('awam.permohonan.show', $khidmat)
            ->with('status', $request->isHantar() ? 'Permohonan dihantar.' : 'Draf disimpan.');
    }

    public function show(KhidmatNasihat $khidmat): View
    {
        Gate::authorize('view', $khidmat);
        $khidmat->load(['cawangan', 'kategori', 'temuJanji']);

        return view('awam.permohonan.show', ['khidmat' => $khidmat]);
    }

    public function cancel(KhidmatNasihat $khidmat): RedirectResponse
    {
        Gate::authorize('update', $khidmat);
        $this->assertCancellable($khidmat);

        $this->service->releaseSlot($khidmat);
        $khidmat->update(['status_kn' => KhidmatNasihat::STATUS_BATAL]);

        Audit::log('khidmat_nasihat', $khidmat->id, Audit::UPDATE, "Permohonan dibatalkan oleh pemohon: {$khidmat->no_permohonan}");

        return redirect()->route('awam.permohonan.show', $khidmat)->with('status', 'Temu janji dibatalkan.');
    }

    public function reschedule(AwamRescheduleRequest $request, KhidmatNasihat $khidmat): RedirectResponse
    {
        Gate::authorize('update', $khidmat);
        $this->assertCancellable($khidmat);

        $this->service->reschedule(
            $khidmat,
            $request->validated()['tarikh_temu_janji'],
            $request->validated()['masa_temu_janji'],
            $request->user()->name,
        );

        Audit::log('khidmat_nasihat', $khidmat->id, Audit::UPDATE, "Temu janji dijadual semula: {$khidmat->no_permohonan}");

        return redirect()->route('awam.permohonan.show', $khidmat)->with('status', 'Temu janji dijadual semula.');
    }

    /** Self-service cancel/reschedule allowed only before attendance + on a future date. */
    private function assertCancellable(KhidmatNasihat $khidmat): void
    {
        $temu = $khidmat->temuJanji()->first();
        abort_if($temu === null, 422, 'Tiada temu janji untuk diubah.');
        abort_if(in_array($temu->status, ['HADIR', 'TIDAK_HADIR', 'SELESAI', 'BATAL'], true), 422, 'Temu janji ini tidak boleh diubah.');
        abort_if(\Illuminate\Support\Carbon::parse($temu->tarikh_temu_janji)->isPast(), 422, 'Temu janji lampau tidak boleh diubah.');
    }

    private function mapInput(AwamPermohonanRequest $request): array
    {
        $v = $request->validated();
        $saringan = session('awam_saringan');
        $kategori = RefKategoriKn::find($v['id_kategori'] ?? null);
        $fee = KhidmatBayaran::kira($kategori?->jenis_kategori, $v['jumlah_pendapatan'] ?? null, false, null);

        return [
            'jenis_permohonan' => 'DIRI_SENDIRI',
            'id_pengguna' => $request->user()->id,
            'saringan_jenis' => $saringan['jenis'] ?? null,
            'saringan_lulus' => (bool) ($saringan['lulus'] ?? false),
            'is_laluan_sumbangan' => (bool) ($saringan['sumbangan'] ?? false),
            'nama_mangsa' => $v['nama_mangsa'],
            'id_pengenalan_mangsa' => $v['id_pengenalan_mangsa'] ?? $request->user()->nokp,
            'jantina_mangsa' => $v['jantina_mangsa'] ?? null,
            'umur_mangsa' => $v['umur_mangsa'] ?? null,
            'bangsa' => $v['bangsa'] ?? null,
            'agama' => $v['agama'] ?? null,
            'tarikh_lahir_mangsa' => $v['tarikh_lahir_mangsa'] ?? null,
            'alamat_surat1' => $v['alamat_surat1'] ?? null,
            'alamat_surat2' => $v['alamat_surat2'] ?? null,
            'alamat_surat3' => $v['alamat_surat3'] ?? null,
            'poskod' => $v['poskod'] ?? null,
            'cawangan_id' => $v['cawangan_id'],
            'id_kategori' => $v['id_kategori'] ?? null,
            'id_subkategori' => $v['id_subkategori'] ?? null,
            'id_negeri' => $v['id_negeri'] ?? null,
            'jenis_kes' => $v['jenis_kes'] ?? null,
            'jumlah_pendapatan' => $v['jumlah_pendapatan'] ?? null,
            'ulasan_permohonan' => $v['ulasan_permohonan'] ?? null,
            'jumlah_bayaran' => $fee,
            'is_percuma' => false,
            'perakuan' => $request->isHantar() ? $request->boolean('perakuan') : false,
            'status_kn' => $request->isHantar() ? KhidmatNasihat::STATUS_BAHARU : KhidmatNasihat::STATUS_DRAF,
            'cipta_oleh' => $request->user()->name,
            'kemaskini_oleh' => $request->user()->name,
        ];
    }
}
