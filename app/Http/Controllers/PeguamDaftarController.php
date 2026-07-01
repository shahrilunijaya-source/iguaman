<?php

namespace App\Http\Controllers;

use App\Http\Requests\PeguamDaftarRequest;
use App\Models\ButiranPeguamPanel2;
use App\Models\ButiranPeguamPanel3;
use App\Models\ButiranPeguamPanel4;
use App\Models\ButiranPeguamPanel5;
use App\Models\ButiranPeguamPanel6;
use App\Models\RefKes;
use App\Models\RefNegeri;
use App\Support\LawyerDocuments;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

// Public lawyer panel application - full 7-section parity with legacy daftar.php.
// Writes butiran_peguam_panel_2..6 + 18 PDF docs (permohonan_status='0' Baharu) that
// staff endorse + decide in PermohonanPeguamController. No login required to apply.
class PeguamDaftarController extends Controller
{
    /** jenis_kes code (ref_kes) → display category stored in _6.category. */
    private const KATEGORI = [
        'JEN' => 'JENAYAH',
        'SIV' => 'SIVIL',
        'SYA' => 'SYARIAH',
        'PG' => 'PENDAMPING GUAMAN',
    ];

    public function create(): View
    {
        $bidang = RefKes::query()
            ->whereIn('jenis_kes', array_keys(self::KATEGORI))
            ->where(fn ($q) => $q->where('aktif_kes', '1')->orWhereNull('aktif_kes'))
            ->orderBy('jenis_kes')
            ->orderBy('deskripsi')
            ->get(['jenis_kes', 'deskripsi'])
            ->groupBy('jenis_kes');

        return view('peguam.daftar', [
            'kategoriMap' => self::KATEGORI,
            'bidang' => $bidang,
            'negeriList' => RefNegeri::orderBy('nama')->pluck('nama'),
        ]);
    }

    public function store(PeguamDaftarRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $kp = $data['kpBaru'];

        $permohonan = DB::transaction(function () use ($request, $data, $kp) {
            $base = ButiranPeguamPanel2::create($this->section2($data) + [
                'permohonan_status' => '0',
                'tarikhMohon' => now(),
                // W10: derive the approval track from the selected practice areas.
                'jalur_permohonan' => $this->deriveJalur($data['selected_kes']),
            ]);

            ButiranPeguamPanel3::create($this->section3($data) + ['kpBaru' => $kp]);
            ButiranPeguamPanel4::create($this->section4($data) + ['kpBaru' => $kp]);
            ButiranPeguamPanel5::create($this->section5($data) + ['kpBaru' => $kp]);

            // Section 2 - one pengkhususan row per selected practice area ("CATEGORY::deskripsi").
            foreach ($data['selected_kes'] as $entry) {
                [$category, $value] = array_pad(explode('::', $entry, 2), 2, '');
                ButiranPeguamPanel6::create([
                    'kpBaru' => $kp,
                    'category' => $category,
                    'checkbox_value' => $value !== '' ? $value : $category,
                    'checkbox_value_status' => 0,
                ]);
            }

            LawyerDocuments::store($request, $kp, $data['namaPeguam'], array_keys(PeguamDaftarRequest::DOC_TYPES));

            return $base;
        });

        return redirect()
            ->route('peguam.daftar')
            ->with('daftar_selesai', true)
            ->with('daftar_ref', $permohonan->id);
    }

    /**
     * W10 - derive the approval track from the selected practice areas. Criminal-wins:
     * any JENAYAH selection routes the whole application through the Pembelaan Awam
     * approver tier; otherwise it follows the civil/syariah Peguam Panel tier.
     *
     * @param  array<int,string>  $selectedKes  entries shaped "CATEGORY::deskripsi"
     */
    private function deriveJalur(array $selectedKes): string
    {
        foreach ($selectedKes as $entry) {
            [$category] = array_pad(explode('::', $entry, 2), 2, '');
            if ($category === 'JENAYAH') {
                return ButiranPeguamPanel2::JALUR_JENAYAH;
            }
        }

        return ButiranPeguamPanel2::JALUR_SIVIL_SYARIAH;
    }

    /** Public application-status lookup form (legacy semak.php parity - no login). */
    public function semakStatus(): View
    {
        return view('peguam.semak-status', ['result' => null, 'nokp' => null]);
    }

    /**
     * Look up a panel application's status by IC. Returns only the status label + apply
     * date (+ rejection reason), never login credentials. Throttled to deter IC scanning.
     */
    public function semakStatusCheck(Request $request): View
    {
        $data = $request->validate(
            [
                'kpBaru' => ['required', 'string', 'max:20'],
                'website' => ['prohibited'], // honeypot
            ],
            ['website.prohibited' => 'Permohonan tidak sah.']
        );

        $kp = trim($data['kpBaru']);
        $p = ButiranPeguamPanel2::where('kpBaru', $kp)->orderByDesc('tarikhMohon')->first();

        $result = $p
            ? [
                'found' => true,
                'status' => (string) $p->permohonan_status,
                'label' => PermohonanPeguamController::STATUS[(string) $p->permohonan_status] ?? 'Tidak diketahui',
                'tarikhMohon' => $p->tarikhMohon,
                'sebabTolak' => (string) $p->permohonan_status === '2' ? $p->sebabTidakDiluluskan : null,
            ]
            : ['found' => false];

        return view('peguam.semak-status', ['result' => $result, 'nokp' => $kp]);
    }

    /** _2 - biographical. */
    private function section2(array $d): array
    {
        $cols = [
            'namaPeguam', 'kpBaru', 'kpLama', 'jantina', 'noTelBimbit', 'emelPeguam',
            'kelulusanAkademik', 'tarikhDiterimaMasuk', 'tarikhDiterimaMasukSyarie',
            'tahunPengalaman', 'tahunPengalamanSyarie', 'bilanganKes', 'keteranganKes',
        ];

        return Arr::only($d, $cols) + ['tahunPengalamanSyarie' => $d['tahunPengalamanSyarie'] ?? '0'];
    }

    /** _3 - qualifications (CLP / CSO 1-5 / YBGK / ADR / sijil / eVendor). */
    private function section3(array $d): array
    {
        $cols = [
            'clpNumber', 'clpMula', 'clpAkhir',
            'ybgk_kelulusan', 'ybgk_tarikhLulus_A', 'ybgk_tarikhLulus_B', 'ybgk_daftar',
            'adr_penimbangtara', 'adr_pengantara',
            'sijilAhli_nombor', 'sijilAhli_namaBadan', 'sijilAhli_mula', 'sijilAhli_akhir',
            'sijilAkreditasi_nombor', 'sijilAkreditasi_namaBadan', 'sijilAkreditasi_mula', 'sijilAkreditasi_akhir',
            'eVendor_daftar', 'eVendor_ID',
        ];
        foreach (range(1, 5) as $i) {
            array_push($cols, "csoNumber{$i}", "cso{$i}Tauliah", "cso{$i}Mula", "cso{$i}Akhir", "lokasiBerguam{$i}");
        }

        return Arr::only($d, $cols);
    }

    /** _4 - firma + insurance. */
    private function section4(array $d): array
    {
        return Arr::only($d, [
            'namaFirma', 'alamatFirma1', 'alamatFirma2', 'alamatFirma3', 'poskodFirma',
            'bandarFirma', 'negeriFirma', 'noTelFirma', 'noFaksFirma',
            'namaInsurans', 'noPolisi', 'amaunPerlindungan', 'polisiMula', 'polisiAkhir',
        ]);
    }

    /** _5 - bank account. */
    private function section5(array $d): array
    {
        return Arr::only($d, [
            'namaBank', 'noAkaunBank', 'alamatBank1', 'alamatBank2', 'alamatBank3',
            'poskodBank', 'bandarBank', 'negeriBank',
        ]);
    }
}
