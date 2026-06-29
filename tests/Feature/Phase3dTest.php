<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/** Phase 3d — statistik dashboard + Excel/PDF exports over the real iguaman_2in1 DB (read-only). */
class Phase3dTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'mysql', 'database.connections.mysql.database' => 'iguaman_2in1']);
        DB::purge('mysql');
        DB::reconnect('mysql');
        User::where('email', 'like', '%@phpunit.local')->delete();
    }

    protected function tearDown(): void
    {
        User::where('email', 'like', '%@phpunit.local')->delete();
        parent::tearDown();
    }

    private function staff(): User
    {
        return User::create([
            'name' => 'PHPUnit Staff', 'email' => 'staff@phpunit.local',
            'password' => Hash::make('secret'), 'user_type' => 'staff',
            'role' => 'pengarah', 'is_active' => true,
        ]);
    }

    public function test_statistik_index_loads(): void
    {
        $this->actingAs($this->staff())
            ->get(route('statistik.index'))
            ->assertOk()
            ->assertSee('Statistik')
            ->assertSee('Jumlah Kes');
    }

    public function test_excel_export_downloads(): void
    {
        $res = $this->actingAs($this->staff())->get(route('statistik.excel'));

        $res->assertOk();
        $this->assertStringContainsString('.xlsx', $res->headers->get('content-disposition'));
        $this->assertStringContainsString('attachment', $res->headers->get('content-disposition'));
    }

    public function test_pdf_export_downloads(): void
    {
        $res = $this->actingAs($this->staff())->get(route('statistik.pdf'));

        $res->assertOk();
        $this->assertSame('application/pdf', $res->headers->get('content-type'));
    }

    public function test_lawyer_cannot_access_statistik(): void
    {
        $lawyer = User::create([
            'name' => 'PHPUnit Peguam', 'email' => 'peguam@phpunit.local',
            'password' => Hash::make('secret'), 'user_type' => 'lawyer',
            'role' => 'peguam', 'is_active' => true,
        ]);

        $this->actingAs($lawyer)
            ->get(route('statistik.index'))
            ->assertRedirect(route('peguam.dashboard'));
    }
}
