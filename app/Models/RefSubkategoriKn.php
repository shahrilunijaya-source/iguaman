<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Khidmat Nasihat category — level 3 (FE-only sub-type).
 *
 * @property int $id
 * @property int $kategori_kes_id
 * @property string $nama
 * @property bool $aktif
 * @property-read RefKategoriKesKn $kategoriKes
 */
class RefSubkategoriKn extends Model
{
    use SoftDeletes;

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
