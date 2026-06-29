<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Validation for OYD (Orang Yang Dibantu / beneficiary) create + edit. */
class OydRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // route gated to staff roles
    }

    public function rules(): array
    {
        $oyd = $this->route('oyd'); // null on create, Oyd model on update
        $ignoreId = $oyd?->id;

        return [
            'nama_oyd' => ['required', 'string', 'max:100'],
            'kp_oyd' => ['required', 'string', 'max:12', Rule::unique('butiran_oyd', 'kp_oyd')->ignore($ignoreId)],
            'alamat_oyd1' => ['nullable', 'string', 'max:255'],
            'alamat_oyd2' => ['nullable', 'string', 'max:255'],
            'alamat_oyd3' => ['nullable', 'string', 'max:100'],
            'poskod_oyd' => ['nullable', 'string', 'max:10'],
            'bandar_oyd' => ['nullable', 'string', 'max:100'],
            'negeri_oyd' => ['nullable', 'string', 'max:100'],
            'notelefon_oyd' => ['nullable', 'string', 'max:20'],
            'email_oyd' => ['nullable', 'email', 'max:50'],
            'umur_oyd' => ['nullable', 'integer', 'min:0', 'max:150'],
            'jantina_oyd' => ['nullable', 'in:Lelaki,Perempuan'],
            'agama_oyd' => ['nullable', 'string', 'max:20'],
            'agamaLain_oyd' => ['nullable', 'string', 'max:20'],
            'oku_oyd' => ['nullable', 'string', 'max:5'],
            'bangsa_oyd' => ['nullable', 'string', 'max:50'],
            'etnik_oyd' => ['nullable', 'string', 'max:50'],
        ];
    }

    public function attributes(): array
    {
        return [
            'nama_oyd' => 'nama OYD',
            'kp_oyd' => 'no. KP',
        ];
    }

    public function messages(): array
    {
        return ['kp_oyd.unique' => 'No. KP ini telah wujud dalam rekod OYD.'];
    }
}
