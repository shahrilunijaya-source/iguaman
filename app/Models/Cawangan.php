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
        'masa_buka' => 'string',
        'masa_tutup' => 'string',
        'tempoh_slot_minit' => 'integer',
    ];

    public const JENIS = ['JBG', 'JKM', 'PENJARA'];

    /**
     * Weekend day-numbers (ISO: 1=Mon … 7=Sun) for this branch, parsed from the
     * hari_minggu comma string. Returns null when unset so callers can fall back
     * to SlotAvailabilityService::WEEKEND (Sat/Sun).
     *
     * @return list<int>|null
     */
    public function weekendDays(): ?array
    {
        if ($this->hari_minggu === null || trim($this->hari_minggu) === '') {
            return null;
        }

        $days = collect(explode(',', $this->hari_minggu))
            ->map(fn ($d) => (int) trim($d))
            ->filter(fn ($d) => $d >= 1 && $d <= 7)
            ->unique()
            ->values()
            ->all();

        return $days ?: null;
    }

    public function bilik(): HasMany
    {
        return $this->hasMany(Bilik::class);
    }

    public function negeri(): BelongsTo
    {
        return $this->belongsTo(RefNegeri::class, 'negeri_id');
    }
}
