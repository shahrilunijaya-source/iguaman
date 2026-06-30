<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** uploaded_files — document attachments, optionally linked to a case via id_kes or a KN via id_khidmat. */
class UploadedFile extends Model
{
    protected $table = 'uploaded_files';

    public $timestamps = false;

    protected $guarded = ['id'];

    protected $casts = [
        'uploaded_at' => 'datetime',
        'id_kes' => 'integer',
        'id_khidmat' => 'integer',
    ];

    public function kes(): BelongsTo
    {
        return $this->belongsTo(Form::class, 'id_kes', 'id');
    }

    public function khidmatNasihat(): BelongsTo
    {
        return $this->belongsTo(KhidmatNasihat::class, 'id_khidmat', 'id');
    }

    /** Owning lawyer (registration/profile documents, keyed by IC). */
    public function peguam(): BelongsTo
    {
        return $this->belongsTo(ButiranPeguamPanel2::class, 'kpBaru', 'kpBaru');
    }
}
