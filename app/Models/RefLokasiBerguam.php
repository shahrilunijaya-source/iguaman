<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** ref_lokasi_berguam — practice-location reference. */
class RefLokasiBerguam extends Model
{
    protected $table = 'ref_lokasi_berguam';

    public $timestamps = false;

    protected $guarded = ['id'];
}
