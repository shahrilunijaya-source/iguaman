<?php

namespace Tests\Feature\Khidmat;

use App\Models\Cawangan;
use App\Models\KhidmatNasihat;
use App\Models\SlotTemuJanji;
use App\Models\TemuJanji;
use App\Support\KhidmatNasihatService;
use Carbon\Carbon;
use Database\Seeders\Batch8MastersSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\TestUsersSeeder;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Unit-style service tests for KhidmatNasihatService.
 * Live mysql per repo convention; PHPUNIT-tagged rows cleaned up.
 */
class KhidmatNasihatServiceTest extends TestCase
{
    private const TAG = 'PHPUNIT';

    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'mysql', 'database.connections.mysql.database' => env('DB_DATABASE', 'iguaman_2in1')]);
        DB::purge('mysql');
        DB::reconnect('mysql');
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        (new RolePermissionSeeder)->run();
        (new Batch8MastersSeeder)->run();
        (new TestUsersSeeder)->run();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->cleanup();
    }

    protected function tearDown(): void
    {
        $this->cleanup();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        parent::tearDown();
    }

    private function cleanup(): void
    {
        $ids = KhidmatNasihat::where('no_permohonan', 'like', self::TAG.'%')
            ->orWhere('nama_mangsa', 'like', self::TAG.'%')
            ->pluck('id');
        TemuJanji::whereIn('id_khidmat_nasihat', $ids)->delete();
        KhidmatNasihat::whereIn('id', $ids)->delete();
        Cawangan::where('nama', 'like', self::TAG.'%')->delete();
    }

    /** A weekday at least 10 days out — safely beyond the 4-working-day booking window. */
    private function bookableDate(): Carbon
    {
        $d = Carbon::today()->addDays(10);
        while ($d->isWeekend()) {
            $d->addDay();
        }

        return $d;
    }

    private function seedBranchWithSlot(): array
    {
        $branch = Cawangan::create([
            'jenis' => 'JBG',
            'kod' => 'SVC',
            'nama' => self::TAG.' Service Branch',
            'negeri_id' => 16,
            'status_aktif' => true,
        ]);

        $date = $this->bookableDate()->toDateString();

        $slot = SlotTemuJanji::create([
            'cawangan_id' => $branch->id,
            'tarikh_slot' => $date,
            'masa_mula' => '10:00',
            'masa_akhir' => '10:30',
            'is_temujanji' => false,
            'status_aktif' => true,
        ]);

        return [$branch, $date, '10:00', $slot];
    }

    private function service(): KhidmatNasihatService
    {
        return app(KhidmatNasihatService::class);
    }

    // ---- create() ----

    public function test_create_assigns_no_permohonan_with_cawangan_kod(): void
    {
        [$branch] = $this->seedBranchWithSlot();

        $kn = $this->service()->create([
            'nama_mangsa' => self::TAG.' Service Create',
            'cawangan_id' => $branch->id,
            'status_kn' => KhidmatNasihat::STATUS_DRAF,
            'jenis_permohonan' => 'DIRI_SENDIRI',
            'jumlah_bayaran' => 10.00,
            'is_percuma' => false,
            'perakuan' => false,
            'saringan_lulus' => false,
            'is_laluan_sumbangan' => false,
            'cipta_oleh' => 'test',
            'kemaskini_oleh' => 'test',
        ]);

        $this->assertNotNull($kn->no_permohonan);
        $this->assertStringStartsWith('KN/', $kn->no_permohonan);
        $this->assertStringContainsString('/SVC/', $kn->no_permohonan);
    }

    // ---- bookSlot() ----

    public function test_book_slot_creates_temu_janji_linked_both_ways_and_flips_slot(): void
    {
        [$branch, $date, $time, $slot] = $this->seedBranchWithSlot();

        // Create a KN row first (status DRAF, no appointment yet).
        $kn = $this->service()->create([
            'nama_mangsa' => self::TAG.' Service BookSlot',
            'cawangan_id' => $branch->id,
            'status_kn' => KhidmatNasihat::STATUS_DRAF,
            'jenis_permohonan' => 'DIRI_SENDIRI',
            'jumlah_bayaran' => 10.00,
            'is_percuma' => false,
            'perakuan' => false,
            'saringan_lulus' => false,
            'is_laluan_sumbangan' => false,
            'cipta_oleh' => 'test',
            'kemaskini_oleh' => 'test',
        ]);

        $temu = $this->service()->bookSlot($kn, $date, $time, 'pegawai ujian');

        // TemuJanji created with correct linkage.
        $this->assertSame($kn->id, $temu->id_khidmat_nasihat);
        $this->assertSame($slot->id, $temu->slot_temu_janji_id);
        $this->assertSame('MENUNGGU', $temu->status);

        // KhidmatNasihat.id_temu_janji updated.
        $kn->refresh();
        $this->assertSame($temu->id, $kn->id_temu_janji);

        // Slot flipped to booked.
        $this->assertTrue($slot->fresh()->is_temujanji);
    }
}
