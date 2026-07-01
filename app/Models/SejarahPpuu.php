<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * sejarah_ppuu — PPUU case-assignment history spine: the PPUU lawyer pick (Pilihan A/B),
 * Pengarah endorsement, and Ketua Pengarah decision for one case, with aktif/tutup rotation
 * (one aktif row per case at a time). Drives the 3-tier agihan + tarik-diri workflows.
 */
class SejarahPpuu extends Model
{
    protected $table = 'sejarah_ppuu';

    public $timestamps = false;

    protected $guarded = ['id'];

    protected $casts = [
        'id_kes' => 'integer',
        'idPPUU' => 'integer',
        'tarikh_diberiAgihan' => 'datetime',
        'tarikh_syorPPUU' => 'datetime',
        'tarikh_PengarahKemaskini' => 'datetime',
        'tarikh_KPKemaskini' => 'datetime',
        'createdDate' => 'datetime',
        'modifiedDate' => 'datetime',
    ];

    public const REKOD_AKTIF = 'aktif';

    public const REKOD_TUTUP = 'tutup';

    public function form(): BelongsTo
    {
        return $this->belongsTo(Form::class, 'id_kes', 'id');
    }

    public function ppuu(): BelongsTo
    {
        return $this->belongsTo(User::class, 'idPPUU', 'id');
    }

    /** The current open assignment record for a case, if any. */
    public static function aktif(int $idKes): ?self
    {
        return static::where('id_kes', $idKes)->where('status_rekod', self::REKOD_AKTIF)->latest('id')->first();
    }
}
