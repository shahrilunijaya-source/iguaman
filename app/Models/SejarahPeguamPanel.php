<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** sejarah_peguam_panel - lawyer assignment history for a case. */
class SejarahPeguamPanel extends Model
{
    /** CODE-04 - legacy history-row outcome markers (sejarah_peguam_panel.status). */
    public const STATUS_TOLAK = 'T';   // offer declined by the lawyer

    public const STATUS_SELESAI = 'S'; // case marked complete by the lawyer

    protected $table = 'sejarah_peguam_panel';

    public $timestamps = false;

    protected $guarded = ['id'];

    protected $casts = [
        'tarikh_penugasan' => 'date',
        'tarikhNextBicaraKes' => 'date',
        'permohonan_kali' => 'integer',
        'createdDate' => 'datetime',
        'modifiedDate' => 'datetime',
    ];

    public function form(): BelongsTo
    {
        return $this->belongsTo(Form::class, 'id_kes', 'id');
    }

    /** Next reassignment counter for a case (MAX+1 per id_kes). */
    public static function nextPermohonanKali(int $idKes): int
    {
        return (int) static::where('id_kes', $idKes)->max('permohonan_kali') + 1;
    }
}
