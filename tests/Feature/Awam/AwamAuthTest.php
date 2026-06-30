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
}
