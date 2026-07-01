<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;

/** peguam_panel — panel lawyer master record (surrogate id PK added in migration). */
class PeguamPanel extends Model
{
    protected $table = 'peguam_panel';

    public $timestamps = false;

    protected $guarded = ['id'];

    protected $casts = [
        'tarikh_penugasan_peguam_panel' => 'date',
        'tarikhTidakAktif' => 'date',
    ];

    public const AKTIF = '1';

    public const TIDAK_AKTIF = '0';

    /** Deactivation justifications (legacy: JK Disiplin / Meninggal / Lain-lain). */
    public const SEBAB_DISIPLIN = 'Tindakan JK Disiplin';

    public const SEBAB_MENINGGAL = 'Meninggal Dunia';

    public const SEBAB_LAIN = 'Lain-lain';

    public const SEBAB_LIST = [self::SEBAB_DISIPLIN, self::SEBAB_MENINGGAL, self::SEBAB_LAIN];

    public function isAktif(): bool
    {
        return (string) $this->statusAktif !== self::TIDAK_AKTIF;
    }

    /** Detailed profile v1 (qualification/firm/bank/insurance), linked by IC. */
    public function butiran(): HasOne
    {
        return $this->hasOne(ButiranPeguamPanel::class, 'kpBaru', 'kp_peguam');
    }
}
