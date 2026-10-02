<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class IdentityRbacTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_be_assigned_role_and_permission()
    {
        $user = User::factory()->create();
        $role = Role::create(['name' => 'manager']);
        $permission = Permission::create(['name' => 'approve memo']);

        $role->givePermissionTo($permission);
        $user->assignRole('manager');

        $this->assertTrue($user->hasRole('manager'));
        $this->assertTrue($user->hasPermissionTo('approve memo'));
    }

    public function test_user_without_permission_cannot_perform_action()
    {
        $user = User::factory()->create();
        $this->assertFalse($user->hasPermissionTo('approve memo'));
    }
}
