<?php

namespace App\Models\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * Branch isolation (legacy: WHERE cawangan = session).
 * Staff with a branch see only their branch UNLESS they hold `cawangan.view-all`.
 * Lawyers / no-branch / view-all -> see everything. Permission resolved ONCE per request.
 */
class CawanganScope implements Scope
{
    /** Per-request memo of can('cawangan.view-all') keyed by user id. */
    private static array $viewAllMemo = [];

    public function apply(Builder $builder, Model $model): void
    {
        $user = auth()->user();

        if ($user && $user->isStaff() && filled($user->cawangan) && ! $this->canViewAll($user)) {
            $builder->where($model->getTable().'.cawangan', $user->cawangan);
        }
    }

    private function canViewAll($user): bool
    {
        return self::$viewAllMemo[$user->id] ??= $user->can('cawangan.view-all');
    }
}
