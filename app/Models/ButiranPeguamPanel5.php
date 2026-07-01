<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** butiran_peguam_panel_5 - lawyer payment bank account. */
class ButiranPeguamPanel5 extends Model
{
    protected $table = 'butiran_peguam_panel_5';

    public $timestamps = false;

    protected $guarded = ['id'];

    public function peguam(): BelongsTo
    {
        return $this->belongsTo(ButiranPeguamPanel2::class, 'kpBaru', 'kpBaru');
    }
}
