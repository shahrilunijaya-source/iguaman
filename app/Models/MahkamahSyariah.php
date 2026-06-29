<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** mahkamah_syariah — syariah court reference data. */
class MahkamahSyariah extends Model
{
    protected $table = 'mahkamah_syariah';

    public $timestamps = false;

    protected $guarded = ['id'];
}
