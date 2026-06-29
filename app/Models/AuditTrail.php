<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** audit_trail — field-level change log (INSERT/UPDATE/DELETE/APPROVE/REJECT). */
class AuditTrail extends Model
{
    protected $table = 'audit_trail';

    public $timestamps = false;

    protected $guarded = ['id'];

    protected $casts = [
        'modified_date' => 'datetime',
    ];
}
