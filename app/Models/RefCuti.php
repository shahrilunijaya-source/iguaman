<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** ref_cuti - holiday / leave reference (legacy table is latin1). */
class RefCuti extends Model
{
    protected $table = 'ref_cuti';

    protected $primaryKey = 'id_cuti';

    public $timestamps = false;

    protected $guarded = ['id_cuti'];

    protected $casts = [
        'tarikh_mula' => 'date',
        'tarikh_tamat' => 'date',
        'created' => 'date',
    ];
}
