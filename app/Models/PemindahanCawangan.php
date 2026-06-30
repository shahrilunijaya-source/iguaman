<?php

declare(strict_types=1);

namespace App\Models;

use App\Support\TransferCawanganService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * W7 + W3 — a single branch-transfer of a case (forms) or advisory
 * (khidmat_nasihat). Polymorphic over `jenis_rekod` + `id_rekod`; the moved
 * record's branch label is changed at transfer time, this row tracks the
 * DIPINDAH -> DITERIMA/DITOLAK lifecycle (reject reverses the label).
 *
 * No DB FKs — id_rekod / cawangan_*_id are soft links (legacy signed-int ids).
 * No CawanganScope here; branch isolation is enforced in
 * {@see TransferCawanganService}.
 */
class PemindahanCawangan extends Model
{
    protected $table = 'pemindahan_cawangan';

    protected $guarded = ['id'];

    protected $casts = [
        'id_rekod' => 'integer',
        'cawangan_asal_id' => 'integer',
        'cawangan_tujuan_id' => 'integer',
        'tarikh_pindah' => 'datetime',
        'tarikh_terima' => 'datetime',
    ];

    public const JENIS_KES = 'KES';

    public const JENIS_KN = 'KHIDMAT_NASIHAT';

    public const STATUS_DIPINDAH = 'DIPINDAH';

    public const STATUS_DITERIMA = 'DITERIMA';

    public const STATUS_DITOLAK = 'DITOLAK';

    /** The transferred case (only when jenis_rekod = KES). Soft link on id_rekod. */
    public function kes(): BelongsTo
    {
        return $this->belongsTo(Form::class, 'id_rekod');
    }

    /** The transferred advisory (only when jenis_rekod = KHIDMAT_NASIHAT). Soft link. */
    public function khidmatNasihat(): BelongsTo
    {
        return $this->belongsTo(KhidmatNasihat::class, 'id_rekod');
    }

    public function isKes(): bool
    {
        return $this->jenis_rekod === self::JENIS_KES;
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_DIPINDAH;
    }

    public function statusLabel(): string
    {
        return [
            self::STATUS_DIPINDAH => 'Menunggu Terima',
            self::STATUS_DITERIMA => 'Diterima',
            self::STATUS_DITOLAK => 'Ditolak',
        ][$this->status] ?? $this->status;
    }
}
