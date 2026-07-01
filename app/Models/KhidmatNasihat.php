<?php

namespace App\Models;

use App\Models\Scopes\CawanganScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Khidmat Nasihat - legal-advisory application (batch 9 core record).
 * Adapted from the .NET KhidmatNasihat entity. Foundation slice: schema +
 * list/show; the wizard create flow + eligibility screening land later.
 */
class KhidmatNasihat extends Model
{
    protected $table = 'khidmat_nasihat';

    protected $guarded = ['id'];

    /**
     * W21 - branch isolation, extending CawanganScope beyond Form. KN keys on the
     * numeric cawangan_id (+ cawangan_asal_id for D2 dual-branch), so the scope is
     * constructed by-branch-id. Closes the index()/route-binding cross-branch read
     * gap; view-all / no-branch staff and lawyers still see everything.
     */
    protected static function booted(): void
    {
        static::addGlobalScope(new CawanganScope('cawangan_id', 'cawangan_asal_id', true));
    }

    protected $casts = [
        'perakuan' => 'boolean',
        'status_bayaran' => 'boolean',
        'is_percuma' => 'boolean',
        'saringan_lulus' => 'boolean',
        'is_laluan_sumbangan' => 'boolean',
        'tarikh_lahir_mangsa' => 'date',
        'tarikh_proses' => 'datetime',
        'tarikh_buka_grab' => 'datetime',
        'tarikh_agihan_pl' => 'datetime',
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

    // ---- W1: explicit applicant source (derived from jenis_permohonan + jenis_wakil) ----
    public const SOURCE_PUBLIC = 'PUBLIC';   // DIRI_SENDIRI walk-in

    public const SOURCE_PRISON = 'PRISON';   // SEBAGAI_WAKIL + PENJARA

    public const SOURCE_CLINIC = 'CLINIC';   // SEBAGAI_WAKIL + JKM (welfare/clinic referral)

    public const SOURCE_COURT = 'COURT';     // SEBAGAI_WAKIL + MAHKAMAH

    public const APPLICANT_SOURCE = [self::SOURCE_PUBLIC, self::SOURCE_PRISON, self::SOURCE_CLINIC, self::SOURCE_COURT];

    /** Derive the explicit applicant_source tag from the intake type + wakil context. */
    public static function deriveSource(?string $jenisPermohonan, ?string $jenisWakil): string
    {
        if ($jenisPermohonan !== 'SEBAGAI_WAKIL') {
            return self::SOURCE_PUBLIC;
        }

        return match ($jenisWakil) {
            'PENJARA' => self::SOURCE_PRISON,
            'JKM' => self::SOURCE_CLINIC,
            'MAHKAMAH' => self::SOURCE_COURT,
            default => self::SOURCE_PUBLIC,
        };
    }

    /** Eligibility-screening jenis (FE selectedJenisKhidmat). */
    public const SARINGAN_SIVIL_SYARIAH = 'SIVIL_SYARIAH';

    public const SARINGAN_PENDAMPING = 'PENDAMPING';

    // ---- W5: external panel-lawyer assignment state machine (status_agihan_pl) ----
    /** Opened to the grab pool; any panel lawyer may self-claim within 7 days. */
    public const PL_BUKA_GRAB = 'BUKA_GRAB';

    /** Assigned to (or claimed by) an external panel lawyer. Terminal. */
    public const PL_DIAGIH = 'DIAGIH';

    /** Grab pool expired with no claim (7 days) - back to officer for re-assign/re-open. */
    public const PL_LUPUT = 'LUPUT';

    /** How an external lawyer got the KN (mod_agihan_peguam). */
    public const MOD_GRAB = 'GRAB';

    public const MOD_ASSIGN = 'ASSIGN';

    public function pengguna(): BelongsTo
    {
        return $this->belongsTo(User::class, 'id_pengguna');
    }

    /**
     * Assigned advisory officer (Pegawai Khidmat Nasihat) - batch 11 slice B.
     * Set on assign-PKN, which moves status_kn BAHARU->DALAM_PROSES.
     */
    public function pegawaiKn(): BelongsTo
    {
        return $this->belongsTo(User::class, 'id_pegawai_kn');
    }

    /**
     * Linked litigation case (forms row) - batch 11 slice C "Buka Kes" bridge.
     * Set once, when an officer opens a case from a SELESAI KN. id_forms is
     * mass-assignable ($guarded = ['id']); no DB FK (forms is the legacy spine).
     */
    public function forms(): BelongsTo
    {
        return $this->belongsTo(Form::class, 'id_forms');
    }

    /**
     * Resolve the linked court (slice 3 MAHKAMAH context). No DB FK - id_mahkamah
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

    /** Assigned external panel lawyer (W5). Set on grab/assign; surrogate id link. */
    public function peguamPanel(): BelongsTo
    {
        return $this->belongsTo(PeguamPanel::class, 'id_peguam_panel');
    }

    /** Fee-waiver proof (W1). Set when is_percuma + a waiver document is uploaded at intake. */
    public function lampiranWaiver(): BelongsTo
    {
        return $this->belongsTo(UploadedFile::class, 'id_lampiran_waiver');
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
     * Linked appointment (batch 10 temu_janji). No DB FK - wired at integration
     * via khidmat_nasihat.id_temu_janji <-> temu_janji.id (see migration notes).
     */
    public function temuJanji(): BelongsTo
    {
        return $this->belongsTo(TemuJanji::class, 'id_temu_janji');
    }

    /**
     * Post-appointment satisfaction feedback (batch 12). One per KN, captured
     * through the public maklum-balas link once status_kn is SELESAI.
     */
    public function maklumBalas(): HasOne
    {
        return $this->hasOne(MaklumBalas::class, 'khidmat_nasihat_id');
    }
}
