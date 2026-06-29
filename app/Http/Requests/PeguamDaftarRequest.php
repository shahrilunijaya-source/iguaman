<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Public lawyer panel application (daftar peguam panel).
 * Feeds butiran_peguam_panel_2 with permohonan_status='0' (Baharu) → staff endorse/decide workflow.
 * No auth: prospective panel lawyers have no login yet. Honeypot + throttle guard abuse.
 */
class PeguamDaftarRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // public route
    }

    public function rules(): array
    {
        return [
            'namaPeguam' => ['required', 'string', 'max:255'],
            'kpBaru' => ['required', 'string', 'max:20', 'unique:butiran_peguam_panel_2,kpBaru'],
            'kpLama' => ['nullable', 'string', 'max:20'],
            'jantina' => ['required', 'in:Lelaki,Perempuan'],
            'noTelBimbit' => ['required', 'string', 'max:20'],
            'emelPeguam' => ['required', 'email', 'max:255'],
            'kelulusanAkademik' => ['required', 'string', 'max:500'],
            'tarikhDiterimaMasuk' => ['nullable', 'date'],
            'tarikhDiterimaMasukSyarie' => ['nullable', 'date'],
            'tahunPengalaman' => ['required', 'integer', 'min:0', 'max:99'],
            'tahunPengalamanSyarie' => ['nullable', 'integer', 'min:0', 'max:99'],
            'bilanganKes' => ['required', 'integer', 'min:0', 'max:99999'],
            'keteranganKes' => ['required', 'string', 'max:2000'],

            // Honeypot — bots fill it, humans never see it. Must be empty/absent.
            'website' => ['prohibited'],
        ];
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
        ];
    }

    public function messages(): array
    {
        return [
            'kpBaru.unique' => 'No. KP ini telah pun menghantar permohonan.',
            'website.prohibited' => 'Permohonan tidak sah.',
        ];
    }
}
