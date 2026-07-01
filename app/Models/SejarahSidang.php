<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** sejarah_sidang - hearing/session history for a case. */
class SejarahSidang extends Model
{
    protected $table = 'sejarah_sidang';

    public $timestamps = false;

    protected $guarded = ['id'];

    protected $casts = [
        'tarikh_sidang' => 'date',
    ];

    public function form(): BelongsTo
    {
        return $this->belongsTo(Form::class, 'id_kes', 'id');
    }
}
