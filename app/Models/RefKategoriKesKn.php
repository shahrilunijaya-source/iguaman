<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Khidmat Nasihat category — level 2 (.NET "JenisKes").
 *
 * @property int $id
 * @property int $kategori_id
 * @property string $nama
 * @property bool $aktif
 * @property-read RefKategoriKn $kategori
 * @property-read Collection<int, RefSubkategoriKn> $subkategori
 */
class RefKategoriKesKn extends Model
{
    use SoftDeletes;

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
