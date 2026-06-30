<?php

namespace App\Models\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * Branch isolation (legacy: WHERE cawangan = session).
 * Staff with a branch see only their branch UNLESS they hold `cawangan.view-all`.
 * Lawyers / no-branch / view-all -> see everything. Permission resolved ONCE per request.
 *
 * W7 (D2 dual-branch): a transferred case carries its originating branch in
 * `cawangan_asal`, so the origin keeps it in normal worklists while the moved
 * `cawangan` makes it visible at the destination. cawangan_asal is NULL for
 * every non-transferred row, so the OR is a no-op for untouched data.
 */
class CawanganScope implements Scope
{
    /** Per-request memo of can('cawangan.view-all') keyed by user id. */
    private static array $viewAllMemo = [];

    public function apply(Builder $builder, Model $model): void
    {
        $user = auth()->user();

        if ($user && $user->isStaff() && filled($user->cawangan) && ! $this->canViewAll($user)) {
            $table = $model->getTable();
            $builder->where(function ($w) use ($table, $user) {
                $w->where($table.'.cawangan', $user->cawangan)
                    ->orWhere($table.'.cawangan_asal', $user->cawangan);
            });
        }
    }

    private function canViewAll($user): bool
    {
        return self::$viewAllMemo[$user->id] ??= $user->can('cawangan.view-all');
    }
}
