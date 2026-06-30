<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * W3 — validate a Khidmat Nasihat branch-transfer. Authorization is at the route
 * (permission:khidmat.manage); the service enforces origin-branch + status guards.
 */
class PindahKnRequest extends FormRequest
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
