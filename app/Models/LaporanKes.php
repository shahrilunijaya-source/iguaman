<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** laporan_kes — court case report, child of forms via id_kes. */
class LaporanKes extends Model
{
    protected $table = 'laporan_kes';

    public $timestamps = false;

    protected $guarded = ['id'];

    protected $casts = [
        'tarikh_sebutan' => 'date',
        'id_kes' => 'integer',
    ];

    public function form(): BelongsTo
    {
        return $this->belongsTo(Form::class, 'id_kes', 'id');
    }
}
