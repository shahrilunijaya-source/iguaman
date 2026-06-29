<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** pegawai_jbg — JBG officer registry. */
class PegawaiJbg extends Model
{
    protected $table = 'pegawai_jbg';

    public $timestamps = false;

    protected $guarded = ['id'];
}
