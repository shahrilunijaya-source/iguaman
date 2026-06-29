<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** butiran_peguam_panel — lawyer profile v1 (qualification, firm, bank, insurance, CLP/CSO). */
class ButiranPeguamPanel extends Model
{
    protected $table = 'butiran_peguam_panel';

    public $timestamps = false;

    protected $guarded = ['id'];

    protected $casts = [
        'tarikhDiterimaMasuk' => 'date',
        'clpMula' => 'date',
        'clpAkhir' => 'date',
        'polisiMula' => 'date',
        'polisiAkhir' => 'date',
    ];
}
