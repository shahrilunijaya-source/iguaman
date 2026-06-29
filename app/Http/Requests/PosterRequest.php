<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** Validation for e-Poster (announcements / notices) create + edit. */
class PosterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // route gated to staff roles
    }

    public function rules(): array
    {
        return [
            'tajuk_poster' => ['required', 'string', 'max:255'],
            'details_poster' => ['nullable', 'string'],
            'status_poster' => ['nullable', 'in:Aktif,Tidak Aktif'],
            'imej' => ['nullable', 'image', 'max:5120'],
        ];
    }

    public function attributes(): array
    {
        return [
            'tajuk_poster' => 'tajuk poster',
            'imej' => 'imej poster',
        ];
    }
}
