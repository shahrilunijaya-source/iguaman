<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Khidmat Nasihat — legal-advisory application (batch 9 core record).
 * Adapted from the .NET KhidmatNasihat entity. Foundation slice: schema +
 * list/show; the wizard create flow + eligibility screening land later.
 */
class KhidmatNasihat extends Model
{
    protected $table = 'khidmat_nasihat';

    protected $guarded = ['id'];

    protected $casts = [
        'perakuan' => 'boolean',
        'status_bayaran' => 'boolean',
        'is_percuma' => 'boolean',
        'saringan_lulus' => 'boolean',
        'is_laluan_sumbangan' => 'boolean',
        'tarikh_lahir_mangsa' => 'date',
        'tarikh_proses' => 'datetime',
        'jumlah_bayaran' => 'decimal:2',
        'jumlah_pendapatan' => 'decimal:2',
    ];

    public const STATUS_DRAF = 'DRAF';

    public const STATUS_BAHARU = 'BAHARU';

    public const STATUS_DALAM_PROSES = 'DALAM_PROSES';

    public const STATUS_SELESAI = 'SELESAI';

    public const STATUS_BATAL = 'BATAL';

    public const STATUS_KN = [
        self::STATUS_DRAF,
        self::STATUS_BAHARU,
        self::STATUS_DALAM_PROSES,
        self::STATUS_SELESAI,
        self::STATUS_BATAL,
    ];

    public const JENIS_PERMOHONAN = ['DIRI_SENDIRI', 'SEBAGAI_WAKIL'];

    /** SEBAGAI_WAKIL representative contexts (slice 3). */
    public const JENIS_WAKIL = ['PENJARA', 'JKM', 'MAHKAMAH'];

    /** Eligibility-screening jenis (FE selectedJenisKhidmat). */
    public const SARINGAN_SIVIL_SYARIAH = 'SIVIL_SYARIAH';

    public const SARINGAN_PENDAMPING = 'PENDAMPING';

    public function pengguna(): BelongsTo
    {
        return $this->belongsTo(User::class, 'id_pengguna');
    }

    /**
     * Assigned advisory officer (Pegawai Khidmat Nasihat) — batch 11 slice B.
     * Set on assign-PKN, which moves status_kn BAHARU->DALAM_PROSES.
     */
    public function pegawaiKn(): BelongsTo
    {
        return $this->belongsTo(User::class, 'id_pegawai_kn');
    }

    // forms() link (KN -> forms case bridge) is reserved for slice C — the
    // id_forms column exists but is not wired until that product decision lands.

    /**
     * Resolve the linked court (slice 3 MAHKAMAH context). No DB FK — id_mahkamah
     * points into mahkamah_sivil or mahkamah_syariah per jenis_mahkamah_pihak.
     */
    public function mahkamah(): ?Model
    {
        if ($this->id_mahkamah === null) {
            return null;
        }

        $class = $this->jenis_mahkamah_pihak === 'SYARIAH' ? MahkamahSyariah::class : MahkamahSivil::class;

        return $class::find($this->id_mahkamah);
    }

    public function cawangan(): BelongsTo
    {
        return $this->belongsTo(Cawangan::class, 'cawangan_id');
    }

    public function kategori(): BelongsTo
    {
        return $this->belongsTo(RefKategoriKn::class, 'id_kategori');
    }

    public function subkategori(): BelongsTo
    {
        return $this->belongsTo(RefSubkategoriKn::class, 'id_subkategori');
    }

    /**
     * Linked appointment (batch 10 temu_janji). No DB FK — wired at integration
     * via khidmat_nasihat.id_temu_janji <-> temu_janji.id (see migration notes).
     */
    public function temuJanji(): BelongsTo
    {
        return $this->belongsTo(TemuJanji::class, 'id_temu_janji');
    }
}
