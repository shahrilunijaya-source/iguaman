<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

/**
 * Logged-in panel lawyer self-service profile update (legacy profilUpdate.php).
 * Writes the editable subset of butiran_peguam_panel_2/_3/_4/_5 + re-uploads.
 * Identity fields (namaPeguam/kpBaru/kpLama/jantina) are readonly - never accepted here.
 * FIXES the legacy bug where cso4/cso5 documents could not be replaced (all 18 docs editable).
 */
class PeguamProfilUpdateRequest extends FormRequest
{
    private const UPPERCASE_FIELDS = [
        'kelulusanAkademik', 'clpNumber',
        'csoNumber1', 'csoNumber2', 'csoNumber3', 'csoNumber4', 'csoNumber5',
        'sijilAhli_nombor', 'sijilAhli_namaBadan', 'sijilAkreditasi_nombor', 'sijilAkreditasi_namaBadan',
        'eVendor_ID', 'namaFirma', 'alamatFirma1', 'alamatFirma2', 'alamatFirma3',
        'bandarFirma', 'negeriFirma', 'namaInsurans', 'namaBank',
        'alamatBank1', 'alamatBank2', 'alamatBank3', 'bandarBank', 'negeriBank',
    ];

    public function authorize(): bool
    {
        return Auth::check() && Auth::user()->isLawyer();
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
            // _2 editable subset
            'noTelBimbit' => ['required', 'string', 'max:20'],
            'emelPeguam' => ['required', 'email', 'max:255'],
            'kelulusanAkademik' => ['required', 'string', 'max:500'],
            'tarikhDiterimaMasuk' => ['required', 'date'],
            'tarikhDiterimaMasukSyarie' => ['nullable', 'date'],
            'tahunPengalaman' => ['required', 'integer', 'min:0', 'max:99'],
            'tahunPengalamanSyarie' => ['nullable', 'integer', 'min:0', 'max:99'],
            'bilanganKes' => ['required', 'integer', 'min:0', 'max:99999'],
            'keteranganKes' => ['required', 'string', 'max:2000'],

            // _3 qualifications
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

            // _4 firma
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

            // _5 bank
            'namaBank' => ['required', 'string', 'max:255'],
            'noAkaunBank' => ['required', 'string', 'max:255'],
            'alamatBank1' => ['nullable', 'string', 'max:255'],
            'alamatBank2' => ['nullable', 'string', 'max:255'],
            'alamatBank3' => ['nullable', 'string', 'max:255'],
            'poskodBank' => ['required', 'string', 'max:10'],
            'bandarBank' => ['required', 'string', 'max:255'],
            'negeriBank' => ['required', 'string', 'max:255'],
        ];

        foreach (range(1, 5) as $i) {
            $rules["csoNumber{$i}"] = ['nullable', 'string', 'max:255'];
            $rules["cso{$i}Tauliah"] = ['nullable', 'string', 'max:255'];
            $rules["cso{$i}Mula"] = ['nullable', 'date'];
            $rules["cso{$i}Akhir"] = ['nullable', 'date'];
            $rules["lokasiBerguam{$i}"] = ['nullable', 'string', 'max:255'];
        }

        // All 18 documents optional on update (re-upload replaces). cso4/cso5 INCLUDED (legacy bug fix).
        foreach (array_keys(PeguamDaftarRequest::DOC_TYPES) as $field) {
            $rules[$field] = ['nullable', 'file', 'mimes:pdf', 'max:5120'];
        }

        return $rules;
    }
}
