<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validation for the Khidmat Nasihat create wizard + DRAF edit (batch 9 slice 2).
 *
 * Two submit modes via the hidden `aksi` field:
 *   - "draf"   → save incomplete; only the always-required basics are enforced.
 *   - "hantar" → final submit; appointment slot + perakuan declaration become
 *                required (the row goes BAHARU, not DRAF).
 */
class KhidmatNasihatRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // route gated to permission:khidmat.manage
    }

    /** Whether this is a final submit (vs a draft save). */
    public function isHantar(): bool
    {
        return $this->input('aksi') === 'hantar';
    }

    public function rules(): array
    {
        $hantar = $this->isHantar();
        $req = $hantar ? 'required' : 'nullable';

        return [
            'aksi' => ['nullable', Rule::in(['draf', 'hantar'])],

            // ---- Maklumat: applicant / victim ----
            'nama_mangsa' => ['required', 'string', 'max:255'],
            'id_pengenalan_mangsa' => [$req, 'string', 'max:255'],
            'jenis_pengenalan_mangsa' => ['nullable', 'string', 'max:255'],
            'jantina_mangsa' => ['nullable', 'in:Lelaki,Perempuan'],
            'umur_mangsa' => ['nullable', 'string', 'max:255'],
            'bangsa' => ['nullable', 'string', 'max:255'],
            'agama' => ['nullable', 'string', 'max:255'],
            'tarikh_lahir_mangsa' => ['nullable', 'date'],
            'nama_wakil' => ['nullable', 'string', 'max:255'],
            'alamat_surat1' => ['nullable', 'string', 'max:255'],
            'alamat_surat2' => ['nullable', 'string', 'max:255'],
            'alamat_surat3' => ['nullable', 'string', 'max:255'],
            'poskod' => ['nullable', 'string', 'max:10'],

            // ---- Maklumat: branch + category tree + state ----
            'cawangan_id' => ['required', 'integer', 'exists:cawangan,id'],
            'id_kategori' => [$req, 'integer', 'exists:ref_kategori_kn,id'],
            'id_kategori_kes' => ['nullable', 'integer', 'exists:ref_kategori_kes_kn,id'],
            'id_subkategori' => ['nullable', 'integer', 'exists:ref_subkategori_kn,id'],
            'id_negeri' => ['nullable', 'integer'],
            'jenis_kes' => ['nullable', 'string', 'max:255'],
            'ulasan_permohonan' => ['nullable', 'string', 'max:2000'],

            // ---- Bayaran ----
            'jumlah_pendapatan' => ['nullable', 'numeric', 'min:0'],
            'is_percuma' => ['nullable', 'boolean'],

            // ---- Slot janji temu (required only on final submit) ----
            'tarikh_temu_janji' => [$hantar ? 'required' : 'nullable', 'date'],
            'masa_temu_janji' => [$hantar ? 'required' : 'nullable', 'date_format:H:i'],

            // ---- Perakuan (declaration must be accepted to submit) ----
            'perakuan' => [$hantar ? 'accepted' : 'nullable', 'boolean'],
        ];
    }

    public function attributes(): array
    {
        return [
            'nama_mangsa' => 'nama mangsa',
            'id_pengenalan_mangsa' => 'no. pengenalan',
            'cawangan_id' => 'cawangan',
            'id_kategori' => 'kategori',
            'id_kategori_kes' => 'kategori kes',
            'id_subkategori' => 'subkategori',
            'jumlah_pendapatan' => 'jumlah pendapatan',
            'tarikh_temu_janji' => 'tarikh temu janji',
            'masa_temu_janji' => 'masa temu janji',
            'perakuan' => 'perakuan',
        ];
    }

    public function messages(): array
    {
        return [
            'perakuan.accepted' => 'Sila tandakan perakuan sebelum menghantar permohonan.',
            'tarikh_temu_janji.required' => 'Sila pilih tarikh temu janji.',
            'masa_temu_janji.required' => 'Sila pilih masa temu janji.',
        ];
    }
}
