<?php

namespace Tests\Feature\Filament;

use App\Filament\Resources\Roles\RoleResource;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RoleResourceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed();
    }

    public function test_employee_cannot_access_role_resource(): void
    {
        $user = User::factory()->create();

        $role = Role::where('code', 'employee')->firstOrFail();

        $user->roles()->attach($role);

        $this->actingAs($user);

        $this->assertFalse(
            RoleResource::canViewAny()
        );

        $this->assertFalse(
            RoleResource::canCreate()
        );
    }

    public function test_hr_admin_cannot_access_role_resource(): void
    {
        $user = User::factory()->create();

        $role = Role::where('code', 'hr_admin')->firstOrFail();

        $user->roles()->attach($role);

        $this->actingAs($user);

        $this->assertFalse(
            RoleResource::canViewAny()
        );

        $this->assertFalse(
            RoleResource::canCreate()
        );
    }

    public function test_approver_cannot_access_role_resource(): void
    {
        $user = User::factory()->create();

        $role = Role::where('code', 'approver')->firstOrFail();

        $user->roles()->attach($role);

        $this->actingAs($user);

        $this->assertFalse(
            RoleResource::canViewAny()
        );

        $this->assertFalse(
            RoleResource::canCreate()
        );
    }

    public function test_system_admin_can_manage_roles(): void
    {
        $user = User::factory()->create();

        $role = Role::where('code', 'system_admin')->firstOrFail();

        $user->roles()->attach($role);

        $target = Role::where('code', 'employee')->firstOrFail();

        $this->actingAs($user);

        $this->assertTrue(
            RoleResource::canViewAny()
        );

        $this->assertTrue(
            RoleResource::canCreate()
        );

        $this->assertTrue(
            RoleResource::canEdit($target)
        );
    }

    public function test_role_resource_delete_is_not_allowed(): void
    {
        $user = User::factory()->create();

        $role = Role::where('code', 'system_admin')->firstOrFail();

        $user->roles()->attach($role);

        $target = Role::where('code', 'employee')->firstOrFail();

        $this->actingAs($user);

        $this->assertFalse(
            RoleResource::canDelete($target)
        );
    }
}