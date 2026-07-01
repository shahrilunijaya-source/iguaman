<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** ref_negeri - state reference. */
class RefNegeri extends Model
{
    protected $table = 'ref_negeri';

    public $timestamps = false;

    protected $guarded = ['id'];
}
