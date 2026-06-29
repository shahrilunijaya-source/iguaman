<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** butiran_peguam_panel_2 — lawyer panel application v2 (director endorsement + KP decision workflow). */
class ButiranPeguamPanel2 extends Model
{
    protected $table = 'butiran_peguam_panel_2';

    public $timestamps = false;

    protected $guarded = ['id'];

    protected $casts = [
        'tarikhDiterimaMasuk' => 'date',
        'tarikhDiterimaMasukSyarie' => 'date',
        'tarikh_sokonganPengarah' => 'datetime',
        'tarikh_keputusanKP' => 'datetime',
        'tarikhMohon' => 'datetime',
        'tarikhBatal' => 'date',
        'tarikhTidakDiluluskan' => 'date',
    ];
}
