<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** ref_jawatan - staff job-title reference. */
class RefJawatan extends Model
{
    protected $table = 'ref_jawatan';

    protected $guarded = ['id'];

    protected $casts = [
        'aktif' => 'boolean',
    ];
}
