<?php

namespace App\Http\Requests\Awam;

use Illuminate\Foundation\Http\FormRequest;

class AwamDaftarRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'nokp' => ['required', 'string', 'max:20', 'regex:/^[0-9-]+$/', 'unique:users,nokp'],
            'email' => ['nullable', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'captcha' => ['required', 'integer'],
            'website' => ['nullable', 'prohibited'], // honeypot: must stay empty
        ];
    }

    public function messages(): array
    {
        return [
            'website.prohibited' => 'Permohonan tidak sah.',
            'nokp.unique' => 'No. Kad Pengenalan telah didaftarkan.',
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            if ((int) $this->input('captcha') !== (int) $this->session()->get('captcha_sum')) {
                $validator->errors()->add('captcha', 'Jawapan pengesahan salah. Cuba lagi.');
            }
        });
    }
}
