<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;

/** peguam_panel — panel lawyer master record (surrogate id PK added in migration). */
class PeguamPanel extends Model
{
    protected $table = 'peguam_panel';

    public $timestamps = false;

    protected $guarded = ['id'];

    protected $casts = [
        'tarikh_penugasan_peguam_panel' => 'date',
    ];

    /** Detailed profile v1 (qualification/firm/bank/insurance), linked by IC. */
    public function butiran(): HasOne
    {
        return $this->hasOne(ButiranPeguamPanel::class, 'kpBaru', 'kp_peguam');
    }
}
