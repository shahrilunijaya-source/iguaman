<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * W7 - validate a case branch-transfer. Authorization is at the route
 * (permission:kes.pindah); the service enforces the origin-branch guard.
 */
class PindahKesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'cawangan_tujuan_id' => ['required', 'integer', 'exists:cawangan,id'],
            'sebab' => ['required', 'string', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'cawangan_tujuan_id.required' => 'Sila pilih cawangan tujuan.',
            'cawangan_tujuan_id.exists' => 'Cawangan tujuan tidak sah.',
            'sebab.required' => 'Sila nyatakan sebab pemindahan.',
        ];
    }
}
