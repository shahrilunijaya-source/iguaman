<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** peguam_panel — panel lawyer master record (surrogate id PK added in migration). */
class PeguamPanel extends Model
{
    protected $table = 'peguam_panel';

    public $timestamps = false;

    protected $guarded = ['id'];

    protected $casts = [
        'tarikh_penugasan_peguam_panel' => 'date',
    ];
}
