<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Khidmat Nasihat category — level 2 (.NET "JenisKes"). */
class RefKategoriKesKn extends Model
{
    protected $table = 'ref_kategori_kes_kn';

    protected $guarded = ['id'];

    protected $casts = [
        'aktif' => 'boolean',
    ];

    public function kategori(): BelongsTo
    {
        return $this->belongsTo(RefKategoriKn::class, 'kategori_id');
    }

    public function subkategori(): HasMany
    {
        return $this->hasMany(RefSubkategoriKn::class, 'kategori_kes_id');
    }
}
