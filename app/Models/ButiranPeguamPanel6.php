<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * butiran_peguam_panel_6 — practice-area specialisation rows (bidang pengkhususan).
 * One lawyer has many. checkbox_value_status drives the add/drop approval machine
 * (0=new add, 1=approved, 3=drop requested, 4=add requested, etc — see pp-kes-oyd).
 */
class ButiranPeguamPanel6 extends Model
{
    protected $table = 'butiran_peguam_panel_6';

    public $timestamps = false;

    protected $guarded = ['id'];

    protected $casts = [
        'checkbox_value_status' => 'integer',
    ];

    public function peguam(): BelongsTo
    {
        return $this->belongsTo(ButiranPeguamPanel2::class, 'kpBaru', 'kpBaru');
    }
}
