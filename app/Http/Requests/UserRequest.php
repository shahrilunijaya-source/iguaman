<?php

namespace App\Http\Requests;

use App\Http\Controllers\UserController;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Validation for Pengurusan Pengguna (user/account) create + edit. */
class UserRequest extends FormRequest
{
    public function authorize(): bool
    {
        $actor = $this->user();
        if (! $actor) {
            return false;
        }

        // A non-admin must not touch an existing admin account at all (password,
        // role, lock). The /pengguna route is held by pengarah/koordinator/
        // ketua_pengarah too — not admin-only — so guard the target here.
        $target = $this->route('user');
        if ($target instanceof User && $target->hasRole(User::ROLE_ADMIN) && ! $actor->hasRole(User::ROLE_ADMIN)) {
            return false;
        }

        return true;
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
            'role' => [
                'required',
                Rule::in(array_keys(UserController::ROLES)),
                // Privilege-escalation guard: only an admin may mint/keep an admin.
                function (string $attr, mixed $value, callable $fail): void {
                    if ($value === User::ROLE_ADMIN && ! $this->user()?->hasRole(User::ROLE_ADMIN)) {
                        $fail('Hanya Admin boleh menetapkan peranan Admin.');
                    }
                },
            ],
            'user_type' => [
                'required',
                Rule::in([User::TYPE_STAFF, User::TYPE_LAWYER]),
                // Role <-> user_type must agree: peguam is the only lawyer-type role.
                function (string $attr, mixed $value, callable $fail): void {
                    $role = $this->input('role');
                    if ($role === User::ROLE_PEGUAM && $value !== User::TYPE_LAWYER) {
                        $fail('Peranan Peguam mesti berjenis pengguna Peguam.');
                    }
                    if ($role !== User::ROLE_PEGUAM && $value === User::TYPE_LAWYER) {
                        $fail('Hanya peranan Peguam boleh berjenis pengguna Peguam.');
                    }
                },
            ],
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
