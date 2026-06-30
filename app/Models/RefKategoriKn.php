<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Khidmat Nasihat category — level 1 ("Jenis Khidmat"). */
class RefKategoriKn extends Model
{
    protected $table = 'ref_kategori_kn';

    protected $guarded = ['id'];

    protected $casts = [
        'aktif' => 'boolean',
    ];

    public function kategoriKes(): HasMany
    {
        return $this->hasMany(RefKategoriKesKn::class, 'kategori_id');
    }
}
