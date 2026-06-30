<?php

namespace Tests\Feature\Awam;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AwamAuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_be_created_with_awam_type(): void
    {
        $user = User::factory()->create(['user_type' => 'awam', 'nokp' => '900101015555']);

        $this->assertDatabaseHas('users', ['id' => $user->id, 'user_type' => 'awam']);
    }

    public function test_awam_role_and_permission_exist(): void
    {
        $this->assertDatabaseHas('roles', ['name' => 'awam']);
        $this->assertDatabaseHas('permissions', ['name' => 'awam.portal']);

        $role = \Spatie\Permission\Models\Role::findByName('awam');
        $this->assertTrue($role->hasPermissionTo('awam.portal'));
    }

    public function test_awam_user_home_route_is_awam_dashboard(): void
    {
        $user = \App\Models\User::factory()->create(['user_type' => 'awam']);

        $this->assertTrue($user->isAwam());
        $this->assertSame('awam.dashboard', $user->homeRoute());
    }

    public function test_register_creates_awam_account_and_logs_in(): void
    {
        $this->withSession(['captcha_sum' => 7]);

        $response = $this->post('/awam/daftar', [
            'name' => 'Ali bin Abu', 'nokp' => '900101015555',
            'password' => 'rahsia123', 'password_confirmation' => 'rahsia123',
            'captcha' => 7, 'website' => '',
        ]);

        $response->assertRedirect(route('awam.dashboard'));
        $this->assertAuthenticated();
        $u = \App\Models\User::where('nokp', '900101015555')->first();
        $this->assertSame('awam', $u->user_type);
        $this->assertTrue($u->hasRole('awam'));
    }

    public function test_register_rejects_filled_honeypot(): void
    {
        $this->withSession(['captcha_sum' => 7]);
        $this->post('/awam/daftar', [
            'name' => 'Bot', 'nokp' => '900101015556',
            'password' => 'rahsia123', 'password_confirmation' => 'rahsia123',
            'captcha' => 7, 'website' => 'http://spam',
        ])->assertSessionHasErrors('website');
        $this->assertGuest();
    }

    public function test_register_rejects_wrong_captcha(): void
    {
        $this->withSession(['captcha_sum' => 7]);
        $this->post('/awam/daftar', [
            'name' => 'Ali', 'nokp' => '900101015557',
            'password' => 'rahsia123', 'password_confirmation' => 'rahsia123',
            'captcha' => 8, 'website' => '',
        ])->assertSessionHasErrors('captcha');
    }

    public function test_awam_login_by_ic_succeeds(): void
    {
        $u = \App\Models\User::factory()->create([
            'user_type' => 'awam', 'nokp' => '880202025555',
            'password' => \Illuminate\Support\Facades\Hash::make('rahsia123'), 'is_active' => true,
        ]);
        $u->assignRole('awam');

        $this->withSession(['captcha_sum' => 5]);
        $this->post('/awam/login', ['nokp' => '880202025555', 'password' => 'rahsia123', 'captcha' => 5])
            ->assertRedirect(route('awam.dashboard'));
        $this->assertAuthenticatedAs($u);
    }

    public function test_awam_cannot_reach_staff_area(): void
    {
        $u = \App\Models\User::factory()->create(['user_type' => 'awam']);
        $u->assignRole('awam');

        $this->actingAs($u)->get('/system')->assertStatus(403);
    }
}
