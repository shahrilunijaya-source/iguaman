<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Janji Temu (appointment). Links to a slot + (later) khidmat_nasihat (Batch 9,
 * wired at integration - no FK). status mirrors the FE appointment lifecycle.
 */
class TemuJanji extends Model
{
    protected $table = 'temu_janji';

    protected $guarded = ['id'];

    protected $casts = [
        'tarikh_temu_janji' => 'date',
        'masa_mula' => 'string',
        'masa_akhir' => 'string',
    ];

    public const STATUS = ['MENUNGGU', 'DISAHKAN', 'HADIR', 'TIDAK_HADIR', 'SELESAI', 'BATAL'];

    public function slot(): BelongsTo
    {
        return $this->belongsTo(SlotTemuJanji::class, 'slot_temu_janji_id');
    }

    public function cawangan(): BelongsTo
    {
        return $this->belongsTo(Cawangan::class);
    }
}
