<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Validation for Pengurusan Pengguna (user/account) create + edit. */
class UserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // route gated to admin role
    }

    public function rules(): array
    {
        $user = $this->route('user'); // null on create, User model on update
        $ignoreId = $user?->id;
        $isCreate = $ignoreId === null;

        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($ignoreId)],
            'username' => ['nullable', 'string', 'max:255'],
            'role' => ['required', Rule::in(array_keys(\App\Http\Controllers\UserController::ROLES))],
            'user_type' => ['required', Rule::in([User::TYPE_STAFF, User::TYPE_LAWYER])],
            'cawangan' => ['nullable', 'string', 'max:50'],
            'nokp' => ['nullable', 'string', 'max:20'],
            'password' => [$isCreate ? 'required' : 'nullable', 'string', 'min:8'],
            'is_active' => ['nullable', 'in:0,1'],
        ];
    }

    public function attributes(): array
    {
        return [
            'name' => 'nama',
            'email' => 'emel',
            'user_type' => 'jenis pengguna',
        ];
    }

    public function messages(): array
    {
        return ['email.unique' => 'Emel ini telah didaftarkan oleh pengguna lain.'];
    }
}
