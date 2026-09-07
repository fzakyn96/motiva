<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        $roles = [
            [
                'code' => 'system_admin',
                'name' => 'System Administrator',
                'description' => 'Administrator penuh aplikasi MOTIVA.',
                'is_active' => true,
            ],
            [
                'code' => 'employee',
                'name' => 'Employee',
                'description' => 'Pengguna umum MOTIVA.',
                'is_active' => true,
            ],
            [
                'code' => 'approver',
                'name' => 'Approver',
                'description' => 'Pengguna yang memiliki kewenangan persetujuan.',
                'is_active' => true,
            ],
            [
                'code' => 'hr_admin',
                'name' => 'HR Administrator',
                'description' => 'Administrator bidang kepegawaian.',
                'is_active' => true,
            ],
        ];

        foreach ($roles as $roleData) {
            Role::query()->updateOrCreate(
                ['code' => $roleData['code']],
                $roleData,
            );
        }

        $allPermissions = Permission::query()->pluck('id');

        Role::query()
            ->where('code', 'system_admin')
            ->firstOrFail()
            ->permissions()
            ->sync($allPermissions);

        Role::query()
            ->where('code', 'employee')
            ->firstOrFail()
            ->permissions()
            ->sync(
                Permission::query()
                    ->whereIn('code', [
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
                    ])
                    ->pluck('id')
            );

        Role::query()
            ->where('code', 'approver')
            ->firstOrFail()
            ->permissions()
            ->sync(
                Permission::query()
                    ->whereIn('code', [
                        'sppd.view',
                        'sppd.approve',
                        'sppd.reject',

                        'leave.view',
                        'leave.approve',
                        'leave.reject',

                        'overtime.view',
                        'overtime.approve',
                        'overtime.reject',
                    ])
                    ->pluck('id')
            );

        Role::query()
            ->where('code', 'hr_admin')
            ->firstOrFail()
            ->permissions()
            ->sync(
                Permission::query()
                    ->whereIn('code', [
                        'employee.view',
                        'employee.create',
                        'employee.update',

                        'user.view',
                        'user.create',
                        'user.update',

                        'sppd.view',
                        'leave.view',
                        'overtime.view',
                    ])
                    ->pluck('id')
            );
    }
}