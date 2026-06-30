<?php

namespace App\Models;

use App\Models\Scopes\CawanganScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * forms — the case spine (94 cols): legal-aid application, mediation, court case, assignment.
 * Legacy column names preserved. Decompose into Case + detail tables in a later phase.
 */
class Form extends Model
{
    protected $table = 'forms';

    public $timestamps = false; // legacy has created_at only, no updated_at

    protected static function booted(): void
    {
        // Branch isolation for front-line staff (legacy WHERE cawangan = session).
        static::addGlobalScope(new CawanganScope());
    }

    protected $guarded = ['id'];

    protected $casts = [
        'tarikh_permohonan' => 'date',
        'tarikh_khidmat_nasihat' => 'date',
        'tarikh_penugasan' => 'date',
        'tarikh_penugasan_peguam_panel' => 'date',
        'tarikh_sidang' => 'date',
        'tarikh_selesai' => 'date',
        'tarikh_tutup_fail' => 'date',
        'tarikh_mohon_khidmat_pp' => 'datetime',
        'created_at' => 'datetime',
        'is_duplicate' => 'boolean',
    ];

    public function laporanKes(): HasMany
    {
        return $this->hasMany(LaporanKes::class, 'id_kes', 'id');
    }

    public function sejarahPegawai(): HasMany
    {
        return $this->hasMany(SejarahPegawai::class, 'id_kes', 'id');
    }

    public function sejarahPeguamPanel(): HasMany
    {
        return $this->hasMany(SejarahPeguamPanel::class, 'id_kes', 'id');
    }

    public function sejarahSidang(): HasMany
    {
        return $this->hasMany(SejarahSidang::class, 'id_kes', 'id');
    }

    public function lampiran(): HasMany
    {
        return $this->hasMany(UploadedFile::class, 'id_kes', 'id');
    }

    /** Originating Khidmat Nasihat (W12 reverse sync), linked via khidmat_nasihat.id_forms. */
    public function khidmatNasihat(): HasOne
    {
        return $this->hasOne(KhidmatNasihat::class, 'id_forms', 'id');
    }
}
