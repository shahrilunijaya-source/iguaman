<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Eligibility 3-modal screening gate (batch 9 slice 3 - FE khidmatnasihat/index).
 *
 * Modal 1: jenis (sivil_syariah | pendamping_jenayah) + income self-declaration
 *          (pendapatan_bawah_had, sivil/syariah only - "Tidak" routes to Sumbangan).
 * Modal 2: two eligibility questions (must both be "Ya" to stay eligible).
 * Modal 3: terms & conditions - must be accepted to submit.
 *
 * The disqualifying-answer branch is handled in the controller (a "Tidak" on an
 * eligibility question is a valid input but blocks proceeding); this request only
 * enforces presence + terms acceptance.
 */
class KhidmatSaringanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // route gated to permission:khidmat.manage
    }

    public function rules(): array
    {
        $isSivilSyariah = $this->input('saringan_jenis') === 'sivil_syariah';

        return [
            'saringan_jenis' => ['required', Rule::in(['sivil_syariah', 'pendamping_jenayah'])],
            // Income declaration only applies to the sivil/syariah path.
            'pendapatan_bawah_had' => [Rule::requiredIf($isSivilSyariah), 'nullable', Rule::in(['Ya', 'Tidak'])],
            'tiada_nasihat_terdahulu' => ['required', Rule::in(['Ya', 'Tidak'])],
            'tiada_perkara_dikecualikan' => ['required', Rule::in(['Ya', 'Tidak'])],
            'terma' => ['accepted'],
        ];
    }

    public function messages(): array
    {
        return [
            'terma.accepted' => 'Sila bersetuju dengan Terma dan Syarat sebelum meneruskan.',
            'saringan_jenis.required' => 'Sila pilih jenis khidmat.',
            'tiada_nasihat_terdahulu.required' => 'Sila jawab soalan kelayakan.',
            'tiada_perkara_dikecualikan.required' => 'Sila jawab soalan kelayakan.',
        ];
    }
}
