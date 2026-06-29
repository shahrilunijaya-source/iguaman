<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** Validation for the pengantaraan (mediation) section of a case (forms). */
class PengantaraanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // route gated to staff roles
    }

    public function rules(): array
    {
        return [
            'status_pengantaraan' => ['nullable', 'string', 'max:10'],
            'pengantaraan_kategori_kes' => ['nullable', 'string', 'max:255'],
            'nama_pegawai' => ['nullable', 'string', 'max:50'], // pegawai pengantara (mediator)
            'tarikh_penugasan' => ['nullable', 'date'],
            'kaedah_sidang' => ['nullable', 'string', 'max:15'],
            'lokasi_pihak_pertama' => ['nullable', 'string', 'max:50'],
            'lokasi_pihak_kedua' => ['nullable', 'string', 'max:50'],
            'lokasi_pegawai_pengantara' => ['nullable', 'string', 'max:50'],
            'tarikh_persetujuan' => ['nullable', 'date'],
            'tarikh_persetujuan_pengantaraan' => ['nullable', 'date'],
            'tarikh_sidang' => ['nullable', 'date'],
            'status_sidang' => ['nullable', 'string', 'max:10'],
            'cara_selesai' => ['nullable', 'string', 'max:40'],
            'setuju_pengantara' => ['nullable', 'string', 'max:10'],
        ];
    }
}
