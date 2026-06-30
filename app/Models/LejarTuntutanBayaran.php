<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * lejar_tuntutan_bayaran — central claim ledger (W15). Polymorphic by
 * (sumber, sumber_id); reused by wishes 5 / 9 / 19. Hard link id_kes -> forms.id.
 */
class LejarTuntutanBayaran extends Model
{
    protected $table = 'lejar_tuntutan_bayaran';

    protected $guarded = ['id'];

    protected $casts = [
        'jumlah_tuntutan' => 'decimal:2',
        'jumlah_diluluskan' => 'decimal:2',
        'jumlah_bayaran' => 'decimal:2',
        'status_bayaran' => 'boolean',
        'tarikh_tuntutan' => 'date',
        'tarikh_resit' => 'date',
        'tarikh_lulus' => 'datetime',
        'tarikh_bayar' => 'datetime',
    ];

    public const SUMBER_KN = 'KN';
    public const SUMBER_PEMBELAAN_AWAM = 'PEMBELAAN_AWAM';
    public const SUMBER_MEDIASI = 'MEDIASI';
    public const SUMBER_PEGUAM_LUAR = 'PEGUAM_LUAR';
    public const SUMBER_LAIN = 'LAIN';

    public const STATUS_DRAF = 'DRAF';
    public const STATUS_DIHANTAR = 'DIHANTAR';
    public const STATUS_SEMAKAN = 'SEMAKAN';
    public const STATUS_DILULUS = 'DILULUS';
    public const STATUS_DITOLAK = 'DITOLAK';
    public const STATUS_DIBAYAR = 'DIBAYAR';
    public const STATUS_BATAL = 'BATAL';

    /** Human labels (Bahasa Melayu) for the lifecycle. */
    public const STATUS_LABELS = [
        self::STATUS_DRAF => 'Draf',
        self::STATUS_DIHANTAR => 'Dihantar',
        self::STATUS_SEMAKAN => 'Dalam Semakan',
        self::STATUS_DILULUS => 'Diluluskan',
        self::STATUS_DITOLAK => 'Ditolak',
        self::STATUS_DIBAYAR => 'Telah Dibayar',
        self::STATUS_BATAL => 'Batal',
    ];

    public function statusLabel(): string
    {
        return self::STATUS_LABELS[$this->status_tuntutan] ?? $this->status_tuntutan;
    }

    public function form(): BelongsTo
    {
        return $this->belongsTo(Form::class, 'id_kes', 'id');
    }

    public function khidmatNasihat(): BelongsTo
    {
        return $this->belongsTo(KhidmatNasihat::class, 'id_khidmat_nasihat');
    }

    public function peguam(): BelongsTo
    {
        return $this->belongsTo(PeguamPanel::class, 'id_peguam_panel');
    }

    public function pengguna(): BelongsTo
    {
        return $this->belongsTo(User::class, 'id_pengguna');
    }

    public function cawanganRef(): BelongsTo
    {
        return $this->belongsTo(Cawangan::class, 'cawangan_id');
    }
}
