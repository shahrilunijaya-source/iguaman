<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** items — generic list (likely legacy/deprecated; kept for ETL parity). */
class Item extends Model
{
    protected $table = 'items';

    public $timestamps = false;

    protected $guarded = ['id'];
}
