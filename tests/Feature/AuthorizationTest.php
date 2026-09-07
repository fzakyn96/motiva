<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Services\Authorization\AuthorizationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

class AuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_have_role(): void
    {
        $user = User::factory()->create();

        $role = Role::query()->create([
            'code' => 'staff',
            'name' => 'Staff',
            'description' => 'Staff MESI',
            'is_active' => true,
        ]);

        $user->roles()->attach($role);

        $this->assertTrue(
            $user->roles()->where('code', 'staff')->exists()
        );
    }

    public function test_user_can_have_permission_through_role(): void
    {
        $user = User::factory()->create();

        $role = Role::query()->create([
            'code' => 'sppd_staff',
            'name' => 'SPPD Staff',
            'description' => 'Pengguna SPPD',
            'is_active' => true,
        ]);

        $permission = Permission::query()->create([
            'code' => 'sppd.create',
            'module' => 'sppd',
            'action' => 'create',
            'name' => 'Membuat SPPD',
            'description' => null,
        ]);

        $role->permissions()->attach($permission);
        $user->roles()->attach($role);

        $authorization = app(
            \App\Services\Authorization\AuthorizationService::class
        );

        $this->assertTrue(
            $authorization->can($user, 'sppd.create')
        );
    }

    public function test_user_without_permission_is_denied(): void
    {
        $user = User::factory()->create();

        $authorization = app(
            \App\Services\Authorization\AuthorizationService::class
        );

        $this->assertFalse(
            $authorization->can($user, 'sppd.approve')
        );
    }

    public function test_laravel_gate_allows_user_with_permission(): void
    {
        $user = User::factory()->create();

        $role = Role::query()->create([
            'code' => 'sppd_staff',
            'name' => 'SPPD Staff',
            'description' => 'Pengguna SPPD',
            'is_active' => true,
        ]);

        $permission = Permission::query()->create([
            'code' => 'sppd.create',
            'module' => 'sppd',
            'action' => 'create',
            'name' => 'Membuat SPPD',
            'description' => null,
        ]);

        $role->permissions()->attach($permission);
        $user->roles()->attach($role);

        $this->assertTrue(
            Gate::forUser($user)->allows('sppd.create')
        );
    }

    public function test_laravel_gate_denies_user_without_permission(): void
    {
        $user = User::factory()->create();

        $this->assertFalse(
            Gate::forUser($user)->allows('sppd.approve')
        );
    }

    public function test_inactive_role_cannot_grant_permission(): void
    {
        $this->seed();

        $user = User::factory()->create();

        $role = Role::query()
            ->where('code', 'approver')
            ->firstOrFail();

        $role->update([
            'is_active' => false,
        ]);

        $user->roles()->attach($role);

        $this->assertFalse(
            app(AuthorizationService::class)
                ->can($user, 'sppd.approve')
        );
    }

    public function test_inactive_role_cannot_grant_permission_when_roles_are_already_loaded(): void
    {
        $this->seed();

        $user = User::factory()->create();

        $role = Role::query()
            ->where('code', 'approver')
            ->firstOrFail();

        $role->update([
            'is_active' => false,
        ]);

        $user->roles()->attach($role);

        // Sengaja load roles terlebih dahulu.
        $user->load('roles');

        $this->assertTrue(
            $user->relationLoaded('roles')
        );

        $this->assertFalse(
            app(AuthorizationService::class)
                ->can($user, 'sppd.approve')
        );
    }

    public function test_user_can_combine_permissions_from_multiple_roles(): void
    {
        $this->seed();

        $user = User::factory()->create();

        $employeeRole = Role::query()
            ->where('code', 'employee')
            ->firstOrFail();

        $approverRole = Role::query()
            ->where('code', 'approver')
            ->firstOrFail();

        $user->roles()->attach([
            $employeeRole->id,
            $approverRole->id,
        ]);

        $authorization = app(AuthorizationService::class);

        $permissions = $authorization->permissionsFor($user);

        $this->assertCount(21, $permissions);

        $this->assertTrue(
            $authorization->can($user, 'sppd.create')
        );

        $this->assertTrue(
            $authorization->can($user, 'sppd.approve')
        );

        $this->assertTrue(
            $authorization->can($user, 'leave.create')
        );

        $this->assertTrue(
            $authorization->can($user, 'leave.approve')
        );

        $this->assertTrue(
            $authorization->can($user, 'overtime.create')
        );

        $this->assertTrue(
            $authorization->can($user, 'overtime.approve')
        );
    }

    public function test_combined_roles_do_not_duplicate_permissions(): void
    {
        $this->seed();

        $user = User::factory()->create();

        $employeeRole = Role::query()
            ->where('code', 'employee')
            ->firstOrFail();

        $approverRole = Role::query()
            ->where('code', 'approver')
            ->firstOrFail();

        $user->roles()->attach([
            $employeeRole->id,
            $approverRole->id,
        ]);

        $authorization = app(AuthorizationService::class);

        $permissions = $authorization->permissionsFor($user);

        $this->assertCount(
            $permissions->pluck('id')->unique()->count(),
            $permissions
        );

        $this->assertCount(21, $permissions);
    }

    public function test_inactive_role_does_not_contribute_to_multi_role_permissions(): void
    {
        $this->seed();

        $user = User::factory()->create();

        $employeeRole = Role::query()
            ->where('code', 'employee')
            ->firstOrFail();

        $approverRole = Role::query()
            ->where('code', 'approver')
            ->firstOrFail();

        $approverRole->update([
            'is_active' => false,
        ]);

        $user->roles()->attach([
            $employeeRole->id,
            $approverRole->id,
        ]);

        $authorization = app(AuthorizationService::class);

        $this->assertTrue(
            $authorization->can($user, 'sppd.create')
        );

        $this->assertFalse(
            $authorization->can($user, 'sppd.approve')
        );

        $this->assertFalse(
            $authorization->can($user, 'leave.approve')
        );

        $this->assertFalse(
            $authorization->can($user, 'overtime.approve')
        );

        $this->assertCount(
            15,
            $authorization->permissionsFor($user)
        );
    }

    public function test_laravel_gate_supports_multiple_roles(): void
    {
        $this->seed();

        $user = User::factory()->create();

        $employeeRole = Role::query()
            ->where('code', 'employee')
            ->firstOrFail();

        $approverRole = Role::query()
            ->where('code', 'approver')
            ->firstOrFail();

        $user->roles()->attach([
            $employeeRole->id,
            $approverRole->id,
        ]);

        $this->assertTrue(
            Gate::forUser($user)->allows('sppd.create')
        );

        $this->assertTrue(
            Gate::forUser($user)->allows('sppd.approve')
        );

        $this->assertTrue(
            Gate::forUser($user)->allows('leave.create')
        );

        $this->assertTrue(
            Gate::forUser($user)->allows('leave.approve')
        );

        $this->assertTrue(
            Gate::forUser($user)->allows('overtime.create')
        );

        $this->assertTrue(
            Gate::forUser($user)->allows('overtime.approve')
        );
    }

    public function test_laravel_gate_ignores_inactive_role_in_multiple_roles(): void
    {
        $this->seed();

        $user = User::factory()->create();

        $employeeRole = Role::query()
            ->where('code', 'employee')
            ->firstOrFail();

        $approverRole = Role::query()
            ->where('code', 'approver')
            ->firstOrFail();

        $approverRole->update([
            'is_active' => false,
        ]);

        $user->roles()->attach([
            $employeeRole->id,
            $approverRole->id,
        ]);

        $this->assertTrue(
            Gate::forUser($user)->allows('sppd.create')
        );

        $this->assertFalse(
            Gate::forUser($user)->allows('sppd.approve')
        );

        $this->assertFalse(
            Gate::forUser($user)->allows('leave.approve')
        );

        $this->assertFalse(
            Gate::forUser($user)->allows('overtime.approve')
        );
    }

    public function test_laravel_gate_ignores_inactive_role_when_roles_are_already_loaded(): void
    {
        $this->seed();

        $user = User::factory()->create();

        $employeeRole = Role::query()
            ->where('code', 'employee')
            ->firstOrFail();

        $approverRole = Role::query()
            ->where('code', 'approver')
            ->firstOrFail();

        $approverRole->update([
            'is_active' => false,
        ]);

        $user->roles()->attach([
            $employeeRole->id,
            $approverRole->id,
        ]);

        // Sengaja load semua role sebelum Gate digunakan.
        $user->load('roles');

        $this->assertTrue(
            $user->relationLoaded('roles')
        );

        $this->assertTrue(
            Gate::forUser($user)->allows('sppd.create')
        );

        $this->assertFalse(
            Gate::forUser($user)->allows('sppd.approve')
        );
    }

    public function test_gate_before_does_not_bypass_other_gate_definitions(): void
    {
        $this->seed();

        $user = User::factory()->create();

        Gate::define('test.custom-ability', function (User $user) {
            return true;
        });

        $this->assertTrue(
            Gate::forUser($user)->allows('test.custom-ability')
        );
    }

    public function test_gate_before_does_not_override_other_gate_definitions(): void
    {
        $this->seed();

        $user = User::factory()->create();

        Gate::define('test.custom-ability', function (User $user) {
            return false;
        });

        $this->assertFalse(
            Gate::forUser($user)->allows('test.custom-ability')
        );
    }
}