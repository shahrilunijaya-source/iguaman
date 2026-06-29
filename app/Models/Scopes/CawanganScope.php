<?php

namespace App\Models\Scopes;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * Branch isolation (legacy: WHERE cawangan = session).
 * Front-line staff (pegawai/pengarah/ppuu/pembantu_tadbir) with a branch only see
 * their own branch's cases. HQ roles (admin/koordinator/ketua_pengarah), lawyers,
 * and unauthenticated/no-branch contexts see everything.
 */
class CawanganScope implements Scope
{
    private const SCOPED_ROLES = [
        User::ROLE_PEGAWAI,
        User::ROLE_PENGARAH,
        User::ROLE_PPUU,
        User::ROLE_PEMBANTU_TADBIR,
    ];

    public function apply(Builder $builder, Model $model): void
    {
        $user = auth()->user();

        if ($user && $user->isStaff() && filled($user->cawangan) && in_array($user->role, self::SCOPED_ROLES, true)) {
            $builder->where($model->getTable().'.cawangan', $user->cawangan);
        }
    }
}
