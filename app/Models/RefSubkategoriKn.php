<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Khidmat Nasihat category — level 3 (FE-only sub-type). */
class RefSubkategoriKn extends Model
{
    protected $table = 'ref_subkategori_kn';

    protected $guarded = ['id'];

    protected $casts = [
        'aktif' => 'boolean',
    ];

    public function kategoriKes(): BelongsTo
    {
        return $this->belongsTo(RefKategoriKesKn::class, 'kategori_kes_id');
    }
}
