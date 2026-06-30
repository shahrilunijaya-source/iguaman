<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Per-branch (+optional room) operational closure (date range). Dates within an
 * active range are excluded by SlotAvailabilityService (batch 10).
 */
class PenutupanOperasi extends Model
{
    protected $table = 'penutupan_operasi';

    protected $guarded = ['id'];

    protected $casts = [
        'tarikh_mula' => 'date',
        'tarikh_tamat' => 'date',
    ];

    public function cawangan(): BelongsTo
    {
        return $this->belongsTo(Cawangan::class);
    }

    public function bilik(): BelongsTo
    {
        return $this->belongsTo(Bilik::class);
    }
}
