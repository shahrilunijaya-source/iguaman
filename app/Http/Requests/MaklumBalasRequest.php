<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\MaklumBalas;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Public satisfaction-feedback submission (batch 12 slice 1). No auth — the
 * route is a public, throttled link. Validates the JBG-awareness checkboxes
 * (at least one), the conditional free text, and the satisfaction enum.
 */
class MaklumBalasRequest extends FormRequest
{
    /** Soalan 1 checkbox fields (how the applicant heard of JBG). */
    public const SOALAN_1_FIELDS = ['soalan_1a', 'soalan_1b', 'soalan_1c', 'soalan_1d', 'soalan_1e'];

    public function authorize(): bool
    {
        return true; // public route
    }

    protected function prepareForValidation(): void
    {
        // Normalise unchecked checkboxes (absent in the POST body) to false so the
        // "at least one true" rule sees explicit booleans.
        $merge = [];
        foreach (self::SOALAN_1_FIELDS as $field) {
            $merge[$field] = $this->boolean($field);
        }
        $this->merge($merge);
    }

    public function rules(): array
    {
        return [
            'soalan_1a' => ['boolean'],
            'soalan_1b' => ['boolean'],
            'soalan_1c' => ['boolean'],
            'soalan_1d' => ['boolean'],
            'soalan_1e' => ['boolean'],
            'soalan_1_lain_lain' => ['nullable', 'string', 'max:255', 'required_if:soalan_1e,true,1'],
            'soalan_2a' => ['required', Rule::in(MaklumBalas::SOALAN_2A)],
            'soalan_cadangan' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /**
     * Soalan 1 requires at least one box ticked. `required_without_all` can't
     * express this for booleans (false satisfies `required`), so assert it here.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $anySelected = collect(self::SOALAN_1_FIELDS)
                ->contains(fn (string $field): bool => $this->boolean($field));

            if (! $anySelected) {
                $validator->errors()->add(
                    'soalan_1a',
                    'Sila pilih sekurang-kurangnya satu cara anda mengetahui tentang JBG.'
                );
            }
        });
    }

    public function messages(): array
    {
        return [
            'soalan_1_lain_lain.required_if' => 'Sila nyatakan jika anda memilih "Lain-lain".',
            'soalan_2a.required' => 'Sila pilih tahap kepuasan terhadap perkhidmatan.',
            'soalan_2a.in' => 'Pilihan kepuasan tidak sah.',
        ];
    }
}
