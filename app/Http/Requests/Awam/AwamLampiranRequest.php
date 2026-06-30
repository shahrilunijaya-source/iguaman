<?php

namespace App\Http\Requests\Awam;

use Illuminate\Foundation\Http\FormRequest;

class AwamLampiranRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAwam() === true;
    }

    public function rules(): array
    {
        return [
            'fail' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'], // 5 MB
        ];
    }
}
