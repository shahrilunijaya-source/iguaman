<?php

namespace App\Models\Scopes;

use App\Models\Cawangan;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * Branch isolation (legacy: WHERE cawangan = session).
 * Staff with a branch see only their branch UNLESS they hold `cawangan.view-all`.
 * Lawyers / no-branch / view-all -> see everything. Permission resolved ONCE per request.
 *
 * W7 (D2 dual-branch): a transferred record carries its originating branch in the
 * `asalCol`, so the origin keeps it in normal worklists while the moved current-branch
 * column makes it visible at the destination. The asal column is NULL for every
 * non-transferred row, so the OR is a no-op for untouched data.
 *
 * W21: parameterised so it applies beyond Form. `Form` keys on a branch NAME string
 * (`cawangan` / `cawangan_asal`); `KhidmatNasihat` keys on a numeric `cawangan_id` /
 * `cawangan_asal_id`, so it constructs the scope with `byBranchId: true`.
 */
class CawanganScope implements Scope
{
    /** Per-request memo of can('cawangan.view-all') keyed by user id. */
    private static array $viewAllMemo = [];

    /**
     * @param  string  $cawanganCol  current-branch column ('cawangan' name, or 'cawangan_id')
     * @param  string  $asalCol      origin-of-transfer column (D2 dual-branch)
     * @param  bool    $byBranchId   match by numeric cawangan_id (resolve from the user's branch name)
     */
    public function __construct(
        private readonly string $cawanganCol = 'cawangan',
        private readonly string $asalCol = 'cawangan_asal',
        private readonly bool $byBranchId = false,
    ) {}

    public function apply(Builder $builder, Model $model): void
    {
        $user = auth()->user();

        if (! ($user && $user->isStaff() && filled($user->cawangan) && ! $this->canViewAll($user))) {
            return;
        }

        $needle = $this->byBranchId
            ? Cawangan::where('nama', $user->cawangan)->value('id')
            : $user->cawangan;

        // Branch can't be resolved → don't over-filter (fail open, like a no-branch user).
        if ($needle === null) {
            return;
        }

        $table = $model->getTable();
        $builder->where(function ($w) use ($table, $needle) {
            $w->where($table.'.'.$this->cawanganCol, $needle)
                ->orWhere($table.'.'.$this->asalCol, $needle);
        });
    }

    private function canViewAll($user): bool
    {
        return self::$viewAllMemo[$user->id] ??= $user->can('cawangan.view-all');
    }
}
