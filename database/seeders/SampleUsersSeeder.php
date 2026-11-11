<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use App\Models\User;
use Spatie\Permission\Models\Role;

class SampleUsersSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create Role Superadmin if not exists
        Role::firstOrCreate(['name' => 'Superadmin', 'guard_name' => 'web']);

        // Create base org structure
        $org = \App\Models\Organization::firstOrCreate(
            ['code' => 'HQ'],
            ['name' => 'Motiva HQ']
        );
        $orgId = $org->id;

        $dept = \App\Models\Department::firstOrCreate(
            ['code' => 'GEN'],
            ['name' => 'General Department', 'organization_id' => $orgId]
        );
        $deptId = $dept->id;

        $pos = \App\Models\Position::firstOrCreate(
            ['code' => 'STAFF'],
            ['name' => 'General Position']
        );
        $posId = $pos->id;

        $roles = [
            'Superadmin' => 'superadmin@motiva.com',
            'Manager' => 'manager@motiva.com',
            'Supervisor' => 'supervisor@motiva.com',
            'HR' => 'hr@motiva.com',
            'Staff' => 'staff@motiva.com',
        ];

        foreach ($roles as $roleName => $email) {
            $user = User::firstOrCreate(
                ['email' => $email],
                [
                    'name' => $roleName . ' User',
                    'password' => Hash::make('password'),
                    'email_verified_at' => now(),
                ]
            );

            // Assign role
            if (! $user->hasRole($roleName)) {
                $user->assignRole($roleName);
            }

            // Ensure employee exists
            if (! DB::table('employees')->where('user_id', $user->id)->exists()) {
                DB::table('employees')->insert([
                    'user_id' => $user->id,
                    'organization_id' => $orgId,
                    'department_id' => $deptId,
                    'position_id' => $posId,
                    'name' => $user->name,
                    'identity_number' => 'ID-' . strtoupper($roleName) . '-' . rand(1000, 9999),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }
}
