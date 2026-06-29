<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** sejarah_peguam_panel — lawyer assignment history for a case. */
class SejarahPeguamPanel extends Model
{
    protected $table = 'sejarah_peguam_panel';

    public $timestamps = false;

    protected $guarded = ['id'];

    protected $casts = [
        'tarikh_penugasan' => 'date',
        'modifiedDate' => 'datetime',
    ];

    public function form(): BelongsTo
    {
        return $this->belongsTo(Form::class, 'id_kes', 'id');
    }
}
