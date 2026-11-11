<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use App\Models\User;
use App\Models\Employee;
use App\Models\Organization;
use App\Models\Department;
use App\Models\Position;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class EmployeeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 1. Setup Permissions
        $permissions = [
            'view_any_memo', 'view_memo', 'create_memo', 'update_memo', 'delete_memo', 'restore_memo', 'force_delete_memo',
            'view_any_sppd', 'view_sppd', 'create_sppd', 'update_sppd', 'delete_sppd',
            'view_any_sppd::cost', 'view_sppd::cost', 'create_sppd::cost', 'update_sppd::cost', 'delete_sppd::cost',
            'view_any_sppd::rate::standard', 'view_sppd::rate::standard', 'create_sppd::rate::standard', 'update_sppd::rate::standard', 'delete_sppd::rate::standard',
            'view_any_reimbursement', 'view_reimbursement', 'create_reimbursement', 'update_reimbursement', 'delete_reimbursement',
            'view_any_employee', 'view_employee', 'create_employee', 'update_employee', 'delete_employee',
            'view_any_department', 'view_department', 'create_department', 'update_department', 'delete_department',
            'view_any_position', 'view_position', 'create_position', 'update_position', 'delete_position',
            'view_any_user', 'view_user', 'create_user', 'update_user', 'delete_user',
            'view_any_role', 'view_role', 'create_role', 'update_role', 'delete_role',
            'view_any_permission', 'view_permission', 'create_permission', 'update_permission', 'delete_permission',
            'approve_memo', 'approve_sppd', 'approve_reimbursement',
        ];

        foreach ($permissions as $perm) {
            Permission::firstOrCreate(['name' => $perm, 'guard_name' => 'web']);
        }

        // 2. Setup Roles (Superadmin, Manager, Supervisor, HR, Staff) - Tidak ada user Admin
        $roleSuperadmin = Role::firstOrCreate(['name' => 'Superadmin', 'guard_name' => 'web']);
        $roleManager = Role::firstOrCreate(['name' => 'Manager', 'guard_name' => 'web']);
        $roleSupervisor = Role::firstOrCreate(['name' => 'Supervisor', 'guard_name' => 'web']);
        $roleHR = Role::firstOrCreate(['name' => 'HR', 'guard_name' => 'web']);
        $roleStaff = Role::firstOrCreate(['name' => 'Staff', 'guard_name' => 'web']);

        $roleSuperadmin->syncPermissions($permissions);

        $roleManager->syncPermissions([
            'view_any_memo', 'view_memo', 'create_memo', 'update_memo', 'delete_memo',
            'view_any_sppd', 'view_sppd', 'create_sppd', 'update_sppd',
            'view_any_reimbursement', 'view_reimbursement', 'create_reimbursement', 'update_reimbursement',
            'view_any_employee', 'view_employee',
            'approve_memo', 'approve_sppd', 'approve_reimbursement',
        ]);

        $roleSupervisor->syncPermissions([
            'view_any_memo', 'view_memo', 'create_memo', 'update_memo',
            'view_any_sppd', 'view_sppd', 'create_sppd', 'update_sppd',
            'view_any_reimbursement', 'view_reimbursement', 'create_reimbursement', 'update_reimbursement',
            'view_any_employee', 'view_employee',
            'approve_sppd', 'approve_reimbursement',
        ]);

        $roleHR->syncPermissions([
            'view_any_memo', 'view_memo', 'create_memo', 'update_memo', 'delete_memo',
            'view_any_sppd', 'view_sppd', 'create_sppd', 'update_sppd', 'delete_sppd',
            'view_any_sppd::cost', 'view_sppd::cost', 'create_sppd::cost', 'update_sppd::cost', 'delete_sppd::cost',
            'view_any_sppd::rate::standard', 'view_sppd::rate::standard', 'create_sppd::rate::standard', 'update_sppd::rate::standard', 'delete_sppd::rate::standard',
            'view_any_reimbursement', 'view_reimbursement', 'create_reimbursement', 'update_reimbursement', 'delete_reimbursement',
            'view_any_employee', 'view_employee', 'create_employee', 'update_employee', 'delete_employee',
            'view_any_department', 'view_department', 'create_department', 'update_department', 'delete_department',
            'view_any_position', 'view_position', 'create_position', 'update_position', 'delete_position',
        ]);

        $roleStaff->syncPermissions([
            'view_any_memo', 'view_memo', 'create_memo', 'update_memo',
            'view_any_sppd', 'view_sppd', 'create_sppd',
            'view_any_reimbursement', 'view_reimbursement', 'create_reimbursement',
            'view_any_employee', 'view_employee',
        ]);

        // 3. Setup Organization
        $org = Organization::firstOrCreate(
            ['code' => 'MOTIVA'],
            ['name' => 'PT Motiva Nusantara']
        );

        // 4. Setup Departments (Unit / Area)
        $deptJakarta = Department::firstOrCreate(
            ['code' => 'JKT'],
            ['name' => 'Jakarta', 'organization_id' => $org->id]
        );

        // Pertahankan unit referensi legacy bila diperlukan
        Department::firstOrCreate(['code' => 'MESI'], ['name' => 'MESI', 'organization_id' => $org->id]);
        Department::firstOrCreate(['code' => 'REG1'], ['name' => 'Regional 1', 'organization_id' => $org->id]);
        Department::firstOrCreate(['code' => 'REG2'], ['name' => 'Regional 2', 'organization_id' => $org->id]);
        Department::firstOrCreate(['code' => 'REG3'], ['name' => 'Regional 3', 'organization_id' => $org->id]);

        // 5. Setup Positions
        $positionsData = [
            'Manager' => ['code' => 'MGR', 'level' => 1],
            'Supervisor' => ['code' => 'SPV', 'level' => 2],
            'HR Specialist' => ['code' => 'HR', 'level' => 3],
            'Driver' => ['code' => 'DRV', 'level' => 4],
            'Manajer Kontrak' => ['code' => 'MGR-KTR', 'level' => 4],
            'Sekretaris Kontrak' => ['code' => 'SEK-KTR', 'level' => 4],
            'Data Spatial Quality Assurance SSWJ AOSS - Regional 1' => ['code' => 'DS-QA-R1', 'level' => 4],
            'Data Spatial Quality Assurance SSWJ AOL - Regional 2' => ['code' => 'DS-QA-R2', 'level' => 4],
            'Data Spatial Quality Assurance SSWJ AOJBB - Regional 3' => ['code' => 'DS-QA-R3', 'level' => 4],
            'Data Spatial Quality Assurance SHG' => ['code' => 'DS-QA-SHG', 'level' => 4],
            'Data Non Spatial Quality Assurance Regional 1' => ['code' => 'DNS-QA-R1', 'level' => 4],
            'Data Non Spatial Quality Assurance Regional 2' => ['code' => 'DNS-QA-R2', 'level' => 4],
            'Data Non Spatial Quality Assurance Regional 3' => ['code' => 'DNS-QA-R3', 'level' => 4],
            'Operator Basis Data Spatial Regional 1' => ['code' => 'OBD-S-R1', 'level' => 4],
            'Operator Basis Data Spatial Regional 2' => ['code' => 'OBD-S-R2', 'level' => 4],
            'Operator Basis Data Spatial Regional 3' => ['code' => 'OBD-S-R3', 'level' => 4],
            'Operator Dokumen Teknis SHG' => ['code' => 'ODT-SHG', 'level' => 4],
            'Operator Dokumen Teknis Digital' => ['code' => 'ODT-DIG', 'level' => 4],
            'Operator Basis Data Asbuilt Drawing' => ['code' => 'OBD-ABD', 'level' => 4],
            'Operator Basis Data Non Spatial Regional 1' => ['code' => 'OBD-NS-R1', 'level' => 4],
            'Operator Basis Data Non Spatial Regional 2' => ['code' => 'OBD-NS-R2', 'level' => 4],
            'Operator Basis Data Non Spatial Regional 3' => ['code' => 'OBD-NS-R3', 'level' => 4],
            'SAP PM Operator PGN' => ['code' => 'SAP-PGN', 'level' => 4],
            'SAP PM Operator SHG' => ['code' => 'SAP-SHG', 'level' => 4],
            'System Analyst -All System' => ['code' => 'SA-ALL', 'level' => 4],
            'Programmer - AIMS MIRACLE' => ['code' => 'PRG-AIM', 'level' => 4],
            'Programmer - NICO, PICO' => ['code' => 'PRG-NPO', 'level' => 4],
            'Programmer - MOC, CR7, PSSR' => ['code' => 'PRG-MCP', 'level' => 4],
            'Programmer - BI Dashboard SHG' => ['code' => 'PRG-BI-SHG', 'level' => 4],
            'Programmer - BI Dasboard SH Pertamina' => ['code' => 'PRG-BI-SHP', 'level' => 4],
        ];

        $positions = [];
        foreach ($positionsData as $name => $meta) {
            $positions[$name] = Position::firstOrCreate(
                ['code' => $meta['code']],
                ['name' => $name, 'level' => $meta['level']]
            );
        }

        // 6. Buat Atasan: Manager (M Nuramzan Iftari)
        $managerUser = User::firstOrCreate(
            ['email' => 'nuramzan.iftari@motiva.com'],
            [
                'name' => 'M Nuramzan Iftari',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
            ]
        );
        $managerUser->syncRoles([$roleManager]);

        $managerEmployee = Employee::firstOrCreate(
            ['identity_number' => '125000001'],
            [
                'user_id' => $managerUser->id,
                'organization_id' => $org->id,
                'department_id' => $deptJakarta->id,
                'position_id' => $positions['Manager']->id,
                'manager_id' => null,
                'name' => 'M Nuramzan Iftari',
            ]
        );
        $managerEmployee->update([
            'user_id' => $managerUser->id,
            'department_id' => $deptJakarta->id,
            'position_id' => $positions['Manager']->id,
            'manager_id' => null,
            'name' => 'M Nuramzan Iftari',
        ]);

        // 7. Buat Atasan: Supervisor 1 (Ikhsan Aditya Wardana)
        $spvIkhsanUser = User::firstOrCreate(
            ['email' => 'ikhsan.wardana@motiva.com'],
            [
                'name' => 'Ikhsan Aditya Wardana',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
            ]
        );
        $spvIkhsanUser->syncRoles([$roleSupervisor]);

        $spvIkhsanEmployee = Employee::firstOrCreate(
            ['identity_number' => '125000002'],
            [
                'user_id' => $spvIkhsanUser->id,
                'organization_id' => $org->id,
                'department_id' => $deptJakarta->id,
                'position_id' => $positions['Supervisor']->id,
                'manager_id' => $managerEmployee->id,
                'name' => 'Ikhsan Aditya Wardana',
            ]
        );
        $spvIkhsanEmployee->update([
            'user_id' => $spvIkhsanUser->id,
            'department_id' => $deptJakarta->id,
            'position_id' => $positions['Supervisor']->id,
            'manager_id' => $managerEmployee->id,
            'name' => 'Ikhsan Aditya Wardana',
        ]);

        // 8. Buat Atasan: Supervisor 2 (Didiet Pradityo)
        $spvDidietUser = User::firstOrCreate(
            ['email' => 'didiet.pradityo@motiva.com'],
            [
                'name' => 'Didiet Pradityo',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
            ]
        );
        $spvDidietUser->syncRoles([$roleSupervisor]);

        $spvDidietEmployee = Employee::firstOrCreate(
            ['identity_number' => '125000003'],
            [
                'user_id' => $spvDidietUser->id,
                'organization_id' => $org->id,
                'department_id' => $deptJakarta->id,
                'position_id' => $positions['Supervisor']->id,
                'manager_id' => $managerEmployee->id,
                'name' => 'Didiet Pradityo',
            ]
        );
        $spvDidietEmployee->update([
            'user_id' => $spvDidietUser->id,
            'department_id' => $deptJakarta->id,
            'position_id' => $positions['Supervisor']->id,
            'manager_id' => $managerEmployee->id,
            'name' => 'Didiet Pradityo',
        ]);

        // 9. Buat Akun HR (hr@motiva.com)
        $hrUser = User::firstOrCreate(
            ['email' => 'hr@motiva.com'],
            [
                'name' => 'HR Motiva',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
            ]
        );
        $hrUser->syncRoles([$roleHR]);

        $hrEmployee = Employee::firstOrCreate(
            ['identity_number' => '125000004'],
            [
                'user_id' => $hrUser->id,
                'organization_id' => $org->id,
                'department_id' => $deptJakarta->id,
                'position_id' => $positions['HR Specialist']->id,
                'manager_id' => $managerEmployee->id,
                'name' => 'HR Motiva',
            ]
        );
        $hrEmployee->update([
            'user_id' => $hrUser->id,
            'department_id' => $deptJakarta->id,
            'position_id' => $positions['HR Specialist']->id,
            'manager_id' => $managerEmployee->id,
            'name' => 'HR Motiva',
        ]);

        // Lookup map atasan untuk staf
        $leaderLookup = [
            'Ikhsan Aditya Wardana' => $spvIkhsanEmployee->id,
            'Didiet Pradityo' => $spvDidietEmployee->id,
            'M Nuramzan Iftari' => $managerEmployee->id,
        ];

        // 10. Daftar 32 Sampel Pegawai
        $rawEmployees = [
            ['nip' => '125770077', 'name' => 'Triyono', 'position' => 'Driver', 'area' => 'Jakarta', 'supervisor' => null, 'manager' => 'M Nuramzan Iftari', 'email' => 'triyono@motiva.com'],
            ['nip' => '126910050', 'name' => 'Sutikno', 'position' => 'Driver', 'area' => 'Jakarta', 'supervisor' => null, 'manager' => 'M Nuramzan Iftari', 'email' => 'sutikno@motiva.com'],
            ['nip' => '125900071', 'name' => 'Panji Abyul Novian', 'position' => 'Manajer Kontrak', 'area' => 'Jakarta', 'supervisor' => 'Ikhsan Aditya Wardana', 'manager' => 'M Nuramzan Iftari', 'email' => 'panji.novian@motiva.com'],
            ['nip' => '125970065', 'name' => 'Maria Ulfah', 'position' => 'Sekretaris Kontrak', 'area' => 'Jakarta', 'supervisor' => 'Ikhsan Aditya Wardana', 'manager' => 'M Nuramzan Iftari', 'email' => 'maria.ulfah@motiva.com'],
            ['nip' => '125980056', 'name' => 'Chairunnisa', 'position' => 'Data Spatial Quality Assurance SSWJ AOSS - Regional 1', 'area' => 'Jakarta', 'supervisor' => 'Didiet Pradityo', 'manager' => 'M Nuramzan Iftari', 'email' => 'chairunnisa@motiva.com'],
            ['nip' => '125960076', 'name' => 'Siti Fatimah', 'position' => 'Data Spatial Quality Assurance SSWJ AOL - Regional 2', 'area' => 'Jakarta', 'supervisor' => 'Didiet Pradityo', 'manager' => 'M Nuramzan Iftari', 'email' => 'siti.fatimah@motiva.com'],
            ['nip' => '125950064', 'name' => 'Lutfiana Damayanti', 'position' => 'Data Spatial Quality Assurance SSWJ AOJBB - Regional 3', 'area' => 'Jakarta', 'supervisor' => 'Didiet Pradityo', 'manager' => 'M Nuramzan Iftari', 'email' => 'lutfiana.damayanti@motiva.com'],
            ['nip' => '125920052', 'name' => 'Agung Nugraha', 'position' => 'Data Spatial Quality Assurance SHG', 'area' => 'Jakarta', 'supervisor' => 'Didiet Pradityo', 'manager' => 'M Nuramzan Iftari', 'email' => 'agung.nugraha@motiva.com'],
            ['nip' => '125880075', 'name' => 'Shahriar Aditya Putra', 'position' => 'Data Non Spatial Quality Assurance Regional 1', 'area' => 'Jakarta', 'supervisor' => 'Didiet Pradityo', 'manager' => 'M Nuramzan Iftari', 'email' => 'shahriar.putra@motiva.com'],
            ['nip' => '125940055', 'name' => 'Aris Dwi Yunanto', 'position' => 'Data Non Spatial Quality Assurance Regional 2', 'area' => 'Jakarta', 'supervisor' => 'Didiet Pradityo', 'manager' => 'M Nuramzan Iftari', 'email' => 'aris.yunanto@motiva.com'],
            ['nip' => '125910073', 'name' => 'Rizki Sandy', 'position' => 'Data Non Spatial Quality Assurance Regional 3', 'area' => 'Jakarta', 'supervisor' => 'Didiet Pradityo', 'manager' => 'M Nuramzan Iftari', 'email' => 'rizki.sandy@motiva.com'],
            ['nip' => '125990057', 'name' => 'Dewi Fitriana', 'position' => 'Operator Basis Data Spatial Regional 1', 'area' => 'Jakarta', 'supervisor' => 'Didiet Pradityo', 'manager' => 'M Nuramzan Iftari', 'email' => 'dewi.fitriana@motiva.com'],
            ['nip' => '125980139', 'name' => 'Mita Septiana', 'position' => 'Operator Basis Data Spatial Regional 1', 'area' => 'Jakarta', 'supervisor' => 'Didiet Pradityo', 'manager' => 'M Nuramzan Iftari', 'email' => 'mita.septiana@motiva.com'],
            ['nip' => '125970070', 'name' => 'Nuril Syahida', 'position' => 'Operator Basis Data Spatial Regional 2', 'area' => 'Jakarta', 'supervisor' => 'Didiet Pradityo', 'manager' => 'M Nuramzan Iftari', 'email' => 'nuril.syahida@motiva.com'],
            ['nip' => '125010062', 'name' => 'Ika Novia Taryuni', 'position' => 'Operator Basis Data Spatial Regional 2', 'area' => 'Jakarta', 'supervisor' => 'Didiet Pradityo', 'manager' => 'M Nuramzan Iftari', 'email' => 'ika.taryuni@motiva.com'],
            ['nip' => '125980126', 'name' => 'Salma Hayati Inda Robbi', 'position' => 'Operator Basis Data Spatial Regional 2', 'area' => 'Jakarta', 'supervisor' => 'Didiet Pradityo', 'manager' => 'M Nuramzan Iftari', 'email' => 'salma.robbi@motiva.com'],
            ['nip' => '125980072', 'name' => 'Risa Erfianti', 'position' => 'Operator Basis Data Spatial Regional 3', 'area' => 'Jakarta', 'supervisor' => 'Didiet Pradityo', 'manager' => 'M Nuramzan Iftari', 'email' => 'risa.erfianti@motiva.com'],
            ['nip' => '126010139', 'name' => 'Rachma Dewi Zulkarnain', 'position' => 'Operator Basis Data Spatial Regional 3', 'area' => 'Jakarta', 'supervisor' => 'Didiet Pradityo', 'manager' => 'M Nuramzan Iftari', 'email' => 'rachma.zulkarnain@motiva.com'],
            ['nip' => '126000024', 'name' => 'Alfian Akbar Maulana', 'position' => 'Operator Dokumen Teknis SHG', 'area' => 'Jakarta', 'supervisor' => 'Didiet Pradityo', 'manager' => 'M Nuramzan Iftari', 'email' => 'alfian.maulana@motiva.com'],
            ['nip' => '126780025', 'name' => 'Jufraini', 'position' => 'Operator Dokumen Teknis Digital', 'area' => 'Jakarta', 'supervisor' => 'Didiet Pradityo', 'manager' => 'M Nuramzan Iftari', 'email' => 'jufraini@motiva.com'],
            ['nip' => '126990002', 'name' => 'Dini Tiara', 'position' => 'Operator Basis Data Asbuilt Drawing', 'area' => 'Jakarta', 'supervisor' => 'Didiet Pradityo', 'manager' => 'M Nuramzan Iftari', 'email' => 'dini.tiara@motiva.com'],
            ['nip' => '125920054', 'name' => 'Arie Masyaid Lubis', 'position' => 'Operator Basis Data Non Spatial Regional 1', 'area' => 'Jakarta', 'supervisor' => 'Didiet Pradityo', 'manager' => 'M Nuramzan Iftari', 'email' => 'arie.lubis@motiva.com'],
            ['nip' => '125880074', 'name' => 'Saldy Michael', 'position' => 'Operator Basis Data Non Spatial Regional 2', 'area' => 'Jakarta', 'supervisor' => 'Didiet Pradityo', 'manager' => 'M Nuramzan Iftari', 'email' => 'saldy.michael@motiva.com'],
            ['nip' => '125970060', 'name' => 'Fatikunada', 'position' => 'Operator Basis Data Non Spatial Regional 3', 'area' => 'Jakarta', 'supervisor' => 'Didiet Pradityo', 'manager' => 'M Nuramzan Iftari', 'email' => 'fatikunada@motiva.com'],
            ['nip' => '125760058', 'name' => 'Dolly Ravindra', 'position' => 'SAP PM Operator PGN', 'area' => 'Jakarta', 'supervisor' => 'Ikhsan Aditya Wardana', 'manager' => 'M Nuramzan Iftari', 'email' => 'dolly.ravindra@motiva.com'],
            ['nip' => '125890066', 'name' => 'Muhajirin', 'position' => 'SAP PM Operator SHG', 'area' => 'Jakarta', 'supervisor' => 'Ikhsan Aditya Wardana', 'manager' => 'M Nuramzan Iftari', 'email' => 'muhajirin@motiva.com'],
            ['nip' => '125890061', 'name' => 'Fikri Achmad', 'position' => 'System Analyst -All System', 'area' => 'Jakarta', 'supervisor' => 'Ikhsan Aditya Wardana', 'manager' => 'M Nuramzan Iftari', 'email' => 'fikri.achmad@motiva.com'],
            ['nip' => '125010128', 'name' => 'Andika Bagus Pambudi', 'position' => 'Programmer - AIMS MIRACLE', 'area' => 'Jakarta', 'supervisor' => 'Ikhsan Aditya Wardana', 'manager' => 'M Nuramzan Iftari', 'email' => 'andika.pambudi@motiva.com'],
            ['nip' => '125980059', 'name' => 'Dzaki Fatchur Rachman', 'position' => 'Programmer - NICO, PICO', 'area' => 'Jakarta', 'supervisor' => 'Ikhsan Aditya Wardana', 'manager' => 'M Nuramzan Iftari', 'email' => 'dzaki.rachman@motiva.com'],
            ['nip' => '125900078', 'name' => 'Saifudin Zuhri', 'position' => 'Programmer - MOC, CR7, PSSR', 'area' => 'Jakarta', 'supervisor' => 'Ikhsan Aditya Wardana', 'manager' => 'M Nuramzan Iftari', 'email' => 'saifudin.zuhri@motiva.com'],
            ['nip' => '125890068', 'name' => 'Novyan Hari Santoso', 'position' => 'Programmer - BI Dashboard SHG', 'area' => 'Jakarta', 'supervisor' => 'Ikhsan Aditya Wardana', 'manager' => 'M Nuramzan Iftari', 'email' => 'novyan.santoso@motiva.com'],
            ['nip' => '125960067', 'name' => 'Novi Dwi Astuti', 'position' => 'Programmer - BI Dasboard SH Pertamina', 'area' => 'Jakarta', 'supervisor' => 'Ikhsan Aditya Wardana', 'manager' => 'M Nuramzan Iftari', 'email' => 'novi.astuti@motiva.com'],
        ];

        // 11. Simpan 32 Pegawai beserta User akun (role: Staff)
        foreach ($rawEmployees as $data) {
            $user = User::firstOrCreate(
                ['email' => $data['email']],
                [
                    'name' => $data['name'],
                    'password' => Hash::make('password'),
                    'email_verified_at' => now(),
                ]
            );
            $user->syncRoles([$roleStaff]);

            // Tentukan atasan langsung: Supervisor jika ada, atau Manager jika kosong
            $directManagerId = ! empty($data['supervisor'])
                ? ($leaderLookup[$data['supervisor']] ?? $managerEmployee->id)
                : $managerEmployee->id;

            $pos = $positions[$data['position']] ?? $positions['Driver'];

            $employee = Employee::firstOrCreate(
                ['identity_number' => $data['nip']],
                [
                    'user_id' => $user->id,
                    'organization_id' => $org->id,
                    'department_id' => $deptJakarta->id,
                    'position_id' => $pos->id,
                    'manager_id' => $directManagerId,
                    'name' => $data['name'],
                ]
            );

            $employee->update([
                'user_id' => $user->id,
                'organization_id' => $org->id,
                'department_id' => $deptJakarta->id,
                'position_id' => $pos->id,
                'manager_id' => $directManagerId,
                'name' => $data['name'],
            ]);
        }
    }
}
