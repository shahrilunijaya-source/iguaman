<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** Validation for the kes mahkamah (court) section of a case (forms). */
class MahkamahRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // route gated to staff roles
    }

    public function rules(): array
    {
        return [
            'nama_pihak' => ['nullable', 'string', 'max:50'],
            'nama_responden' => ['nullable', 'string', 'max:50'],
            'nama_mahkamah' => ['nullable', 'string', 'max:100'],
            'no_mahkamah' => ['nullable', 'string', 'max:30'],
            'nama_pegawai_penyiasat' => ['nullable', 'string', 'max:100'],
            'tarikh_pemfailan_kes' => ['nullable', 'date'],
            'tarikh_pemfailan' => ['nullable', 'date'],
            'keputusan_kendali_kes' => ['nullable', 'string', 'max:15'],
            'tarikh_perintah' => ['nullable', 'date'],
            'tarikh_perintah_bersih' => ['nullable', 'date'],
            'tarikh_serahan_perintah' => ['nullable', 'date'],
            'kos' => ['nullable', 'string', 'max:10'],
            'kos_oyd' => ['nullable', 'integer', 'min:0'],
            'kos_pihak_lawan' => ['nullable', 'integer', 'min:0'],
        ];
    }
}
