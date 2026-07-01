<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** butiran_peguam_panel_4 - law firm details + professional-indemnity insurance. */
class ButiranPeguamPanel4 extends Model
{
    protected $table = 'butiran_peguam_panel_4';

    public $timestamps = false;

    protected $guarded = ['id'];

    protected $casts = [
        'polisiMula' => 'date',
        'polisiAkhir' => 'date',
    ];

    public function peguam(): BelongsTo
    {
        return $this->belongsTo(ButiranPeguamPanel2::class, 'kpBaru', 'kpBaru');
    }
}
