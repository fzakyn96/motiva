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
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // Create Memo Permissions
        $memoPermissions = [
            'view_any_memo',
            'view_memo',
            'create_memo',
            'update_memo',
            'delete_memo',
            'restore_memo',
            'force_delete_memo',
            'approve_memo',
        ];

        // Create SPPD Permissions
        $sppdPermissions = [
            'view_any_sppd',
            'view_sppd',
            'create_sppd',
            'update_sppd',
            'delete_sppd',
            'approve_sppd',
        ];

        // Create SPPD Cost Permissions
        $costPermissions = [
            'view_any_sppd::cost',
            'view_sppd::cost',
            'create_sppd::cost',
            'update_sppd::cost',
            'delete_sppd::cost',
        ];

        // Create SPPD Rate Standard Permissions
        $rateStandardPermissions = [
            'view_any_sppd::rate::standard',
            'view_sppd::rate::standard',
            'create_sppd::rate::standard',
            'update_sppd::rate::standard',
            'delete_sppd::rate::standard',
        ];

        // Create Reimbursement Permissions
        $reimbursementPermissions = [
            'view_any_reimbursement',
            'view_reimbursement',
            'create_reimbursement',
            'update_reimbursement',
            'delete_reimbursement',
            'approve_reimbursement',
        ];

        // Create Employee Master Permissions (Data Pegawai, Unit Kerja, Jabatan)
        $employeeMasterPermissions = [
            'view_any_employee',
            'view_employee',
            'create_employee',
            'update_employee',
            'delete_employee',
            'view_any_department',
            'view_department',
            'create_department',
            'update_department',
            'delete_department',
            'view_any_position',
            'view_position',
            'create_position',
            'update_position',
            'delete_position',
        ];

        // Create Hak Akses & Pengguna Permissions (Khusus Superadmin/Admin)
        $hakAksesPermissions = [
            'view_any_user',
            'view_user',
            'create_user',
            'update_user',
            'delete_user',
            'view_any_role',
            'view_role',
            'create_role',
            'update_role',
            'delete_role',
            'view_any_permission',
            'view_permission',
            'create_permission',
            'update_permission',
            'delete_permission',
        ];

        $allPermissions = array_merge(
            $memoPermissions,
            $sppdPermissions,
            $costPermissions,
            $rateStandardPermissions,
            $reimbursementPermissions,
            $employeeMasterPermissions,
            $hakAksesPermissions
        );

        foreach ($allPermissions as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }

        // Create roles (keep order consistent with live database)
        $roles = [
            'Superadmin',
            'Manager',
            'Supervisor',
            'HR',
            'Staff',
            'Admin',
        ];

        $roleInstances = [];
        foreach ($roles as $roleName) {
            $roleInstances[$roleName] = Role::firstOrCreate(['name' => $roleName, 'guard_name' => 'web']);
        }

        // Give all permissions to Superadmin & Admin
        $roleInstances['Superadmin']->syncPermissions($allPermissions);
        $roleInstances['Admin']->syncPermissions($allPermissions);

        // Assign Memo & SPPD permissions to Manager, Supervisor
        foreach (['Manager', 'Supervisor'] as $roleName) {
            $roleInstances[$roleName]->givePermissionTo($memoPermissions);
            $roleInstances[$roleName]->givePermissionTo($sppdPermissions);
        }

        // Sync HR permissions (Memo, SPPD, Biaya SPPD, Tarif Standar SPPD, Reimbursement, Data Pegawai/Unit/Jabatan - TANPA Hak Akses & Pengguna)
        $roleInstances['HR']->syncPermissions(array_merge(
            $memoPermissions,
            $sppdPermissions,
            $costPermissions,
            $rateStandardPermissions,
            $reimbursementPermissions,
            $employeeMasterPermissions
        ));

        // Assign Reimbursement permissions to Manager, Supervisor, Staff
        $roleInstances['Manager']->givePermissionTo($reimbursementPermissions);
        $roleInstances['Supervisor']->givePermissionTo($reimbursementPermissions);
        $roleInstances['Staff']->givePermissionTo([
            'view_any_memo',
            'view_memo',
            'create_memo',
            'update_memo',
            'view_any_sppd',
            'view_sppd',
            'create_sppd',
            'view_any_reimbursement',
            'view_reimbursement',
            'create_reimbursement',
            'update_reimbursement',
        ]);

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
    }
}
