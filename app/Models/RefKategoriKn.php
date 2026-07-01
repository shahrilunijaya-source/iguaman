<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Khidmat Nasihat category — level 1 ("Jenis Khidmat").
 *
 * @property int $id
 * @property string $jenis_kategori
 * @property bool $aktif
 * @property-read Collection<int, RefKategoriKesKn> $kategoriKes
 */
class RefKategoriKn extends Model
{
    use SoftDeletes;

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
