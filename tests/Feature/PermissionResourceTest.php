<?php

namespace Tests\Feature;

use App\Filament\Resources\Permissions\PermissionResource;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PermissionResourceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed();
    }

    public function test_employee_cannot_access_permission_resource(): void
    {
        $user = User::factory()->create();

        $role = Role::where('code', 'employee')->firstOrFail();

        $user->roles()->attach($role);

        $this->actingAs($user);

        $this->assertFalse(PermissionResource::canViewAny());
        $this->assertFalse(PermissionResource::canCreate());
    }

    public function test_approver_cannot_access_permission_resource(): void
    {
        $user = User::factory()->create();

        $role = Role::where('code', 'approver')->firstOrFail();

        $user->roles()->attach($role);

        $this->actingAs($user);

        $this->assertFalse(PermissionResource::canViewAny());
        $this->assertFalse(PermissionResource::canCreate());
    }

    public function test_hr_admin_cannot_access_permission_resource(): void
    {
        $user = User::factory()->create();

        $role = Role::where('code', 'hr_admin')->firstOrFail();

        $user->roles()->attach($role);

        $this->actingAs($user);

        $this->assertFalse(PermissionResource::canViewAny());
        $this->assertFalse(PermissionResource::canCreate());
    }

    public function test_system_admin_can_view_and_manage_permissions(): void
    {
        $user = User::factory()->create();

        $role = Role::where('code', 'system_admin')->firstOrFail();

        $user->roles()->attach($role);

        $permission = Permission::where('code', 'sppd.view')->firstOrFail();

        $this->actingAs($user);

        $this->assertTrue(PermissionResource::canViewAny());
        $this->assertTrue(PermissionResource::canCreate());
        $this->assertTrue(PermissionResource::canEdit($permission));
    }

    public function test_permission_resource_delete_is_not_allowed(): void
    {
        $user = User::factory()->create();

        $role = Role::where('code', 'system_admin')->firstOrFail();

        $user->roles()->attach($role);

        $permission = Permission::where('code', 'sppd.view')->firstOrFail();

        $this->actingAs($user);

        $this->assertFalse(PermissionResource::canDelete($permission));
    }
}