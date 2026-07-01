<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** butiran_oyd - Orang Yang Dibantu (applicant / beneficiary) details. */
class Oyd extends Model
{
    protected $table = 'butiran_oyd';

    public $timestamps = false;

    protected $guarded = ['id'];

    protected $casts = [
        'umur_oyd' => 'integer',
        'createdDate_oyd' => 'datetime',
        'modifiedDate_oyd' => 'datetime',
    ];
}
