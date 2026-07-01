<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * butiran_peguam_panel_6 - practice-area specialisation rows (bidang pengkhususan).
 * One lawyer has many. checkbox_value_status drives the add/drop approval machine
 * (0=new add, 1=approved, 3=drop requested, 4=add requested, etc - see pp-kes-oyd).
 */
class ButiranPeguamPanel6 extends Model
{
    protected $table = 'butiran_peguam_panel_6';

    public $timestamps = false;

    protected $guarded = ['id'];

    protected $casts = [
        'checkbox_value_status' => 'integer',
    ];

    // checkbox_value_status add/drop approval machine (legacy profil-kemaskinibidangkes).
    public const LEGACY_AKTIF = 1;      // approved at registration

    public const AKTIF = 2;             // approved & active

    public const DROP_MOHON = 3;        // lawyer requested removal

    public const ADD_MOHON = 4;         // lawyer requested addition

    public const DROP_DISOKONG = 7;     // Pengarah recommended removal -> KP deletes

    public const ADD_DISOKONG = 9;      // Pengarah recommended addition -> KP activates

    public const AKTIF_STATES = [self::LEGACY_AKTIF, self::AKTIF];

    public const PENGARAH_PENDING = [self::DROP_MOHON, self::ADD_MOHON];

    public const KP_PENDING = [self::DROP_DISOKONG, self::ADD_DISOKONG];

    public function peguam(): BelongsTo
    {
        return $this->belongsTo(ButiranPeguamPanel2::class, 'kpBaru', 'kpBaru');
    }
}
