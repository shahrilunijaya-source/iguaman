<?php

namespace Tests\Feature;

use App\Models\Cawangan;
use App\Models\KhidmatNasihat;
use App\Models\UploadedFile as Lampiran;
use App\Models\User;
use Database\Seeders\Batch8MastersSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\TestUsersSeeder;
use Illuminate\Http\UploadedFile as HttpUploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * W1 — Khidmat Nasihat intake separated by applicant source (prison/clinic vs public),
 * a prison_officer intake role, and an optional fee-waiver attachment.
 */
class Phase4W1Test extends TestCase
{
    private const TAG = 'PHPUNITW1';

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
        $ids = KhidmatNasihat::where('nama_mangsa', 'like', self::TAG.'%')->pluck('id');
        Lampiran::whereIn('id_khidmat', $ids)->delete();
        KhidmatNasihat::whereIn('id', $ids)->delete();
        User::where('email', 'like', self::TAG.'%')->delete();
        Cawangan::where('nama', 'like', self::TAG.'%')->delete();
    }

    private function prisonOfficer(Cawangan $branch): User
    {
        $u = User::create([
            'name' => self::TAG.' Officer', 'email' => self::TAG.'@test.local',
            'password' => Hash::make('password'), 'user_type' => User::TYPE_STAFF,
            'role' => User::ROLE_PRISON_OFFICER, 'cawangan' => $branch->nama,
            'is_active' => true, 'must_change_password' => false,
        ]);
        $u->syncRoles([User::ROLE_PRISON_OFFICER]);

        return $u;
    }

    private function branch(): Cawangan
    {
        return Cawangan::create([
            'jenis' => 'JBG', 'kod' => 'TW1', 'nama' => self::TAG.' Branch',
            'negeri_id' => 16, 'status_aktif' => true,
        ]);
    }

    // ---- pure derivation ----

    public function test_derive_source_maps_intake_type_and_wakil_context(): void
    {
        $this->assertSame(KhidmatNasihat::SOURCE_PUBLIC, KhidmatNasihat::deriveSource('DIRI_SENDIRI', null));
        $this->assertSame(KhidmatNasihat::SOURCE_PRISON, KhidmatNasihat::deriveSource('SEBAGAI_WAKIL', 'PENJARA'));
        $this->assertSame(KhidmatNasihat::SOURCE_CLINIC, KhidmatNasihat::deriveSource('SEBAGAI_WAKIL', 'JKM'));
        $this->assertSame(KhidmatNasihat::SOURCE_COURT, KhidmatNasihat::deriveSource('SEBAGAI_WAKIL', 'MAHKAMAH'));
        // A SEBAGAI_WAKIL with an unknown context falls back to PUBLIC, never throws.
        $this->assertSame(KhidmatNasihat::SOURCE_PUBLIC, KhidmatNasihat::deriveSource('SEBAGAI_WAKIL', null));
    }

    // ---- role + intake ----

    public function test_prison_officer_role_exists_with_intake_grants(): void
    {
        $branch = $this->branch();
        $officer = $this->prisonOfficer($branch);

        $this->assertTrue($officer->can('khidmat.manage'));
        $this->assertTrue($officer->can('system.view'));
        // No claim-ledger or approval reach for an intake-only officer.
        $this->assertFalse($officer->can('tuntutan.lulus'));
    }

    public function test_prison_officer_intake_tags_source_prison(): void
    {
        $branch = $this->branch();
        $officer = $this->prisonOfficer($branch);

        $this->actingAs($officer)->post(route('khidmat.store'), [
            'aksi' => 'draf',
            'jenis_permohonan' => 'SEBAGAI_WAKIL',
            'jenis_wakil' => 'PENJARA',
            'nama_mangsa' => self::TAG.' Banduan',
            'cawangan_id' => $branch->id,
        ])->assertRedirect();

        $row = KhidmatNasihat::where('nama_mangsa', self::TAG.' Banduan')->firstOrFail();
        $this->assertSame(KhidmatNasihat::SOURCE_PRISON, $row->applicant_source);
        $this->assertSame('SEBAGAI_WAKIL', $row->jenis_permohonan);
    }

    public function test_public_walk_in_intake_tags_source_public(): void
    {
        $branch = $this->branch();
        $officer = $this->prisonOfficer($branch);

        $this->actingAs($officer)->post(route('khidmat.store'), [
            'aksi' => 'draf',
            'jenis_permohonan' => 'DIRI_SENDIRI',
            'nama_mangsa' => self::TAG.' Awam',
            'cawangan_id' => $branch->id,
        ])->assertRedirect();

        $row = KhidmatNasihat::where('nama_mangsa', self::TAG.' Awam')->firstOrFail();
        $this->assertSame(KhidmatNasihat::SOURCE_PUBLIC, $row->applicant_source);
    }

    // ---- fee waiver attachment ----

    public function test_fee_waiver_attachment_is_stored_and_linked_when_percuma(): void
    {
        Storage::fake('repositori');
        $branch = $this->branch();
        $officer = $this->prisonOfficer($branch);

        $this->actingAs($officer)->post(route('khidmat.store'), [
            'aksi' => 'draf',
            'jenis_permohonan' => 'DIRI_SENDIRI',
            'nama_mangsa' => self::TAG.' Percuma',
            'cawangan_id' => $branch->id,
            'is_percuma' => 1,
            'lampiran_waiver' => HttpUploadedFile::fake()->create('waiver.pdf', 120, 'application/pdf'),
        ])->assertRedirect();

        $row = KhidmatNasihat::where('nama_mangsa', self::TAG.' Percuma')->firstOrFail();
        $this->assertTrue($row->is_percuma);
        $this->assertNotNull($row->id_lampiran_waiver);

        $lampiran = Lampiran::findOrFail($row->id_lampiran_waiver);
        $this->assertSame($row->id, $lampiran->id_khidmat);
        Storage::disk('repositori')->assertExists($lampiran->file_path);
    }

    public function test_no_waiver_row_when_not_percuma(): void
    {
        Storage::fake('repositori');
        $branch = $this->branch();
        $officer = $this->prisonOfficer($branch);

        $this->actingAs($officer)->post(route('khidmat.store'), [
            'aksi' => 'draf',
            'jenis_permohonan' => 'DIRI_SENDIRI',
            'nama_mangsa' => self::TAG.' NoWaiver',
            'cawangan_id' => $branch->id,
            'is_percuma' => 0,
            'lampiran_waiver' => HttpUploadedFile::fake()->create('stray.pdf', 50, 'application/pdf'),
        ])->assertRedirect();

        $row = KhidmatNasihat::where('nama_mangsa', self::TAG.' NoWaiver')->firstOrFail();
        $this->assertNull($row->id_lampiran_waiver);
    }
}
