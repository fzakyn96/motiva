<?php

namespace Tests\Feature\Filament;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Filament\Resources\Users\UserResource;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;

class UserResourceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed();
    }

    public function test_employee_cannot_view_user_resource(): void
    {
        $user = User::factory()->create();

        $role = Role::where('code', 'employee')->firstOrFail();

        $user->roles()->attach($role);

        $this->assertFalse(
            $user->can('user.view')
        );
    }

    public function test_employee_cannot_create_user(): void
    {
        $user = User::factory()->create();

        $role = Role::where('code', 'employee')->firstOrFail();

        $user->roles()->attach($role);

        $this->assertFalse(
            $user->can('user.create')
        );
    }

    public function test_employee_cannot_update_user(): void
    {
        $user = User::factory()->create();

        $role = Role::where('code', 'employee')->firstOrFail();

        $user->roles()->attach($role);

        $this->assertFalse(
            $user->can('user.update')
        );
    }

    public function test_hr_admin_can_view_user_resource(): void
    {
        $user = User::factory()->create();

        $role = Role::where('code', 'hr_admin')->firstOrFail();

        $user->roles()->attach($role);

        $this->assertTrue(
            $user->can('user.view')
        );
    }

    public function test_hr_admin_can_create_user(): void
    {
        $user = User::factory()->create();

        $role = Role::where('code', 'hr_admin')->firstOrFail();

        $user->roles()->attach($role);

        $this->assertTrue(
            $user->can('user.create')
        );
    }

    public function test_hr_admin_can_update_user(): void
    {
        $user = User::factory()->create();

        $role = Role::where('code', 'hr_admin')->firstOrFail();

        $user->roles()->attach($role);

        $this->assertTrue(
            $user->can('user.update')
        );
    }

    public function test_system_admin_can_manage_users(): void
    {
        $user = User::factory()->create();

        $role = Role::where('code', 'system_admin')->firstOrFail();

        $user->roles()->attach($role);

        $this->assertTrue(
            $user->can('user.view')
        );

        $this->assertTrue(
            $user->can('user.create')
        );

        $this->assertTrue(
            $user->can('user.update')
        );
    }

    public function test_employee_cannot_access_user_resource_authorization(): void
    {
        $user = User::factory()->create();

        $role = Role::where('code', 'employee')->firstOrFail();

        $user->roles()->attach($role);

        $this->actingAs($user);

        $this->assertFalse(
            UserResource::canViewAny()
        );

        $this->assertFalse(
            UserResource::canCreate()
        );
    }

    public function test_hr_admin_can_access_user_resource_authorization(): void
    {
        $user = User::factory()->create();

        $role = Role::where('code', 'hr_admin')->firstOrFail();

        $user->roles()->attach($role);

        $this->actingAs($user);

        $this->assertTrue(
            UserResource::canViewAny()
        );

        $this->assertTrue(
            UserResource::canCreate()
        );
    }

    public function test_system_admin_can_access_user_resource_authorization(): void
    {
        $user = User::factory()->create();

        $role = Role::where('code', 'system_admin')->firstOrFail();

        $user->roles()->attach($role);

        $this->actingAs($user);

        $this->assertTrue(
            UserResource::canViewAny()
        );

        $this->assertTrue(
            UserResource::canCreate()
        );
    }

    public function test_user_resource_edit_authorization_uses_policy(): void
    {
        $user = User::factory()->create();

        $role = Role::where('code', 'hr_admin')->firstOrFail();

        $user->roles()->attach($role);

        $target = User::factory()->create();

        $this->actingAs($user);

        $this->assertTrue(
            UserResource::canEdit($target)
        );
    }

    public function test_user_password_is_hashed_and_can_be_verified(): void
    {
        $user = User::factory()->create([
            'password' => 'initial-password',
        ]);

        $this->assertNotSame('initial-password', $user->password);

        $this->assertTrue(
            Hash::check('initial-password', $user->password)
        );
    }
}