<?php

namespace App\Http\Requests\Awam;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AwamPermohonanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAwam() === true;
    }

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
            'nama_mangsa' => ['required', 'string', 'max:255'],
            'id_pengenalan_mangsa' => [$req, 'string', 'max:255'],
            'jantina_mangsa' => ['nullable', 'in:Lelaki,Perempuan'],
            'umur_mangsa' => ['nullable', 'string', 'max:255'],
            'bangsa' => ['nullable', 'string', 'max:255'],
            'agama' => ['nullable', 'string', 'max:255'],
            'tarikh_lahir_mangsa' => ['nullable', 'date'],
            'alamat_surat1' => ['nullable', 'string', 'max:255'],
            'alamat_surat2' => ['nullable', 'string', 'max:255'],
            'alamat_surat3' => ['nullable', 'string', 'max:255'],
            'poskod' => ['nullable', 'string', 'max:10'],
            'cawangan_id' => ['required', 'integer', 'exists:cawangan,id'],
            'id_kategori' => [$req, 'integer', 'exists:ref_kategori_kn,id'],
            'id_subkategori' => ['nullable', 'integer', 'exists:ref_subkategori_kn,id'],
            'id_negeri' => ['nullable', 'integer'],
            'jenis_kes' => ['nullable', 'string', 'max:255'],
            'ulasan_permohonan' => ['nullable', 'string', 'max:2000'],
            'jumlah_pendapatan' => ['nullable', 'numeric', 'min:0'],
            'tarikh_temu_janji' => [$hantar ? 'required' : 'nullable', 'date'],
            'masa_temu_janji' => [$hantar ? 'required' : 'nullable', 'date_format:H:i'],
            'perakuan' => [$hantar ? 'accepted' : 'nullable'],
        ];
    }
}
