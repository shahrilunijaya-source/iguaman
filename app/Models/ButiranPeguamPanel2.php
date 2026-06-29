<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/** butiran_peguam_panel_2 — lawyer panel application v2 (director endorsement + KP decision workflow). */
class ButiranPeguamPanel2 extends Model
{
    protected $table = 'butiran_peguam_panel_2';

    public $timestamps = false;

    protected $guarded = ['id'];

    protected $casts = [
        'tarikhDiterimaMasuk' => 'date',
        'tarikhDiterimaMasukSyarie' => 'date',
        'tarikh_semakan_ppuu' => 'datetime',
        'tarikh_sokonganPengarah' => 'datetime',
        'tarikh_keputusanKP' => 'datetime',
        'tarikhMohon' => 'datetime',
        'tarikhBatal' => 'date',
        'tarikhTidakDiluluskan' => 'date',
    ];

    /** Professional qualifications (CLP / CSO / YBGK / ADR / eVendor). */
    public function qualifications(): HasOne
    {
        return $this->hasOne(ButiranPeguamPanel3::class, 'kpBaru', 'kpBaru');
    }

    /** Law firm + insurance. */
    public function firma(): HasOne
    {
        return $this->hasOne(ButiranPeguamPanel4::class, 'kpBaru', 'kpBaru');
    }

    /** Payment bank account. */
    public function bank(): HasOne
    {
        return $this->hasOne(ButiranPeguamPanel5::class, 'kpBaru', 'kpBaru');
    }

    /** Practice-area specialisation rows (bidang pengkhususan). */
    public function pengkhususan(): HasMany
    {
        return $this->hasMany(ButiranPeguamPanel6::class, 'kpBaru', 'kpBaru');
    }

    /** Uploaded registration/profile PDF documents (18 doc types). */
    public function documents(): HasMany
    {
        return $this->hasMany(UploadedFile::class, 'kpBaru', 'kpBaru');
    }
}
