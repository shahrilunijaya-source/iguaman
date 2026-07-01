<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** ref_kes - case type / category reference. */
class RefKes extends Model
{
    protected $table = 'ref_kes';

    public $timestamps = false;

    protected $guarded = ['id'];

    protected $casts = [
        'tarikh_kuatkuasa' => 'date',
    ];
}
