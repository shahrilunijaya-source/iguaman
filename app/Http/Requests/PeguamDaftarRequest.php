<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Public lawyer panel application (daftar peguam panel) - full 7-section parity with
 * legacy daftar.php: Butiran / Pengendalian Kes / Pengkhususan / Kelayakan Profesion /
 * Firma / Akaun Pembayaran / Senarai Semak (18 PDF docs). Writes butiran_peguam_panel_2..6
 * with permohonan_status='0' (Baharu) → staff endorse/decide. No auth (no login yet).
 * Honeypot + throttle guard abuse.
 */
class PeguamDaftarRequest extends FormRequest
{
    /** 18 document upload fields → required? (kept in sync with the controller persist step). */
    public const DOC_TYPES = [
        'kadPengenalan' => true,
        'senaraiKesKendali' => true,
        'sijilAkademik1' => false,
        'sijilAkademik2' => false,
        'sijilAkademik3' => false,
        'clp' => false,
        'cso1' => false, 'cso2' => false, 'cso3' => false, 'cso4' => false, 'cso5' => false,
        'certkelulusanYBGK' => false,
        'certpenimbangtara' => false,
        'certpengantara' => false,
        'profilFirma' => false,
        'insuransTR' => false,
        'penyataBank' => false,
        'sijilEvendor' => false,
    ];

    /** Text fields legacy stored upper-cased (strtoupper(trim())). */
    private const UPPERCASE_FIELDS = [
        'namaPeguam', 'kelulusanAkademik', 'clpNumber',
        'csoNumber1', 'csoNumber2', 'csoNumber3', 'csoNumber4', 'csoNumber5',
        'sijilAhli_nombor', 'sijilAhli_namaBadan', 'sijilAkreditasi_nombor', 'sijilAkreditasi_namaBadan',
        'eVendor_ID', 'namaFirma', 'alamatFirma1', 'alamatFirma2', 'alamatFirma3',
        'bandarFirma', 'negeriFirma', 'namaInsurans', 'namaBank',
        'alamatBank1', 'alamatBank2', 'alamatBank3', 'bandarBank', 'negeriBank',
    ];

    public function authorize(): bool
    {
        return true; // public route
    }

    protected function prepareForValidation(): void
    {
        $merge = [];
        foreach (self::UPPERCASE_FIELDS as $f) {
            $v = $this->input($f);
            if (is_string($v) && $v !== '') {
                $merge[$f] = mb_strtoupper(trim($v));
            }
        }
        if ($merge) {
            $this->merge($merge);
        }
    }

    public function rules(): array
    {
        $rules = [
            // --- Section 1: Butiran Peguam (_2) ---
            'namaPeguam' => ['required', 'string', 'max:255'],
            'kpBaru' => ['required', 'string', 'max:20', 'unique:butiran_peguam_panel_2,kpBaru'],
            'kpLama' => ['nullable', 'string', 'max:20'],
            'jantina' => ['required', 'in:Lelaki,Perempuan'],
            'noTelBimbit' => ['required', 'string', 'max:20'],
            'emelPeguam' => ['required', 'email', 'max:255'],
            'kelulusanAkademik' => ['required', 'string', 'max:500'],
            'tarikhDiterimaMasuk' => ['required', 'date'],
            'tarikhDiterimaMasukSyarie' => ['nullable', 'date'],
            'tahunPengalaman' => ['required', 'integer', 'min:0', 'max:99'],
            'tahunPengalamanSyarie' => ['nullable', 'integer', 'min:0', 'max:99'],
            'bilanganKes' => ['required', 'integer', 'min:0', 'max:99999'],
            'keteranganKes' => ['required', 'string', 'max:2000'],

            // --- Section 2: Pengendalian Kes → pengkhususan (_6) ---
            'selected_kes' => ['required', 'array', 'min:1'],
            'selected_kes.*' => ['string', 'max:600'],

            // --- Section 3: Pengkhususan / Kelayakan (_3) ---
            'clpNumber' => ['required', 'string', 'max:255'],
            'clpMula' => ['required', 'date'],
            'clpAkhir' => ['required', 'date', 'after_or_equal:clpMula'],

            'ybgk_kelulusan' => ['nullable', 'in:Ya,Tidak,Pengecualian'],
            'ybgk_tarikhLulus_A' => ['nullable', 'date', 'required_if:ybgk_kelulusan,Ya'],
            'ybgk_tarikhLulus_B' => ['nullable', 'date'],
            'ybgk_daftar' => ['nullable', 'string', 'max:255'],

            'adr_penimbangtara' => ['nullable', 'in:Ya,Tidak'],
            'adr_pengantara' => ['nullable', 'in:Ya,Tidak'],

            'sijilAhli_nombor' => ['nullable', 'string', 'max:255'],
            'sijilAhli_namaBadan' => ['nullable', 'string', 'max:255'],
            'sijilAhli_mula' => ['nullable', 'date'],
            'sijilAhli_akhir' => ['nullable', 'date'],
            'sijilAkreditasi_nombor' => ['nullable', 'string', 'max:255'],
            'sijilAkreditasi_namaBadan' => ['nullable', 'string', 'max:255'],
            'sijilAkreditasi_mula' => ['nullable', 'date'],
            'sijilAkreditasi_akhir' => ['nullable', 'date'],

            'eVendor_daftar' => ['nullable', 'in:Ya,Tidak'],
            'eVendor_ID' => ['nullable', 'string', 'max:255', 'required_if:eVendor_daftar,Ya'],

            // --- Section 5: Firma (_4) ---
            'namaFirma' => ['required', 'string', 'max:255'],
            'alamatFirma1' => ['nullable', 'string', 'max:255'],
            'alamatFirma2' => ['nullable', 'string', 'max:255'],
            'alamatFirma3' => ['nullable', 'string', 'max:255'],
            'poskodFirma' => ['nullable', 'string', 'max:10'],
            'bandarFirma' => ['nullable', 'string', 'max:255'],
            'negeriFirma' => ['nullable', 'string', 'max:255'],
            'noTelFirma' => ['nullable', 'string', 'max:20'],
            'noFaksFirma' => ['nullable', 'string', 'max:20'],
            'namaInsurans' => ['nullable', 'string', 'max:255'],
            'noPolisi' => ['nullable', 'string', 'max:255'],
            'amaunPerlindungan' => ['nullable', 'string', 'max:255'],
            'polisiMula' => ['nullable', 'date'],
            'polisiAkhir' => ['nullable', 'date'],

            // --- Section 6: Akaun Pembayaran (_5) ---
            'namaBank' => ['required', 'string', 'max:255'],
            'noAkaunBank' => ['required', 'string', 'max:255'],
            'alamatBank1' => ['nullable', 'string', 'max:255'],
            'alamatBank2' => ['nullable', 'string', 'max:255'],
            'alamatBank3' => ['nullable', 'string', 'max:255'],
            'poskodBank' => ['required', 'string', 'max:10'],
            'bandarBank' => ['required', 'string', 'max:255'],
            'negeriBank' => ['required', 'string', 'max:255'],

            // Honeypot - bots fill it, humans never see it. Must be empty/absent.
            'website' => ['prohibited'],
        ];

        // CSO 1-5 number + tauliah + dates (all optional).
        foreach (range(1, 5) as $i) {
            $rules["csoNumber{$i}"] = ['nullable', 'string', 'max:255'];
            $rules["cso{$i}Tauliah"] = ['nullable', 'string', 'max:255'];
            $rules["cso{$i}Mula"] = ['nullable', 'date'];
            $rules["cso{$i}Akhir"] = ['nullable', 'date'];
            $rules["lokasiBerguam{$i}"] = ['nullable', 'string', 'max:255'];
        }

        // 18 PDF document uploads (max 5 MB each).
        foreach (self::DOC_TYPES as $field => $required) {
            $rules[$field] = array_filter([
                $required ? 'required' : 'nullable',
                'file', 'mimes:pdf', 'max:5120',
            ]);
        }

        return $rules;
    }

    public function attributes(): array
    {
        return [
            'namaPeguam' => 'nama peguam',
            'kpBaru' => 'no. KP',
            'jantina' => 'jantina',
            'noTelBimbit' => 'no. telefon bimbit',
            'emelPeguam' => 'emel',
            'kelulusanAkademik' => 'kelulusan akademik',
            'tahunPengalaman' => 'tahun pengalaman',
            'bilanganKes' => 'bilangan kes',
            'keteranganKes' => 'keterangan kes',
            'selected_kes' => 'bidang pengkhususan',
            'clpNumber' => 'no. perakuan CLP',
            'clpMula' => 'tarikh mula CLP',
            'clpAkhir' => 'tarikh akhir CLP',
            'namaFirma' => 'nama firma',
            'namaBank' => 'nama bank',
            'noAkaunBank' => 'no. akaun bank',
            'poskodBank' => 'poskod bank',
            'bandarBank' => 'bandar bank',
            'negeriBank' => 'negeri bank',
            'kadPengenalan' => 'salinan kad pengenalan',
            'senaraiKesKendali' => 'senarai kes dikendalikan',
        ];
    }

    public function messages(): array
    {
        return [
            'kpBaru.unique' => 'No. KP ini telah pun menghantar permohonan.',
            'website.prohibited' => 'Permohonan tidak sah.',
            'selected_kes.required' => 'Sila pilih sekurang-kurangnya satu bidang pengkhususan.',
        ];
    }
}
