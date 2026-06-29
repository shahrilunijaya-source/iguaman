<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** mahkamah_sivil — civil court reference data. */
class MahkamahSivil extends Model
{
    protected $table = 'mahkamah_sivil';

    public $timestamps = false;

    protected $guarded = ['id'];
}
