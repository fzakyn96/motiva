<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RbacSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_rbac_seeders_create_expected_roles_and_permissions(): void
    {
        $this->seed();

        $this->assertDatabaseCount('permissions', 32);
        $this->assertDatabaseCount('roles', 4);
    }

    public function test_rbac_seeders_are_idempotent(): void
    {
        $this->seed();
        $this->seed();

        $this->assertDatabaseCount('permissions', 32);
        $this->assertDatabaseCount('roles', 4);

        $this->assertDatabaseCount('permission_role', 65);
    }

    public function test_employee_role_has_expected_permissions(): void
    {
        $this->seed();

        $role = Role::query()
            ->where('code', 'employee')
            ->firstOrFail();

        $permissions = $role->permissions
            ->pluck('code')
            ->sort()
            ->values();

        $expected = collect([
            'sppd.view',
            'sppd.create',
            'sppd.update',
            'sppd.submit',
            'sppd.cancel',

            'leave.view',
            'leave.create',
            'leave.update',
            'leave.submit',
            'leave.cancel',

            'overtime.view',
            'overtime.create',
            'overtime.update',
            'overtime.submit',
            'overtime.cancel',
        ])->sort()->values();

        $this->assertSame(
            $expected->all(),
            $permissions->all()
        );
    }

    public function test_approver_role_has_expected_permissions(): void
    {
        $this->seed();

        $role = Role::query()
            ->where('code', 'approver')
            ->firstOrFail();

        $permissions = $role->permissions
            ->pluck('code')
            ->sort()
            ->values();

        $expected = collect([
            'sppd.view',
            'sppd.approve',
            'sppd.reject',

            'leave.view',
            'leave.approve',
            'leave.reject',

            'overtime.view',
            'overtime.approve',
            'overtime.reject',
        ])->sort()->values();

        $this->assertSame(
            $expected->all(),
            $permissions->all()
        );
    }

    public function test_system_admin_has_all_permissions(): void
    {
        $this->seed();

        $role = Role::query()
            ->where('code', 'system_admin')
            ->firstOrFail();

        $this->assertCount(
            Permission::count(),
            $role->permissions
        );
    }

    public function test_employee_cannot_approve(): void
    {
        $this->seed();

        $role = Role::query()
            ->where('code', 'employee')
            ->firstOrFail();

        $this->assertFalse(
            $role->permissions
                ->contains('code', 'sppd.approve')
        );

        $this->assertFalse(
            $role->permissions
                ->contains('code', 'leave.approve')
        );

        $this->assertFalse(
            $role->permissions
                ->contains('code', 'overtime.approve')
        );
    }

    public function test_approver_can_approve(): void
    {
        $this->seed();

        $role = Role::query()
            ->where('code', 'approver')
            ->firstOrFail();

        $this->assertTrue(
            $role->permissions
                ->contains('code', 'sppd.approve')
        );

        $this->assertTrue(
            $role->permissions
                ->contains('code', 'leave.approve')
        );

        $this->assertTrue(
            $role->permissions
                ->contains('code', 'overtime.approve')
        );
    }
}