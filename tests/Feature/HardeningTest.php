<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/** Pre-prod hardening — forced password change + security headers. Real DB, self-cleaning. */
class HardeningTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'mysql', 'database.connections.mysql.database' => 'iguaman_2in1']);
        DB::purge('mysql');
        DB::reconnect('mysql');
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
        (new \Database\Seeders\RolePermissionSeeder())->run();
        User::where('email', 'like', '%@phpunit.local')->delete();
    }

    protected function tearDown(): void
    {
        User::where('email', 'like', '%@phpunit.local')->delete();
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
        parent::tearDown();
    }

    private function staff(bool $mustChange): User
    {
        $user = User::create([
            'name' => 'PHPUnit Staff', 'email' => 'staff@phpunit.local',
            'password' => Hash::make('secret123'), 'user_type' => 'staff',
            'role' => 'admin', 'is_active' => true, 'must_change_password' => $mustChange,
        ]);
        $user->syncRoles(['admin']);

        return $user;
    }

    public function test_flagged_user_forced_to_change_password(): void
    {
        $this->actingAs($this->staff(true))
            ->get(route('system.utama'))
            ->assertRedirect(route('password.change'));
    }

    public function test_unflagged_user_not_redirected(): void
    {
        $this->actingAs($this->staff(false))
            ->get(route('system.utama'))
            ->assertOk();
    }

    public function test_change_password_clears_flag(): void
    {
        $user = $this->staff(true);

        $this->actingAs($user)
            ->post(route('password.change.update'), [
                'current_password' => 'secret123',
                'password' => 'BaharuKuat99',
                'password_confirmation' => 'BaharuKuat99',
            ])
            ->assertRedirect(route('system.utama'));

        $this->assertFalse($user->fresh()->must_change_password);
        $this->assertTrue(Hash::check('BaharuKuat99', $user->fresh()->password));
    }

    public function test_change_password_requires_correct_current(): void
    {
        $user = $this->staff(true);

        $this->actingAs($user)
            ->from(route('password.change'))
            ->post(route('password.change.update'), [
                'current_password' => 'wrong',
                'password' => 'BaharuKuat99',
                'password_confirmation' => 'BaharuKuat99',
            ])
            ->assertSessionHasErrors('current_password');

        $this->assertTrue($user->fresh()->must_change_password);
    }

    public function test_security_headers_present(): void
    {
        $this->get(route('system.login'))
            ->assertHeader('X-Frame-Options', 'DENY')
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
    }
}
