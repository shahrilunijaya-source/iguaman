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

    /** Whether this application is filed on behalf of someone (SEBAGAI_WAKIL). */
    public function isWakil(): bool
    {
        return $this->input('jenis_permohonan') === 'SEBAGAI_WAKIL';
    }

    /** Whether the wakil context is the court (mahkamah) variant. */
    public function isMahkamah(): bool
    {
        return $this->isWakil() && $this->input('jenis_wakil') === 'MAHKAMAH';
    }

    public function rules(): array
    {
        $hantar = $this->isHantar();
        $req = $hantar ? 'required' : 'nullable';
        $wakil = $this->isWakil();
        $mahkamah = $this->isMahkamah();

        return [
            'aksi' => ['nullable', Rule::in(['draf', 'hantar'])],
            'jenis_permohonan' => ['nullable', Rule::in(['DIRI_SENDIRI', 'SEBAGAI_WAKIL'])],

            // ---- Sebagai-Wakil context (slice 3) ----
            // jenis_wakil required for every SEBAGAI_WAKIL submit; null for DIRI_SENDIRI.
            'jenis_wakil' => [Rule::requiredIf($wakil), 'nullable', Rule::in(['PENJARA', 'JKM', 'MAHKAMAH'])],
            'no_pengenalan_wakil' => ['nullable', 'string', 'max:255'],
            'jawatan_wakil' => ['nullable', 'string', 'max:255'],
            'nama_diwakili' => ['nullable', 'string', 'max:255'],
            'id_pengenalan_diwakili' => ['nullable', 'string', 'max:255'],

            // MAHKAMAH context: court party-type + court id (from mahkamah_sivil|syariah).
            'jenis_mahkamah_pihak' => [Rule::requiredIf($mahkamah), 'nullable', Rule::in(['SIVIL', 'SYARIAH'])],
            'id_mahkamah' => [Rule::requiredIf($mahkamah), 'nullable', 'integer'],

            // ---- Eligibility screening outcome (carried from the saringan gate) ----
            'saringan_jenis' => ['nullable', 'string', 'max:255'],
            'saringan_lulus' => ['nullable', 'boolean'],
            'is_laluan_sumbangan' => ['nullable', 'boolean'],

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
            // W1 - optional fee-waiver proof (only stored when is_percuma is set).
            'lampiran_waiver' => ['nullable', 'file', 'max:25600', 'mimes:pdf,jpg,jpeg,png,doc,docx'],

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
            'jenis_wakil' => 'jenis wakil',
            'jenis_mahkamah_pihak' => 'jenis mahkamah',
            'id_mahkamah' => 'mahkamah',
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
            'jenis_wakil.required' => 'Sila pilih konteks wakil (Penjara / JKM / Mahkamah).',
            'jenis_mahkamah_pihak.required' => 'Sila pilih jenis mahkamah (Sivil / Syariah).',
            'id_mahkamah.required' => 'Sila pilih mahkamah.',
        ];
    }
}
