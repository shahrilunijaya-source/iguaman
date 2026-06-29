<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** sejarah_pegawai — officer reassignment history for a case. */
class SejarahPegawai extends Model
{
    protected $table = 'sejarah_pegawai';

    public $timestamps = false;

    protected $guarded = ['id'];

    protected $casts = [
        'tarikh_kemaskini' => 'datetime',
    ];

    public function form(): BelongsTo
    {
        return $this->belongsTo(Form::class, 'id_kes', 'id');
    }
}
