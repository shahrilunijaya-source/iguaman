<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** uploaded_files — document attachments. */
class UploadedFile extends Model
{
    protected $table = 'uploaded_files';

    public $timestamps = false;

    protected $guarded = ['id'];

    protected $casts = [
        'uploaded_at' => 'datetime',
    ];
}
