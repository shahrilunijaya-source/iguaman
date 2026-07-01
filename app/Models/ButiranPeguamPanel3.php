<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** butiran_peguam_panel_3 - lawyer professional qualifications (CLP / CSO / YBGK / ADR / sijil / eVendor). */
class ButiranPeguamPanel3 extends Model
{
    protected $table = 'butiran_peguam_panel_3';

    public $timestamps = false;

    protected $guarded = ['id'];

    protected $casts = [
        'clpMula' => 'date',
        'clpAkhir' => 'date',
        'cso1Mula' => 'date', 'cso1Akhir' => 'date',
        'cso2Mula' => 'date', 'cso2Akhir' => 'date',
        'cso3Mula' => 'date', 'cso3Akhir' => 'date',
        'cso4Mula' => 'date', 'cso4Akhir' => 'date',
        'cso5Mula' => 'date', 'cso5Akhir' => 'date',
        'ybgk_tarikhLulus_A' => 'date',
        'ybgk_tarikhLulus_B' => 'date',
        'sijilAhli_mula' => 'date', 'sijilAhli_akhir' => 'date',
        'sijilAkreditasi_mula' => 'date', 'sijilAkreditasi_akhir' => 'date',
    ];

    public function peguam(): BelongsTo
    {
        return $this->belongsTo(ButiranPeguamPanel2::class, 'kpBaru', 'kpBaru');
    }
}
