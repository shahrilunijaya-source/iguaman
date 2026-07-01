<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Bilik (rooms) under a JBG cawangan - consumed by appointment slot generation (batch 10). */
class Bilik extends Model
{
    protected $table = 'bilik';

    protected $guarded = ['id'];

    protected $casts = [
        'status_aktif' => 'boolean',
    ];

    public function cawangan(): BelongsTo
    {
        return $this->belongsTo(Cawangan::class);
    }
}
