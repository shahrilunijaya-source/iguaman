<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validation for permohonan (case intake/edit). Covers the core lifecycle sections:
 * Pemohon, Permohonan & Pendaftaran, Keputusan, Penutupan.
 * Pengantaraan/Mahkamah fields are edited via specialized Phase 3c actions.
 */
class PermohonanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // route already gated to staff roles
    }

    public function rules(): array
    {
        return [
            // Pemohon
            'nama' => ['required', 'string', 'max:100'],
            'nokp' => ['nullable', 'string', 'max:12'],
            'umur' => ['nullable', 'integer', 'min:0', 'max:150'],
            'jantina' => ['nullable', 'string', 'max:10'],
            'agama' => ['nullable', 'string', 'max:30'],
            'bangsa' => ['nullable', 'string', 'max:50'],
            'etnik' => ['nullable', 'string', 'max:50'],
            'oku' => ['nullable', 'string', 'max:5'],
            'nama_penjaga' => ['nullable', 'string', 'max:255'],
            'nokp_penjaga' => ['nullable', 'string', 'max:20'],

            // Permohonan & Pendaftaran
            'cawangan' => ['required', 'string', 'max:50'],
            'tarikh_khidmat_nasihat' => ['nullable', 'date'],
            'tarikh_permohonan' => ['nullable', 'date'],
            'tarikh_daftar' => ['nullable', 'date'],
            'kategori_kes' => ['nullable', 'string', 'max:20'],
            'jenis_kategori' => ['nullable', 'string', 'max:30'],
            'jenis_kes' => ['nullable', 'string', 'max:5'],
            'jenis_jenayah' => ['nullable', 'string', 'max:50'],
            'taraf' => ['nullable', 'string', 'max:50'],
            'no_fail' => ['nullable', 'string', 'max:50'],
            'no_sistem' => ['nullable', 'string', 'max:50'],
            'nama_pegawai' => ['nullable', 'string', 'max:50'],

            // Keputusan
            'keputusan' => ['nullable', 'string', 'max:20'],
            'diterima' => ['nullable', 'string', 'max:10'],
            'kelulusan' => ['nullable', 'string', 'max:20'],
            'keputusan_menteri' => ['nullable', 'string', 'max:10'],
            'tarikh_perakuan' => ['nullable', 'date'],
            'tarikh_pemakluman' => ['nullable', 'date'],
            'sumbangan' => ['nullable', 'string', 'max:20'],
            'nilai_sumbangan' => ['nullable', 'integer', 'min:0'],

            // Penutupan
            'status' => ['nullable', 'string', 'max:50'],
            'tarikh_selesai' => ['nullable', 'date'],
            'sebab_selesai' => ['nullable', 'string', 'max:50'],
            'tarikh_tutup_fail' => ['nullable', 'date'],
            'sebab_tutup_fail' => ['nullable', 'string'],
        ];
    }

    public function attributes(): array
    {
        return [
            'nama' => 'nama pemohon',
            'nokp' => 'no. KP',
            'cawangan' => 'cawangan',
        ];
    }
}
