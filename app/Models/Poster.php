<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** posters - announcements / notices. */
class Poster extends Model
{
    protected $table = 'posters';

    public $timestamps = false;

    protected $guarded = ['id'];

    protected $casts = [
        'created_at' => 'datetime',
        'modified_at' => 'datetime',
    ];
}
