<?php

namespace Database\Seeders;

use App\Models\Permission;
use Illuminate\Database\Seeder;

class PermissionSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = [
            // SPPD
            [
                'code' => 'sppd.view',
                'module' => 'sppd',
                'action' => 'view',
                'name' => 'Melihat SPPD',
                'description' => 'Melihat data SPPD.',
            ],
            [
                'code' => 'sppd.create',
                'module' => 'sppd',
                'action' => 'create',
                'name' => 'Membuat SPPD',
                'description' => 'Membuat pengajuan SPPD.',
            ],
            [
                'code' => 'sppd.update',
                'module' => 'sppd',
                'action' => 'update',
                'name' => 'Mengubah SPPD',
                'description' => 'Mengubah data SPPD.',
            ],
            [
                'code' => 'sppd.submit',
                'module' => 'sppd',
                'action' => 'submit',
                'name' => 'Mengajukan SPPD',
                'description' => 'Mengajukan SPPD untuk proses persetujuan.',
            ],
            [
                'code' => 'sppd.cancel',
                'module' => 'sppd',
                'action' => 'cancel',
                'name' => 'Membatalkan SPPD',
                'description' => 'Membatalkan pengajuan SPPD.',
            ],
            [
                'code' => 'sppd.approve',
                'module' => 'sppd',
                'action' => 'approve',
                'name' => 'Menyetujui SPPD',
                'description' => 'Memberikan persetujuan terhadap SPPD.',
            ],
            [
                'code' => 'sppd.reject',
                'module' => 'sppd',
                'action' => 'reject',
                'name' => 'Menolak SPPD',
                'description' => 'Menolak pengajuan SPPD.',
            ],

            // Leave
            [
                'code' => 'leave.view',
                'module' => 'leave',
                'action' => 'view',
                'name' => 'Melihat Cuti',
                'description' => 'Melihat data cuti.',
            ],
            [
                'code' => 'leave.create',
                'module' => 'leave',
                'action' => 'create',
                'name' => 'Membuat Cuti',
                'description' => 'Membuat pengajuan cuti.',
            ],
            [
                'code' => 'leave.update',
                'module' => 'leave',
                'action' => 'update',
                'name' => 'Mengubah Cuti',
                'description' => 'Mengubah data pengajuan cuti.',
            ],
            [
                'code' => 'leave.submit',
                'module' => 'leave',
                'action' => 'submit',
                'name' => 'Mengajukan Cuti',
                'description' => 'Mengajukan cuti untuk proses persetujuan.',
            ],
            [
                'code' => 'leave.cancel',
                'module' => 'leave',
                'action' => 'cancel',
                'name' => 'Membatalkan Cuti',
                'description' => 'Membatalkan pengajuan cuti.',
            ],
            [
                'code' => 'leave.approve',
                'module' => 'leave',
                'action' => 'approve',
                'name' => 'Menyetujui Cuti',
                'description' => 'Memberikan persetujuan terhadap cuti.',
            ],
            [
                'code' => 'leave.reject',
                'module' => 'leave',
                'action' => 'reject',
                'name' => 'Menolak Cuti',
                'description' => 'Menolak pengajuan cuti.',
            ],

            // Overtime
            [
                'code' => 'overtime.view',
                'module' => 'overtime',
                'action' => 'view',
                'name' => 'Melihat Lembur',
                'description' => 'Melihat data lembur.',
            ],
            [
                'code' => 'overtime.create',
                'module' => 'overtime',
                'action' => 'create',
                'name' => 'Membuat Lembur',
                'description' => 'Membuat pengajuan lembur.',
            ],
            [
                'code' => 'overtime.update',
                'module' => 'overtime',
                'action' => 'update',
                'name' => 'Mengubah Lembur',
                'description' => 'Mengubah data lembur.',
            ],
            [
                'code' => 'overtime.submit',
                'module' => 'overtime',
                'action' => 'submit',
                'name' => 'Mengajukan Lembur',
                'description' => 'Mengajukan lembur untuk proses persetujuan.',
            ],
            [
                'code' => 'overtime.cancel',
                'module' => 'overtime',
                'action' => 'cancel',
                'name' => 'Membatalkan Lembur',
                'description' => 'Membatalkan pengajuan lembur.',
            ],
            [
                'code' => 'overtime.approve',
                'module' => 'overtime',
                'action' => 'approve',
                'name' => 'Menyetujui Lembur',
                'description' => 'Memberikan persetujuan terhadap lembur.',
            ],
            [
                'code' => 'overtime.reject',
                'module' => 'overtime',
                'action' => 'reject',
                'name' => 'Menolak Lembur',
                'description' => 'Menolak pengajuan lembur.',
            ],

            // Employee
            [
                'code' => 'employee.view',
                'module' => 'employee',
                'action' => 'view',
                'name' => 'Melihat Pegawai',
                'description' => 'Melihat data pegawai.',
            ],
            [
                'code' => 'employee.create',
                'module' => 'employee',
                'action' => 'create',
                'name' => 'Membuat Pegawai',
                'description' => 'Menambahkan data pegawai.',
            ],
            [
                'code' => 'employee.update',
                'module' => 'employee',
                'action' => 'update',
                'name' => 'Mengubah Pegawai',
                'description' => 'Mengubah data pegawai.',
            ],

            // User
            [
                'code' => 'user.view',
                'module' => 'user',
                'action' => 'view',
                'name' => 'Melihat User',
                'description' => 'Melihat data user aplikasi.',
            ],
            [
                'code' => 'user.create',
                'module' => 'user',
                'action' => 'create',
                'name' => 'Membuat User',
                'description' => 'Membuat user aplikasi.',
            ],
            [
                'code' => 'user.update',
                'module' => 'user',
                'action' => 'update',
                'name' => 'Mengubah User',
                'description' => 'Mengubah user aplikasi.',
            ],

            // Role
            [
                'code' => 'role.view',
                'module' => 'role',
                'action' => 'view',
                'name' => 'Melihat Role',
                'description' => 'Melihat role aplikasi.',
            ],
            [
                'code' => 'role.create',
                'module' => 'role',
                'action' => 'create',
                'name' => 'Membuat Role',
                'description' => 'Membuat role aplikasi.',
            ],
            [
                'code' => 'role.update',
                'module' => 'role',
                'action' => 'update',
                'name' => 'Mengubah Role',
                'description' => 'Mengubah role aplikasi.',
            ],

            // Permission
            [
                'code' => 'permission.view',
                'module' => 'permission',
                'action' => 'view',
                'name' => 'Melihat Permission',
                'description' => 'Melihat permission aplikasi.',
            ],
            [
                'code' => 'permission.manage',
                'module' => 'permission',
                'action' => 'manage',
                'name' => 'Mengelola Permission',
                'description' => 'Mengelola permission aplikasi.',
            ],
        ];

        foreach ($permissions as $permission) {
            Permission::query()->updateOrCreate(
                ['code' => $permission['code']],
                $permission,
            );
        }
    }
}