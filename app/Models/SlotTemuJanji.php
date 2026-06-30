<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A generated appointment slot for a branch (+optional room) on a date.
 * is_temujanji = booked flag (false = open); status_aktif = slot enabled.
 * Consumed by SlotAvailabilityService (batch 10).
 */
class SlotTemuJanji extends Model
{
    protected $table = 'slot_temu_janji';

    protected $guarded = ['id'];

    protected $casts = [
        'tarikh_slot' => 'date',
        'masa_mula' => 'string',
        'masa_akhir' => 'string',
        'is_temujanji' => 'boolean',
        'status_aktif' => 'boolean',
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
