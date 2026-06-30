<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Cawangan master (JBG / JKM / Penjara). `nama` mirrors the legacy branch string
 * used by CawanganScope. Mahkamah is NOT here (reuse mahkamah_sivil/syariah).
 */
class Cawangan extends Model
{
    protected $table = 'cawangan';

    protected $guarded = ['id'];

    protected $casts = [
        'status_aktif' => 'boolean',
    ];

    public const JENIS = ['JBG', 'JKM', 'PENJARA'];

    public function bilik(): HasMany
    {
        return $this->hasMany(Bilik::class);
    }

    public function negeri(): BelongsTo
    {
        return $this->belongsTo(RefNegeri::class, 'negeri_id');
    }
}
