<?php

namespace Tests\Feature\Awam;

use App\Models\Cawangan;
use App\Models\KhidmatNasihat;
use App\Models\SlotTemuJanji;
use App\Models\UploadedFile;
use App\Models\User;
use Carbon\Carbon;
use Database\Seeders\Batch8MastersSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\TestUsersSeeder;
use Illuminate\Http\UploadedFile as HttpUploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Batch 13 Slice C3 — citizen document upload + owner-gated download.
 * Live MySQL per repo convention; tagged rows cleaned up in tearDown.
 */
class AwamLampiranTest extends TestCase
{
    private const TAG  = 'PHPUNIT';
    private const DISK = 'local';

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
        User::where('email', 'like', self::TAG.'.awam%@test.local')->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        parent::tearDown();
    }

    private function cleanup(): void
    {
        $ids = KhidmatNasihat::where('no_permohonan', 'like', self::TAG.'%')
            ->orWhere('nama_mangsa', 'like', self::TAG.'%')
            ->pluck('id');
        UploadedFile::whereIn('id_khidmat', $ids)->delete();
        KhidmatNasihat::whereIn('id', $ids)->delete();
        $branchIds = Cawangan::where('nama', 'like', self::TAG.'%')->pluck('id');
        SlotTemuJanji::whereIn('cawangan_id', $branchIds)->delete();
        Cawangan::whereIn('id', $branchIds)->delete();
    }

    private function makeAwamUser(string $suffix = 'a'): User
    {
        $email = self::TAG.'.awam.'.$suffix.'@test.local';
        $user = User::updateOrCreate(
            ['email' => $email],
            [
                'name'                 => self::TAG.' Awam '.strtoupper($suffix),
                'user_type'            => User::TYPE_AWAM,
                'password'             => bcrypt('password'),
                'is_active'            => true,
                'must_change_password' => false,
            ]
        );
        $user->syncRoles(['awam']);

        return $user;
    }

    private function makeKn(User $user): KhidmatNasihat
    {
        return KhidmatNasihat::create([
            'no_permohonan'  => self::TAG.'-UL-'.uniqid(),
            'nama_mangsa'    => self::TAG.' Mangsa UL',
            'jenis_permohonan' => 'DIRI_SENDIRI',
            'id_pengguna'    => $user->id,
            'cawangan_id'    => Cawangan::where('status_aktif', true)->value('id') ?? 1,
            'status_kn'      => KhidmatNasihat::STATUS_BAHARU,
            'is_percuma'     => false,
            'perakuan'       => true,
            'jumlah_bayaran' => 0,
        ]);
    }

    public function test_citizen_can_upload_pdf_to_own_application(): void
    {
        Storage::fake(self::DISK);

        $owner = $this->makeAwamUser('ul1');
        $kn    = $this->makeKn($owner);

        $this->actingAs($owner)
            ->post(route('awam.lampiran.store', $kn), [
                'fail' => HttpUploadedFile::fake()->create('surat.pdf', 100, 'application/pdf'),
            ])
            ->assertRedirect(route('awam.permohonan.show', $kn));

        $this->assertDatabaseHas('uploaded_files', [
            'id_khidmat' => $kn->id,
            'nama'       => 'surat.pdf',
        ]);
    }

    public function test_upload_rejects_disallowed_mime(): void
    {
        Storage::fake(self::DISK);

        $owner = $this->makeAwamUser('ul2');
        $kn    = $this->makeKn($owner);

        $this->actingAs($owner)
            ->post(route('awam.lampiran.store', $kn), [
                'fail' => HttpUploadedFile::fake()->create('virus.exe', 50, 'application/octet-stream'),
            ])
            ->assertSessionHasErrors('fail');
    }

    public function test_non_owner_cannot_upload(): void
    {
        Storage::fake(self::DISK);

        $owner = $this->makeAwamUser('ul3');
        $other = $this->makeAwamUser('ul4');
        $kn    = $this->makeKn($owner);

        $this->actingAs($other)
            ->post(route('awam.lampiran.store', $kn), [
                'fail' => HttpUploadedFile::fake()->create('surat.pdf', 100, 'application/pdf'),
            ])
            ->assertStatus(403);
    }

    public function test_owner_can_download_own_file(): void
    {
        Storage::fake(self::DISK);

        $owner = $this->makeAwamUser('ul5');
        $kn    = $this->makeKn($owner);

        // Upload first.
        $this->actingAs($owner)
            ->post(route('awam.lampiran.store', $kn), [
                'fail' => HttpUploadedFile::fake()->create('bukti.pdf', 80, 'application/pdf'),
            ]);

        $lampiran = UploadedFile::where('id_khidmat', $kn->id)->firstOrFail();

        $this->actingAs($owner)
            ->get(route('awam.lampiran.download', [$kn, $lampiran->id]))
            ->assertSuccessful();
    }

    public function test_non_owner_cannot_download(): void
    {
        Storage::fake(self::DISK);

        $owner = $this->makeAwamUser('ul6');
        $other = $this->makeAwamUser('ul7');
        $kn    = $this->makeKn($owner);

        $this->actingAs($owner)
            ->post(route('awam.lampiran.store', $kn), [
                'fail' => HttpUploadedFile::fake()->create('rahsia.pdf', 80, 'application/pdf'),
            ]);

        $lampiran = UploadedFile::where('id_khidmat', $kn->id)->firstOrFail();

        $this->actingAs($other)
            ->get(route('awam.lampiran.download', [$kn, $lampiran->id]))
            ->assertStatus(403);
    }
}
