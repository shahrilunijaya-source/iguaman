<?php

namespace App\Http\Requests\Awam;

use Illuminate\Foundation\Http\FormRequest;

class AwamRescheduleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAwam() === true;
    }

    public function rules(): array
    {
        return [
            'tarikh_temu_janji' => ['required', 'date', 'after:today'],
            'masa_temu_janji' => ['required', 'date_format:H:i'],
        ];
    }
}
