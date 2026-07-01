<?php

namespace Tests\Feature;

use App\Models\Cawangan;
use App\Models\KhidmatNasihat;
use App\Models\UploadedFile;
use App\Models\User;
use App\Support\AgihanLuarService;
use App\Support\RetensiLampiranService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Phase 7 (audit hardening, TEST-03) — coverage for the two previously-untested
 * scheduled jobs (grab:tamat-luput, lampiran:bersih-retensi).
 *
 * Live mysql per repo convention; rows tagged + cleaned. Deliberately exercises only
 * the single-row (luputkan) + read-only (expired) service methods, NEVER the global
 * run()/command — those process/DELETE every matching row in the shared dev DB (same
 * discipline as LebihMasaTest). The destructive --purge path therefore stays uncovered
 * until the suite has an isolated DB (TEST-01 full isolation).
 */
class Phase7HardeningTest extends TestCase
{
    private const TAG = 'PHPUNITP7';

    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'mysql', 'database.connections.mysql.database' => env('DB_DATABASE', 'iguaman_2in1')]);
        DB::purge('mysql');
        DB::reconnect('mysql');
        $this->cleanup();
    }

    protected function tearDown(): void
    {
        $this->cleanup();
        parent::tearDown();
    }

    private function cleanup(): void
    {
        UploadedFile::where('nama', 'like', self::TAG.'%')->delete();
        KhidmatNasihat::where('no_permohonan', 'like', self::TAG.'%')->delete();
    }

    // ---- W6 retention cutoff (RetensiLampiranService::expired) ----

    public function test_attachment_past_retention_window_is_flagged_expired(): void
    {
        $expired = UploadedFile::create([
            'nama' => self::TAG.' lama', 'file_name' => 'a.pdf', 'file_path' => 'phpunitp7/a.pdf',
            'file_type' => 'pdf', 'uploaded_at' => now()->subYears(RetensiLampiranService::RETENTION_YEARS + 1),
        ]);
        $recent = UploadedFile::create([
            'nama' => self::TAG.' baru', 'file_name' => 'b.pdf', 'file_path' => 'phpunitp7/b.pdf',
            'file_type' => 'pdf', 'uploaded_at' => now(),
        ]);

        $ids = app(RetensiLampiranService::class)->expired()->pluck('id');

        $this->assertTrue($ids->contains($expired->id), 'attachment past the 7-year window should be flagged expired');
        $this->assertFalse($ids->contains($recent->id), 'a fresh attachment must not be flagged expired');
    }

    // ---- W5 grab expiry (AgihanLuarService) ----

    public function test_unclaimed_grab_expires_to_luput_after_window(): void
    {
        $kn = $this->makeGrabKn(now()->subDays(AgihanLuarService::GRAB_DAYS + 1));

        $this->assertTrue(app(AgihanLuarService::class)->expired()->pluck('id')->contains($kn->id));

        app(AgihanLuarService::class)->luputkan($kn);

        $fresh = $kn->fresh();
        $this->assertSame(KhidmatNasihat::PL_LUPUT, $fresh->status_agihan_pl);
        $this->assertNull($fresh->tarikh_buka_grab);
    }

    public function test_recent_grab_is_not_expired(): void
    {
        $kn = $this->makeGrabKn(now()->subDay());

        $this->assertFalse(app(AgihanLuarService::class)->expired()->pluck('id')->contains($kn->id));
    }

    /** A KN sitting in the grab pool (BUKA_GRAB) opened at $bukaGrab. */
    private function makeGrabKn(Carbon $bukaGrab): KhidmatNasihat
    {
        return KhidmatNasihat::create([
            'no_permohonan' => self::TAG.'-'.substr(uniqid(), -8),
            'nama_mangsa' => self::TAG.' Mangsa',
            'jenis_permohonan' => 'DIRI_SENDIRI',
            'id_pengguna' => User::query()->value('id'),
            'cawangan_id' => Cawangan::where('status_aktif', true)->value('id') ?? 1,
            'status_kn' => KhidmatNasihat::STATUS_BAHARU,
            'is_percuma' => false,
            'perakuan' => true,
            'jumlah_bayaran' => 0,
            'status_agihan_pl' => KhidmatNasihat::PL_BUKA_GRAB,
            'mod_agihan_peguam' => KhidmatNasihat::MOD_GRAB,
            'tarikh_buka_grab' => $bukaGrab,
        ]);
    }
}
