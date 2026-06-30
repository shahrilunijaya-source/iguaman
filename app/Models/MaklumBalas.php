<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Maklum Balas — post-appointment satisfaction feedback (batch 12 slice 1).
 * One row per Khidmat Nasihat (DB-unique on khidmat_nasihat_id). Captured via a
 * public, throttled link once the advisory appointment is SELESAI. No login.
 */
class MaklumBalas extends Model
{
    protected $table = 'maklum_balas';

    protected $guarded = ['id'];

    protected $casts = [
        'soalan_1a' => 'boolean',
        'soalan_1b' => 'boolean',
        'soalan_1c' => 'boolean',
        'soalan_1d' => 'boolean',
        'soalan_1e' => 'boolean',
    ];

    public const SOALAN_2A = ['CEMERLANG', 'BAIK', 'KURANG_MEMUASKAN'];

    public function khidmatNasihat(): BelongsTo
    {
        return $this->belongsTo(KhidmatNasihat::class, 'khidmat_nasihat_id');
    }
}
