<?php

namespace App\Support;

use App\Models\AuditTrail;

/**
 * Thin writer for the legacy audit_trail table. Record-level entries
 * (field_name/old/new left null) with a human-readable remark.
 * action_type must match the legacy enum.
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
            'modified_date' => now(),
        ]);
    }
}
