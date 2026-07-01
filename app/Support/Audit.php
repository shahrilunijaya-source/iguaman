<?php

namespace App\Support;

use App\Models\AuditTrail;

/**
 * Thin writer for the legacy audit_trail table.
 *
 *  - log()     — record-level entry (field_name/old/new null) with a human-readable remark.
 *  - changes() — LOG-05 field-level before/after diff (one row per changed field).
 *
 * Both stamp the acting user's id (LOG-06) alongside the display name. action_type must
 * match the legacy enum.
 */
class Audit
{
    public const INSERT = 'INSERT';

    public const UPDATE = 'UPDATE';

    public const DELETE = 'DELETE';

    public const APPROVE = 'APPROVE';

    public const REJECT = 'REJECT';

    public static function log(string $table, int $recordId, string $action, ?string $remarks = null, ?string $by = null): void
    {
        AuditTrail::create([
            'table_name' => $table,
            'record_id' => $recordId,
            'action_type' => $action,
            'field_name' => null,
            'old_value' => null,
            'new_value' => null,
            'remarks' => $remarks,
            'modified_by' => $by ?: (auth()->user()->name ?? 'sistem'),
            'actor_id' => auth()->id(),
            'modified_date' => now(),
        ]);
    }

    /**
     * LOG-05 — write a before/after diff for a sensitive update: one row per field whose value
     * actually changed. Falls back to a single record-level entry when nothing changed so the
     * action still leaves a breadcrumb.
     *
     * @param  array<string, mixed>  $before
     * @param  array<string, mixed>  $after
     */
    public static function changes(string $table, int $recordId, string $action, array $before, array $after, ?string $remarks = null, ?string $by = null): void
    {
        $by = $by ?: (auth()->user()->name ?? 'sistem');
        $actorId = auth()->id();
        $wrote = false;

        foreach ($after as $field => $newValue) {
            $oldValue = $before[$field] ?? null;
            if (self::stringify($oldValue) === self::stringify($newValue)) {
                continue;
            }

            AuditTrail::create([
                'table_name' => $table,
                'record_id' => $recordId,
                'action_type' => $action,
                'field_name' => $field,
                'old_value' => self::stringify($oldValue),
                'new_value' => self::stringify($newValue),
                'remarks' => $remarks,
                'modified_by' => $by,
                'actor_id' => $actorId,
                'modified_date' => now(),
            ]);
            $wrote = true;
        }

        if (! $wrote) {
            self::log($table, $recordId, $action, $remarks, $by);
        }
    }

    /** Normalise a value to its stored string form for comparison + persistence. */
    private static function stringify(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }
        if (is_bool($value)) {
            return $value ? '1' : '0';
        }
        if (is_scalar($value) || $value instanceof \Stringable) {
            return (string) $value;
        }

        return json_encode($value) ?: null;
    }
}
