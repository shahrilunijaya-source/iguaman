<?php

namespace Tests\Feature;

use App\Models\Cawangan;
use App\Models\KhidmatNasihat;
use App\Models\RefKategoriKn;
use App\Models\SlotTemuJanji;
use App\Models\TemuJanji;
use App\Models\User;
use App\Support\KhidmatBayaran;
use Carbon\Carbon;
use Database\Seeders\Batch8MastersSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\TestUsersSeeder;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Batch 9 slice 2 — Khidmat Nasihat create wizard: store + slot booking + payment.
 * Live mysql per repo convention; PHPUNIT-tagged rows cleaned up.
 */
class Batch9CreateTest extends TestCase
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
        // Drop tagged applications + their booked appointments, then the tagged branch
        // (cascade clears slot_temu_janji).
        $ids = KhidmatNasihat::where('no_permohonan', 'like', self::TAG.'%')
            ->orWhere('nama_mangsa', 'like', self::TAG.'%')
            ->pluck('id');
        TemuJanji::whereIn('id_khidmat_nasihat', $ids)->delete();
        KhidmatNasihat::whereIn('id', $ids)->delete();
        Cawangan::where('nama', 'like', self::TAG.'%')->delete();
    }

    private function user(string $email): User
    {
        return User::where('email', $email)->firstOrFail();
    }

    /** A weekday at least 6 working days out (clears the 4-working-day lead with margin). */
    private function bookableDate(): Carbon
    {
        $d = Carbon::today()->addDays(10);
        while ($d->isWeekend()) {
            $d->addDay();
        }

        return $d;
    }

    /** Tagged branch + one open future slot. Returns [branch, date 'Y-m-d', time 'H:i']. */
    private function seedBranchWithSlot(): array
    {
        $branch = Cawangan::create([
            'jenis' => 'JBG',
            'kod' => 'TST',
            'nama' => self::TAG.' Slot Branch',
            'negeri_id' => 16, // Putrajaya — no test holiday seeded here
            'status_aktif' => true,
        ]);

        $date = $this->bookableDate()->toDateString();

        SlotTemuJanji::create([
            'cawangan_id' => $branch->id,
            'tarikh_slot' => $date,
            'masa_mula' => '09:00',
            'masa_akhir' => '09:30',
            'is_temujanji' => false,
            'status_aktif' => true,
        ]);

        return [$branch, $date, '09:00'];
    }

    private function kategori(string $jenis): RefKategoriKn
    {
        return RefKategoriKn::where('jenis_kategori', $jenis)->firstOrFail();
    }

    // ---- KhidmatBayaran (pure payment rules) ----

    public function test_bayaran_base_fee_is_rm10(): void
    {
        $this->assertSame(10.0, KhidmatBayaran::kira('SIVIL', 30000, false));
        $this->assertSame(10.0, KhidmatBayaran::kira('SYARIAH', null, false));
    }

    public function test_bayaran_sumbangan_rm260_when_income_over_50k_sivil(): void
    {
        $this->assertSame(260.0, KhidmatBayaran::kira('SIVIL', 50001, false));
        $this->assertSame(260.0, KhidmatBayaran::kira('SYARIAH', 80000, false));
        // Exactly at the threshold is NOT above it.
        $this->assertSame(10.0, KhidmatBayaran::kira('SIVIL', 50000, false));
    }

    public function test_bayaran_zero_for_pendamping_categories(): void
    {
        $this->assertSame(0.0, KhidmatBayaran::kira('PENDAMPING JENAYAH', 99999, false));
        $this->assertSame(0.0, KhidmatBayaran::kira('PENDAMPING GUAMAN', 99999, false));
    }

    public function test_bayaran_zero_when_is_percuma_overrides_all(): void
    {
        $this->assertSame(0.0, KhidmatBayaran::kira('SIVIL', 90000, true));
        $this->assertSame(0.0, KhidmatBayaran::kira('PENDAMPING JENAYAH', 0, true));
    }

    // ---- Create flow (full submit) ----

    public function test_store_full_submit_creates_record_books_slot_and_sets_baharu(): void
    {
        [$branch, $date, $time] = $this->seedBranchWithSlot();
        $kategori = $this->kategori('SIVIL');
        $slot = SlotTemuJanji::where('cawangan_id', $branch->id)->firstOrFail();

        $payload = [
            'aksi' => 'hantar',
            'nama_mangsa' => self::TAG.' Mangsa Hantar',
            'id_pengenalan_mangsa' => '900101015555',
            'cawangan_id' => $branch->id,
            'id_kategori' => $kategori->id,
            'id_negeri' => 16,
            'jumlah_pendapatan' => 60000, // > 50k + SIVIL -> RM260
            'is_percuma' => 0,
            'tarikh_temu_janji' => $date,
            'masa_temu_janji' => $time,
            'perakuan' => 1,
        ];

        $this->actingAs($this->user('pembantu@test.local'))
            ->post(route('khidmat.store'), $payload)
            ->assertRedirect();

        $row = KhidmatNasihat::where('nama_mangsa', self::TAG.' Mangsa Hantar')->firstOrFail();

        // status BAHARU + computed fee + perakuan
        $this->assertSame(KhidmatNasihat::STATUS_BAHARU, $row->status_kn);
        $this->assertSame('260.00', $row->jumlah_bayaran);
        $this->assertTrue($row->perakuan);
        $this->assertNotNull($row->no_permohonan);
        $this->assertStringStartsWith('KN/TST/', $row->no_permohonan);

        // temu_janji created + linked BOTH ways
        $this->assertNotNull($row->id_temu_janji);
        $temu = TemuJanji::findOrFail($row->id_temu_janji);
        $this->assertSame($row->id, $temu->id_khidmat_nasihat);
        $this->assertSame('MENUNGGU', $temu->status);
        $this->assertSame($slot->id, $temu->slot_temu_janji_id);

        // slot flipped to booked
        $this->assertTrue($slot->fresh()->is_temujanji);
    }

    public function test_store_draft_save_sets_draf_status_and_no_appointment(): void
    {
        [$branch] = $this->seedBranchWithSlot();
        $kategori = $this->kategori('SIVIL');

        $this->actingAs($this->user('pembantu@test.local'))
            ->post(route('khidmat.store'), [
                'aksi' => 'draf',
                'nama_mangsa' => self::TAG.' Mangsa Draf',
                'cawangan_id' => $branch->id,
                'id_kategori' => $kategori->id,
            ])
            ->assertRedirect();

        $row = KhidmatNasihat::where('nama_mangsa', self::TAG.' Mangsa Draf')->firstOrFail();
        $this->assertSame(KhidmatNasihat::STATUS_DRAF, $row->status_kn);
        $this->assertNull($row->id_temu_janji);
        $this->assertFalse($row->perakuan);
        $this->assertSame('10.00', $row->jumlah_bayaran); // base fee, no income
    }

    public function test_store_persists_percuma_fee_zero(): void
    {
        [$branch] = $this->seedBranchWithSlot();
        $kategori = $this->kategori('SIVIL');

        $this->actingAs($this->user('pembantu@test.local'))
            ->post(route('khidmat.store'), [
                'aksi' => 'draf',
                'nama_mangsa' => self::TAG.' Mangsa Percuma',
                'cawangan_id' => $branch->id,
                'id_kategori' => $kategori->id,
                'jumlah_pendapatan' => 90000,
                'is_percuma' => 1,
            ])
            ->assertRedirect();

        $row = KhidmatNasihat::where('nama_mangsa', self::TAG.' Mangsa Percuma')->firstOrFail();
        $this->assertSame('0.00', $row->jumlah_bayaran);
        $this->assertTrue($row->is_percuma);
    }

    // ---- Permission gating ----

    public function test_create_form_gate_allows_pembantu_blocks_peguam(): void
    {
        $this->actingAs($this->user('pembantu@test.local'))->get(route('khidmat.create'))->assertOk();
        $this->actingAs($this->user('peguam@test.local'))->get(route('khidmat.create'))->assertStatus(302);
    }

    public function test_store_gate_blocks_peguam(): void
    {
        [$branch] = $this->seedBranchWithSlot();
        $kategori = $this->kategori('SIVIL');

        $this->actingAs($this->user('peguam@test.local'))
            ->post(route('khidmat.store'), [
                'aksi' => 'draf',
                'nama_mangsa' => self::TAG.' Blocked',
                'cawangan_id' => $branch->id,
                'id_kategori' => $kategori->id,
            ])
            ->assertStatus(302);

        $this->assertDatabaseMissing('khidmat_nasihat', ['nama_mangsa' => self::TAG.' Blocked']);
    }
}
