<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class RolesAndPermissionsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Reset cached roles and permissions
        // app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // Create Memo Permissions
        $memoPermissions = [
            'view_any_memo',
            'view_memo',
            'create_memo',
            'update_memo',
            'delete_memo',
            'restore_memo',
            'force_delete_memo',
        ];

        foreach ($memoPermissions as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }

        // Create roles
        $roles = [
            'Admin',
            'Manager',
            'Supervisor',
            'HR',
            'Staff',
        ];

        $roleInstances = [];
        foreach ($roles as $roleName) {
            $roleInstances[$roleName] = Role::firstOrCreate(['name' => $roleName, 'guard_name' => 'web']);
        }

        // Assign Memo permissions to Admin, Manager, Supervisor, HR
        $memoAllowedRoles = ['Admin', 'Manager', 'Supervisor', 'HR'];
        foreach ($memoAllowedRoles as $roleName) {
            $roleInstances[$roleName]->givePermissionTo($memoPermissions);
        }
    }
}
