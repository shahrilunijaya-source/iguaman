<?php

namespace Tests\Feature;

use App\Models\Form;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Phase 3b — permohonan CRUD over the real iguaman_2in1 DB.
 * phpunit.xml forces sqlite :memory:, but the legacy baseline migration is MySQL-specific,
 * so we run against the live mysql connection and clean up our own rows (tagged cawangan=PHPUNIT).
 */
class PermohonanTest extends TestCase
{
    private const TAG = 'PHPUNIT';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'database.default' => 'mysql',
            'database.connections.mysql.database' => 'iguaman_2in1',
        ]);
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
        Form::where('cawangan', self::TAG)->delete();
        User::where('email', 'like', '%@phpunit.local')->delete();
    }

    private function staff(): User
    {
        return User::create([
            'name' => 'PHPUnit Staff', 'email' => 'staff@phpunit.local',
            'password' => Hash::make('secret'), 'user_type' => 'staff',
            'role' => 'admin', 'is_active' => true,
        ]);
    }

    private function lawyer(): User
    {
        return User::create([
            'name' => 'PHPUnit Peguam', 'email' => 'peguam@phpunit.local',
            'password' => Hash::make('secret'), 'user_type' => 'lawyer',
            'role' => 'peguam', 'is_active' => true,
        ]);
    }

    public function test_staff_sees_create_form(): void
    {
        $this->actingAs($this->staff())
            ->get(route('kes.create'))
            ->assertOk()
            ->assertSee('Permohonan Baharu');
    }

    public function test_store_creates_case_with_audit_fields(): void
    {
        $res = $this->actingAs($this->staff())->post(route('kes.store'), [
            'nama' => 'UJIAN Pemohon',
            'nokp' => '900101015555',
            'cawangan' => self::TAG,
            'tarikh_permohonan' => '2026-06-01',
        ]);

        $kes = Form::where('cawangan', self::TAG)->first();
        $this->assertNotNull($kes);
        $res->assertRedirect(route('kes.show', $kes));

        $this->assertSame('UJIAN Pemohon', $kes->nama);
        $this->assertNotNull($kes->created_at);
        $this->assertSame('PHPUnit Staff', $kes->didaftarkan_oleh);
    }

    public function test_store_requires_nama(): void
    {
        $this->actingAs($this->staff())
            ->from(route('kes.create'))
            ->post(route('kes.store'), ['cawangan' => self::TAG])
            ->assertRedirect(route('kes.create'))
            ->assertSessionHasErrors('nama');

        $this->assertSame(0, Form::where('cawangan', self::TAG)->count());
    }

    public function test_update_edits_case(): void
    {
        $kes = Form::create([
            'nama' => 'Asal', 'cawangan' => self::TAG, 'diterima' => '', 'created_at' => now(),
        ]);

        $this->actingAs($this->staff())
            ->put(route('kes.update', $kes), ['nama' => 'Dikemaskini', 'cawangan' => self::TAG])
            ->assertRedirect(route('kes.show', $kes));

        $this->assertSame('Dikemaskini', $kes->fresh()->nama);
    }

    public function test_lawyer_cannot_access_permohonan(): void
    {
        $this->actingAs($this->lawyer())
            ->get(route('kes.create'))
            ->assertRedirect(route('peguam.dashboard'));
    }
}
