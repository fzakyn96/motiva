<?php

namespace Database\Seeders;

use App\Models\Department;
use App\Models\Employee;
use App\Models\Organization;
use App\Models\Position;
use App\Models\User;
use App\Models\WorkArea;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

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

        // 2. Setup Roles (Superadmin, Division Head, Manager, Supervisor, HR, Staff) - Tidak ada user Admin
        $roleSuperadmin = Role::firstOrCreate(['name' => 'Superadmin', 'guard_name' => 'web']);
        $roleDivisionHead = Role::firstOrCreate(['name' => 'Division Head', 'guard_name' => 'web']);
        $roleManager = Role::firstOrCreate(['name' => 'Manager', 'guard_name' => 'web']);
        $roleSupervisor = Role::firstOrCreate(['name' => 'Supervisor', 'guard_name' => 'web']);
        $roleHR = Role::firstOrCreate(['name' => 'HR', 'guard_name' => 'web']);
        $roleStaff = Role::firstOrCreate(['name' => 'Staff', 'guard_name' => 'web']);

        $roleSuperadmin->syncPermissions($permissions);

        $managerPermissions = [
            'view_any_memo', 'view_memo', 'create_memo', 'update_memo', 'delete_memo',
            'view_any_sppd', 'view_sppd', 'create_sppd', 'update_sppd',
            'view_any_reimbursement', 'view_reimbursement', 'create_reimbursement', 'update_reimbursement',
            'view_any_employee', 'view_employee',
            'approve_memo', 'approve_sppd', 'approve_reimbursement',
        ];
        $roleDivisionHead->syncPermissions($managerPermissions);
        $roleManager->syncPermissions($managerPermissions);

        $roleSupervisor->syncPermissions([
            'view_any_memo', 'view_memo', 'create_memo', 'update_memo',
            'view_any_sppd', 'view_sppd', 'create_sppd', 'update_sppd',
            'view_any_reimbursement', 'view_reimbursement', 'create_reimbursement', 'update_reimbursement',
            'view_any_employee', 'view_employee',
            'approve_sppd', 'approve_reimbursement',
        ]);

        $roleHR->syncPermissions([
            'view_any_memo', 'view_memo', 'create_memo', 'update_memo', 'delete_memo',
            'view_any_sppd', 'view_sppd', 'create_sppd', 'update_sppd',
            'view_any_sppd::cost', 'view_sppd::cost', 'create_sppd::cost', 'update_sppd::cost', 'delete_sppd::cost',
            'view_any_sppd::rate::standard', 'view_sppd::rate::standard', 'create_sppd::rate::standard', 'update_sppd::rate::standard', 'delete_sppd::rate::standard',
            'view_any_reimbursement', 'view_reimbursement', 'create_reimbursement', 'update_reimbursement', 'delete_reimbursement',
            'view_any_employee', 'view_employee', 'create_employee', 'update_employee', 'delete_employee',
            'view_any_department', 'view_department', 'create_department', 'update_department', 'delete_department',
            'view_any_position', 'view_position', 'create_position', 'update_position', 'delete_position',
        ]);

        $roleStaff->syncPermissions([
            'view_any_sppd', 'view_sppd', 'update_sppd',
            'view_any_reimbursement', 'view_reimbursement', 'create_reimbursement', 'update_reimbursement', 'delete_reimbursement',
            'view_any_employee', 'view_employee',
        ]);

        $roleMap = [
            'Superadmin' => $roleSuperadmin,
            'Division Head' => $roleDivisionHead,
            'Manager' => $roleManager,
            'Supervisor' => $roleSupervisor,
            'HR' => $roleHR,
            'Staff' => $roleStaff,
        ];

        // 3. Setup Organization (PGN & Perkasa / PT Permata Karya Jasa)
        $orgPkj = Organization::updateOrCreate(
            ['code' => 'PKJ'],
            ['name' => 'PT Permata Karya Jasa']
        );
        $orgPgn = Organization::updateOrCreate(
            ['code' => 'PGN'],
            ['name' => 'PT. Perusahaan Gas Negara (Persero) Tbk.']
        );

        $orgMap = [
            'PKJ' => $orgPkj,
            'PGN' => $orgPgn,
        ];

        // 4. Setup Department (Unit Kerja: hanya MESI di bawah PT. Perusahaan Gas Negara (Persero) Tbk.)
        $deptMesi = Department::updateOrCreate(
            ['code' => 'MESI'],
            [
                'name' => 'Management Enterprise System Infrastructure Information (MESI)',
                'organization_id' => $orgPgn->id,
            ]
        );

        // 5. Setup Positions
        $positionsData = [
            ['code' => 'DIV-AIMS', 'name' => 'Division Head Asset Integrity Management System', 'level' => 0],
            ['code' => 'BDH-PKJ', 'name' => 'Business Division Head', 'level' => 1],
            ['code' => 'SR-EXP-IIS', 'name' => 'Sr. Expert I. Infrastructure Information System', 'level' => 1],
            ['code' => 'SR-EXP-GTOST', 'name' => 'Sr. Expert I, Gas Transmission Operation System and Technology', 'level' => 1],
            ['code' => 'SPV', 'name' => 'Supervisor', 'level' => 2],
            ['code' => 'HR-ADM', 'name' => 'HR/Admin', 'level' => 3],
            ['code' => 'DRV', 'name' => 'Driver', 'level' => 4],
            ['code' => 'MGR-KTR', 'name' => 'Manajer Kontrak', 'level' => 4],
            ['code' => 'SEK-KTR', 'name' => 'Sekretaris Kontrak', 'level' => 4],
            ['code' => 'DS-QA-R1', 'name' => 'Data Spatial Quality Assurance SSWJ AOSS - Regional 1', 'level' => 4],
            ['code' => 'DS-QA-R2', 'name' => 'Data Spatial Quality Assurance SSWJ AOL - Regional 2', 'level' => 4],
            ['code' => 'DS-QA-R3', 'name' => 'Data Spatial Quality Assurance SSWJ AOJBB - Regional 3', 'level' => 4],
            ['code' => 'DS-QA-SHG', 'name' => 'Data Spatial Quality Assurance SHG', 'level' => 4],
            ['code' => 'DNS-QA-R1', 'name' => 'Data Non Spatial Quality Assurance Regional 1', 'level' => 4],
            ['code' => 'DNS-QA-R2', 'name' => 'Data Non Spatial Quality Assurance Regional 2', 'level' => 4],
            ['code' => 'DNS-QA-R3', 'name' => 'Data Non Spatial Quality Assurance Regional 3', 'level' => 4],
            ['code' => 'OBD-S-R1', 'name' => 'Operator Basis Data Spatial Regional 1', 'level' => 4],
            ['code' => 'OBD-S-R2', 'name' => 'Operator Basis Data Spatial Regional 2', 'level' => 4],
            ['code' => 'OBD-S-R3', 'name' => 'Operator Basis Data Spatial Regional 3', 'level' => 4],
            ['code' => 'ODT-SHG', 'name' => 'Operator Dokumen Teknis SHG', 'level' => 4],
            ['code' => 'ODT-DIG', 'name' => 'Operator Dokumen Teknis Digital', 'level' => 4],
            ['code' => 'OBD-ABD', 'name' => 'Operator Basis Data Asbuilt Drawing', 'level' => 4],
            ['code' => 'OBD-NS-R1', 'name' => 'Operator Basis Data Non Spatial Regional 1', 'level' => 4],
            ['code' => 'OBD-NS-R2', 'name' => 'Operator Basis Data Non Spatial Regional 2', 'level' => 4],
            ['code' => 'OBD-NS-R3', 'name' => 'Operator Basis Data Non Spatial Regional 3', 'level' => 4],
            ['code' => 'SAP-PGN', 'name' => 'SAP PM Operator PGN', 'level' => 4],
            ['code' => 'SAP-SHG', 'name' => 'SAP PM Operator SHG', 'level' => 4],
            ['code' => 'SA-ALL', 'name' => 'System Analyst -All System', 'level' => 4],
            ['code' => 'PRG-AIM', 'name' => 'Programmer - AIMS MIRACLE', 'level' => 4],
            ['code' => 'PRG-NPO', 'name' => 'Programmer - NICO, PICO', 'level' => 4],
            ['code' => 'PRG-MCP', 'name' => 'Programmer - MOC, CR7, PSSR', 'level' => 4],
            ['code' => 'PRG-BI-SHG', 'name' => 'Programmer - BI Dashboard SHG', 'level' => 4],
            ['code' => 'PRG-BI-SHP', 'name' => 'Programmer - BI Dasboard SH Pertamina', 'level' => 4],
            ['code' => 'STF', 'name' => 'Staff', 'level' => 3],
            ['code' => 'NO', 'name' => 'Network Operator', 'level' => 4],
            ['code' => 'SR-EXP-GSOR-1', 'name' => 'Sr. Expert I, Gas System Operation Region I', 'level' => 4],
        ];

        $positions = [];
        foreach ($positionsData as $meta) {
            $positions[$meta['code']] = Position::updateOrCreate(
                ['code' => $meta['code']],
                ['name' => $meta['name'], 'level' => $meta['level']]
            );
        }

        // 6. Setup Master Area Kerja di bawah Unit Kerja MESI
        $workAreasData = [
            ['code' => 'JKT', 'name' => 'Jakarta', 'coordinator_nip' => 'PGN-002', 'description' => null],
            ['code' => 'BJN', 'name' => 'Bojonegara', 'coordinator_nip' => 'NO-20001', 'description' => null],
            ['code' => 'BTG', 'name' => 'Bitung', 'coordinator_nip' => 'PGN-002', 'description' => null],
            ['code' => 'MDN', 'name' => 'Medan', 'coordinator_nip' => 'PGN-005', 'description' => null],
            ['code' => 'SRB', 'name' => 'Surabaya', 'coordinator_nip' => 'PGN-002', 'description' => null],
        ];

        $workAreas = [];
        foreach ($workAreasData as $areaMeta) {
            $workAreas[$areaMeta['code']] = WorkArea::updateOrCreate(
                ['code' => $areaMeta['code']],
                [
                    'department_id' => $deptMesi->id,
                    'name' => $areaMeta['name'],
                    'description' => $areaMeta['description'],
                ]
            );
        }

        // 7. Daftar Seluruh Pegawai (Pejabat PGN, Pejabat Perkasa, Koordinator Area Kerja, dan Staf Operasional MESI)
        // Seluruh pegawai memiliki username, email, dan password default "password"
        $defaultPasswordHash = Hash::make('password');

        $employeesData = [
            // Pejabat & Koordinator PGN / Perkasa
            ['nip' => 'PGN-001', 'name' => 'Herry Rachmadi', 'org' => 'PGN', 'area' => 'JKT', 'pos' => 'DIV-AIMS', 'manager_nip' => null, 'email' => 'herry.rachmadi@pgn.co.id', 'username' => 'herry.rachmadi', 'role' => 'Division Head'],
            ['nip' => '125000005', 'name' => 'Khairul Khitam', 'org' => 'PKJ', 'area' => 'JKT', 'pos' => 'BDH-PKJ', 'manager_nip' => null, 'email' => 'khairul.khitam@pkj.co.id', 'username' => 'khairul.khitam', 'role' => 'Manager'],
            ['nip' => 'PGN-002', 'name' => 'M Nuramzan Iftari', 'org' => 'PGN', 'area' => 'JKT', 'pos' => 'SR-EXP-IIS', 'manager_nip' => 'PGN-001', 'email' => 'nuramzan.iftari@motiva.com', 'username' => 'nuramzan.iftari', 'role' => 'Manager'],
            ['nip' => 'PGN-003', 'name' => 'Ikhsan Aditya Wardana', 'org' => 'PGN', 'area' => 'JKT', 'pos' => 'SPV', 'manager_nip' => 'PGN-002', 'email' => 'ikhsan.wardana@motiva.com', 'username' => 'ikhsan.wardana', 'role' => 'Supervisor'],
            ['nip' => 'PGN-004', 'name' => 'Didiet Pradityo', 'org' => 'PGN', 'area' => 'JKT', 'pos' => 'SPV', 'manager_nip' => 'PGN-002', 'email' => 'didiet.pradityo@motiva.com', 'username' => 'didiet.pradityo', 'role' => 'Supervisor'],
            ['nip' => 'NO-20001', 'name' => 'Mahar Prasetyo', 'org' => 'PKJ', 'area' => 'BJN', 'pos' => 'SR-EXP-GTOST', 'manager_nip' => null, 'email' => 'mahar.prasetyo@motiva.com', 'username' => 'mahar.prasetyo', 'role' => 'Manager'],
            ['nip' => 'PGN-005', 'name' => 'Kartini Tetty Erawati Pandiangan', 'org' => 'PGN', 'area' => 'MDN', 'pos' => 'SR-EXP-GSOR-1', 'manager_nip' => 'PGN-001', 'email' => 'kartini.pandiangan@motiva.com', 'username' => 'kartini.pandiangan', 'role' => 'Manager'],

            // Area Kerja: Jakarta (JKT)
            ['nip' => '125770077', 'name' => 'Triyono', 'org' => 'PKJ', 'area' => 'JKT', 'pos' => 'DRV', 'manager_nip' => 'PGN-002', 'email' => 'triyono@motiva.com', 'username' => 'triyono', 'role' => 'Staff'],
            ['nip' => '126910050', 'name' => 'Sutikno', 'org' => 'PKJ', 'area' => 'JKT', 'pos' => 'DRV', 'manager_nip' => 'PGN-002', 'email' => 'sutikno@motiva.com', 'username' => 'sutikno', 'role' => 'Staff'],
            ['nip' => '125900071', 'name' => 'Panji Abyul Novian', 'org' => 'PKJ', 'area' => 'JKT', 'pos' => 'MGR-KTR', 'manager_nip' => 'PGN-003', 'email' => 'panji.novian@motiva.com', 'username' => 'panji.novian', 'role' => 'Staff'],
            ['nip' => '125970065', 'name' => 'Maria Ulfah', 'org' => 'PKJ', 'area' => 'JKT', 'pos' => 'SEK-KTR', 'manager_nip' => 'PGN-003', 'email' => 'maria.ulfah@motiva.com', 'username' => 'maria.ulfah', 'role' => 'HR'],
            ['nip' => '125980056', 'name' => 'Chairunnisa', 'org' => 'PKJ', 'area' => 'JKT', 'pos' => 'DS-QA-R1', 'manager_nip' => 'PGN-004', 'email' => 'chairunnisa@motiva.com', 'username' => 'chairunnisa', 'role' => 'Staff'],
            ['nip' => '125960076', 'name' => 'Siti Fatimah', 'org' => 'PKJ', 'area' => 'JKT', 'pos' => 'DS-QA-R2', 'manager_nip' => 'PGN-004', 'email' => 'siti.fatimah@motiva.com', 'username' => 'siti.fatimah', 'role' => 'Staff'],
            ['nip' => '125950064', 'name' => 'Lutfiana Damayanti', 'org' => 'PKJ', 'area' => 'JKT', 'pos' => 'DS-QA-R3', 'manager_nip' => 'PGN-004', 'email' => 'lutfiana.damayanti@motiva.com', 'username' => 'lutfiana.damayanti', 'role' => 'Staff'],
            ['nip' => '125920052', 'name' => 'Agung Nugraha', 'org' => 'PKJ', 'area' => 'JKT', 'pos' => 'DS-QA-SHG', 'manager_nip' => 'PGN-004', 'email' => 'agung.nugraha@motiva.com', 'username' => 'agung.nugraha', 'role' => 'Staff'],
            ['nip' => '125880075', 'name' => 'Shahriar Aditya Putra', 'org' => 'PKJ', 'area' => 'JKT', 'pos' => 'DNS-QA-R1', 'manager_nip' => 'PGN-004', 'email' => 'shahriar.putra@motiva.com', 'username' => 'shahriar.putra', 'role' => 'Staff'],
            ['nip' => '125940055', 'name' => 'Aris Dwi Yunanto', 'org' => 'PKJ', 'area' => 'JKT', 'pos' => 'DNS-QA-R2', 'manager_nip' => 'PGN-004', 'email' => 'aris.yunanto@motiva.com', 'username' => 'aris.yunanto', 'role' => 'Staff'],
            ['nip' => '125910073', 'name' => 'Rizki Sandy', 'org' => 'PKJ', 'area' => 'JKT', 'pos' => 'DNS-QA-R3', 'manager_nip' => 'PGN-004', 'email' => 'rizki.sandy@motiva.com', 'username' => 'rizki.sandy', 'role' => 'Staff'],
            ['nip' => '125990057', 'name' => 'Dewi Fitriana', 'org' => 'PKJ', 'area' => 'JKT', 'pos' => 'OBD-S-R1', 'manager_nip' => 'PGN-004', 'email' => 'dewi.fitriana@motiva.com', 'username' => 'dewi.fitriana', 'role' => 'Staff'],
            ['nip' => '125980139', 'name' => 'Mita Septiana', 'org' => 'PKJ', 'area' => 'JKT', 'pos' => 'OBD-S-R1', 'manager_nip' => 'PGN-004', 'email' => 'mita.septiana@motiva.com', 'username' => 'mita.septiana', 'role' => 'Staff'],
            ['nip' => '125970070', 'name' => 'Nuril Syahida', 'org' => 'PKJ', 'area' => 'JKT', 'pos' => 'OBD-S-R2', 'manager_nip' => 'PGN-004', 'email' => 'nuril.syahida@motiva.com', 'username' => 'nuril.syahida', 'role' => 'Staff'],
            ['nip' => '125010062', 'name' => 'Ika Novia Taryuni', 'org' => 'PKJ', 'area' => 'JKT', 'pos' => 'OBD-S-R2', 'manager_nip' => 'PGN-004', 'email' => 'ika.taryuni@motiva.com', 'username' => 'ika.taryuni', 'role' => 'Staff'],
            ['nip' => '125980126', 'name' => 'Salma Hayati Inda Robbi', 'org' => 'PKJ', 'area' => 'JKT', 'pos' => 'OBD-S-R2', 'manager_nip' => 'PGN-004', 'email' => 'salma.robbi@motiva.com', 'username' => 'salma.robbi', 'role' => 'Staff'],
            ['nip' => '125980072', 'name' => 'Risa Erfianti', 'org' => 'PKJ', 'area' => 'JKT', 'pos' => 'OBD-S-R3', 'manager_nip' => 'PGN-004', 'email' => 'risa.erfianti@motiva.com', 'username' => 'risa.erfianti', 'role' => 'Staff'],
            ['nip' => '126010139', 'name' => 'Rachma Dewi Zulkarnain', 'org' => 'PKJ', 'area' => 'JKT', 'pos' => 'OBD-S-R3', 'manager_nip' => 'PGN-004', 'email' => 'rachma.zulkarnain@motiva.com', 'username' => 'rachma.zulkarnain', 'role' => 'Staff'],
            ['nip' => '126000024', 'name' => 'Alfian Akbar Maulana', 'org' => 'PKJ', 'area' => 'JKT', 'pos' => 'ODT-SHG', 'manager_nip' => 'PGN-004', 'email' => 'alfian.maulana@motiva.com', 'username' => 'alfian.maulana', 'role' => 'Staff'],
            ['nip' => '126780025', 'name' => 'Jufraini', 'org' => 'PKJ', 'area' => 'JKT', 'pos' => 'ODT-DIG', 'manager_nip' => 'PGN-004', 'email' => 'jufraini@motiva.com', 'username' => 'jufraini', 'role' => 'Staff'],
            ['nip' => '126990002', 'name' => 'Dini Tiara', 'org' => 'PKJ', 'area' => 'JKT', 'pos' => 'OBD-ABD', 'manager_nip' => 'PGN-004', 'email' => 'dini.tiara@motiva.com', 'username' => 'dini.tiara', 'role' => 'Staff'],
            ['nip' => '125920054', 'name' => 'Arie Masyaid Lubis', 'org' => 'PKJ', 'area' => 'JKT', 'pos' => 'OBD-NS-R1', 'manager_nip' => 'PGN-004', 'email' => 'arie.lubis@motiva.com', 'username' => 'arie.lubis', 'role' => 'Staff'],
            ['nip' => '125880074', 'name' => 'Saldy Michael', 'org' => 'PKJ', 'area' => 'JKT', 'pos' => 'OBD-NS-R2', 'manager_nip' => 'PGN-004', 'email' => 'saldy.michael@motiva.com', 'username' => 'saldy.michael', 'role' => 'Staff'],
            ['nip' => '125970060', 'name' => 'Fatikunada', 'org' => 'PKJ', 'area' => 'JKT', 'pos' => 'OBD-NS-R3', 'manager_nip' => 'PGN-004', 'email' => 'fatikunada@motiva.com', 'username' => 'fatikunada', 'role' => 'Staff'],
            ['nip' => '125760058', 'name' => 'Dolly Ravindra', 'org' => 'PKJ', 'area' => 'JKT', 'pos' => 'SAP-PGN', 'manager_nip' => 'PGN-003', 'email' => 'dolly.ravindra@motiva.com', 'username' => 'dolly.ravindra', 'role' => 'Staff'],
            ['nip' => '125890066', 'name' => 'Muhajirin', 'org' => 'PKJ', 'area' => 'JKT', 'pos' => 'SAP-SHG', 'manager_nip' => 'PGN-003', 'email' => 'muhajirin@motiva.com', 'username' => 'muhajirin', 'role' => 'Staff'],
            ['nip' => '125890061', 'name' => 'Fikri Achmad', 'org' => 'PKJ', 'area' => 'JKT', 'pos' => 'SA-ALL', 'manager_nip' => 'PGN-003', 'email' => 'fikri.achmad@motiva.com', 'username' => 'fikri.achmad', 'role' => 'Staff'],
            ['nip' => '125010128', 'name' => 'Andika Bagus Pambudi', 'org' => 'PKJ', 'area' => 'JKT', 'pos' => 'PRG-AIM', 'manager_nip' => 'PGN-003', 'email' => 'andika.pambudi@motiva.com', 'username' => 'andika.pambudi', 'role' => 'Staff'],
            ['nip' => '125980059', 'name' => 'Dzaki Fatchur Rachman', 'org' => 'PKJ', 'area' => 'JKT', 'pos' => 'PRG-NPO', 'manager_nip' => 'PGN-003', 'email' => 'dzaki.rachman@motiva.com', 'username' => 'dzaki.rachman', 'role' => 'Staff'],
            ['nip' => '125900078', 'name' => 'Saifudin Zuhri', 'org' => 'PKJ', 'area' => 'JKT', 'pos' => 'PRG-MCP', 'manager_nip' => 'PGN-003', 'email' => 'saifudin.zuhri@motiva.com', 'username' => 'saifudin.zuhri', 'role' => 'Staff'],
            ['nip' => '125890068', 'name' => 'Novyan Hari Santoso', 'org' => 'PKJ', 'area' => 'JKT', 'pos' => 'PRG-BI-SHG', 'manager_nip' => 'PGN-003', 'email' => 'novyan.santoso@motiva.com', 'username' => 'novyan.santoso', 'role' => 'Staff'],
            ['nip' => '125960067', 'name' => 'Novi Dwi Astuti', 'org' => 'PKJ', 'area' => 'JKT', 'pos' => 'PRG-BI-SHP', 'manager_nip' => 'PGN-003', 'email' => 'novi.astuti@motiva.com', 'username' => 'novi.astuti', 'role' => 'Staff'],

            // Area Kerja: Bojonegara (BJN)
            ['nip' => '125960268', 'name' => 'Diah Nur Cahyani', 'org' => 'PKJ', 'area' => 'BJN', 'pos' => 'NO', 'manager_nip' => 'NO-20001', 'email' => 'diah.cahyani@motiva.com', 'username' => 'diah.cahyani', 'role' => 'Staff'],
            ['nip' => '125950269', 'name' => 'Febry Ramadhan Somantri', 'org' => 'PKJ', 'area' => 'BJN', 'pos' => 'NO', 'manager_nip' => 'NO-20001', 'email' => 'febry.somantri@motiva.com', 'username' => 'febry.somantri', 'role' => 'Staff'],
            ['nip' => '125940270', 'name' => 'Kurnia Aidi Setiawan', 'org' => 'PKJ', 'area' => 'BJN', 'pos' => 'NO', 'manager_nip' => 'NO-20001', 'email' => 'kurnia.setiawan@motiva.com', 'username' => 'kurnia.setiawan', 'role' => 'Staff'],
            ['nip' => '125910272', 'name' => 'Anggi Gustianto', 'org' => 'PKJ', 'area' => 'BJN', 'pos' => 'NO', 'manager_nip' => 'NO-20001', 'email' => 'anggi.gustianto@motiva.com', 'username' => 'anggi.gustianto', 'role' => 'Staff'],
            ['nip' => '125900274', 'name' => 'Didi Riyadi', 'org' => 'PKJ', 'area' => 'BJN', 'pos' => 'NO', 'manager_nip' => 'NO-20001', 'email' => 'didi.riyadi@motiva.com', 'username' => 'didi.riyadi', 'role' => 'Staff'],
            ['nip' => '125900276', 'name' => 'Anggit Kurniawan', 'org' => 'PKJ', 'area' => 'BJN', 'pos' => 'NO', 'manager_nip' => 'NO-20001', 'email' => 'anggit.kurniawan@motiva.com', 'username' => 'anggit.kurniawan', 'role' => 'Staff'],
            ['nip' => '125860281', 'name' => 'Ridha Umri', 'org' => 'PKJ', 'area' => 'BJN', 'pos' => 'NO', 'manager_nip' => 'NO-20001', 'email' => 'ridha.umri@motiva.com', 'username' => 'ridha.umri', 'role' => 'Staff'],
            ['nip' => '125850282', 'name' => 'Samsudin', 'org' => 'PKJ', 'area' => 'BJN', 'pos' => 'NO', 'manager_nip' => 'NO-20001', 'email' => 'samsudin@motiva.com', 'username' => 'samsudin', 'role' => 'Staff'],
            ['nip' => '125960289', 'name' => 'Yokawa Fajar Setiawan', 'org' => 'PKJ', 'area' => 'BJN', 'pos' => 'NO', 'manager_nip' => 'NO-20001', 'email' => 'yokawa.setiawan@motiva.com', 'username' => 'yokawa.setiawan', 'role' => 'Staff'],
            ['nip' => '125870295', 'name' => 'Asep Najiulloh', 'org' => 'PKJ', 'area' => 'BJN', 'pos' => 'STF', 'manager_nip' => 'NO-20001', 'email' => 'asep.najiulloh@motiva.com', 'username' => 'asep.najiulloh', 'role' => 'Staff'],
            ['nip' => '125920299', 'name' => 'Nasoro Abdah', 'org' => 'PKJ', 'area' => 'BJN', 'pos' => 'STF', 'manager_nip' => 'NO-20001', 'email' => 'nasoro.abdah@motiva.com', 'username' => 'nasoro.abdah', 'role' => 'Staff'],
            ['nip' => '125890300', 'name' => 'Denny Sukmawan', 'org' => 'PKJ', 'area' => 'BJN', 'pos' => 'STF', 'manager_nip' => 'NO-20001', 'email' => 'denny.sukmawan@motiva.com', 'username' => 'denny.sukmawan', 'role' => 'Staff'],
            ['nip' => '125810302', 'name' => 'Sigit Adhy Wibowo', 'org' => 'PKJ', 'area' => 'BJN', 'pos' => 'STF', 'manager_nip' => 'NO-20001', 'email' => 'sigit.wibowo@motiva.com', 'username' => 'sigit.wibowo', 'role' => 'Staff'],
            ['nip' => '126980030', 'name' => 'Artdiena Izdihar Darin', 'org' => 'PKJ', 'area' => 'BJN', 'pos' => 'NO', 'manager_nip' => 'NO-20001', 'email' => 'artdiena.darin@motiva.com', 'username' => 'artdiena.darin', 'role' => 'Staff'],

            // Area Kerja: Bitung (BTG)
            ['nip' => '125890278', 'name' => 'Agus Arifin', 'org' => 'PKJ', 'area' => 'BTG', 'pos' => 'NO', 'manager_nip' => null, 'email' => 'agus.arifin@motiva.com', 'username' => 'agus.arifin', 'role' => 'Staff'],
            ['nip' => '125890279', 'name' => 'Iis Ismail', 'org' => 'PKJ', 'area' => 'BTG', 'pos' => 'NO', 'manager_nip' => null, 'email' => 'iis.ismail@motiva.com', 'username' => 'iis.ismail', 'role' => 'Staff'],
            ['nip' => '125840284', 'name' => 'Dedi Irawan', 'org' => 'PKJ', 'area' => 'BTG', 'pos' => 'STF', 'manager_nip' => null, 'email' => 'dedi.irawan@motiva.com', 'username' => 'dedi.irawan', 'role' => 'Staff'],
            ['nip' => '125830285', 'name' => 'Muhammad Ruswenda', 'org' => 'PKJ', 'area' => 'BTG', 'pos' => 'NO', 'manager_nip' => null, 'email' => 'muhammad.ruswenda@motiva.com', 'username' => 'muhammad.ruswenda', 'role' => 'Staff'],
            ['nip' => '125830286', 'name' => 'Sabda Oing', 'org' => 'PKJ', 'area' => 'BTG', 'pos' => 'NO', 'manager_nip' => null, 'email' => 'sabda.oing@motiva.com', 'username' => 'sabda.oing', 'role' => 'Staff'],
            ['nip' => '125820288', 'name' => 'Asep Muhtar Maulana', 'org' => 'PKJ', 'area' => 'BTG', 'pos' => 'NO', 'manager_nip' => null, 'email' => 'asep.maulana@motiva.com', 'username' => 'asep.maulana', 'role' => 'Staff'],
            ['nip' => '125700296', 'name' => 'Basni Rusminadi', 'org' => 'PKJ', 'area' => 'BTG', 'pos' => 'STF', 'manager_nip' => null, 'email' => 'basni.rusminadi@motiva.com', 'username' => 'basni.rusminadi', 'role' => 'Staff'],
            ['nip' => '125920297', 'name' => 'Darmadi', 'org' => 'PKJ', 'area' => 'BTG', 'pos' => 'STF', 'manager_nip' => null, 'email' => 'darmadi@motiva.com', 'username' => 'darmadi', 'role' => 'Staff'],
            ['nip' => '125900301', 'name' => 'Yusuf Ramdani', 'org' => 'PKJ', 'area' => 'BTG', 'pos' => 'NO', 'manager_nip' => null, 'email' => 'yusuf.ramdani@motiva.com', 'username' => 'yusuf.ramdani', 'role' => 'Staff'],
            ['nip' => '126060004', 'name' => 'Damar Firmansyah', 'org' => 'PKJ', 'area' => 'BTG', 'pos' => 'STF', 'manager_nip' => null, 'email' => 'damar.firmansyah@motiva.com', 'username' => 'damar.firmansyah', 'role' => 'Staff'],
            ['nip' => '126850026', 'name' => 'Yudiawan Efendi', 'org' => 'PKJ', 'area' => 'BTG', 'pos' => 'STF', 'manager_nip' => null, 'email' => 'yudiawan.efendi@motiva.com', 'username' => 'yudiawan.efendi', 'role' => 'Staff'],
            ['nip' => '126060047', 'name' => 'Hafiz Ikhwan Muzakky', 'org' => 'PKJ', 'area' => 'BTG', 'pos' => 'STF', 'manager_nip' => null, 'email' => 'hafiz.muzakky@motiva.com', 'username' => 'hafiz.muzakky', 'role' => 'Staff'],
            ['nip' => '126950051', 'name' => 'Ikhwan Eka Saputra', 'org' => 'PKJ', 'area' => 'BTG', 'pos' => 'STF', 'manager_nip' => null, 'email' => 'ikhwan.saputra@motiva.com', 'username' => 'ikhwan.saputra', 'role' => 'Staff'],

            // Area Kerja: Medan (MDN)
            ['nip' => '125910273', 'name' => 'Andriansyah Ginting', 'org' => 'PKJ', 'area' => 'MDN', 'pos' => 'NO', 'manager_nip' => 'PGN-005', 'email' => 'andriansyah.ginting@motiva.com', 'username' => 'andriansyah.ginting', 'role' => 'Staff'],
            ['nip' => '125850283', 'name' => 'Windi Santoso', 'org' => 'PKJ', 'area' => 'MDN', 'pos' => 'NO', 'manager_nip' => 'PGN-005', 'email' => 'windi.santoso@motiva.com', 'username' => 'windi.santoso', 'role' => 'Staff'],
            ['nip' => '125830287', 'name' => 'Ninoy Renato Harahap', 'org' => 'PKJ', 'area' => 'MDN', 'pos' => 'NO', 'manager_nip' => 'PGN-005', 'email' => 'ninoy.harahap@motiva.com', 'username' => 'ninoy.harahap', 'role' => 'Staff'],
            ['nip' => '125040290', 'name' => 'Ezra Maulana', 'org' => 'PKJ', 'area' => 'MDN', 'pos' => 'NO', 'manager_nip' => 'PGN-005', 'email' => 'ezra.maulana@motiva.com', 'username' => 'ezra.maulana', 'role' => 'Staff'],
            ['nip' => '125710291', 'name' => 'Nya Agam', 'org' => 'PKJ', 'area' => 'MDN', 'pos' => 'STF', 'manager_nip' => 'PGN-005', 'email' => 'nya.agam@motiva.com', 'username' => 'nya.agam', 'role' => 'Staff'],
            ['nip' => '125940292', 'name' => 'Maulana Ibrahim', 'org' => 'PKJ', 'area' => 'MDN', 'pos' => 'STF', 'manager_nip' => 'PGN-005', 'email' => 'maulana.ibrahim@motiva.com', 'username' => 'maulana.ibrahim', 'role' => 'Staff'],
            ['nip' => '126000046', 'name' => 'Rizki Firdaus', 'org' => 'PKJ', 'area' => 'MDN', 'pos' => 'STF', 'manager_nip' => 'PGN-005', 'email' => 'rizki.firdaus@motiva.com', 'username' => 'rizki.firdaus', 'role' => 'Staff'],

            // Area Kerja: Surabaya (SRB)
            ['nip' => '125930271', 'name' => 'Firmansyah Choria Rizki', 'org' => 'PKJ', 'area' => 'SRB', 'pos' => 'NO', 'manager_nip' => null, 'email' => 'firmansyah.rizki@motiva.com', 'username' => 'firmansyah.rizki', 'role' => 'Staff'],
            ['nip' => '125900275', 'name' => 'Heru Widodo', 'org' => 'PKJ', 'area' => 'SRB', 'pos' => 'NO', 'manager_nip' => null, 'email' => 'heru.widodo@motiva.com', 'username' => 'heru.widodo', 'role' => 'Staff'],
            ['nip' => '125890277', 'name' => 'Muhammad Fariz Khoiruddin', 'org' => 'PKJ', 'area' => 'SRB', 'pos' => 'NO', 'manager_nip' => null, 'email' => 'muhammad.khoiruddin@motiva.com', 'username' => 'muhammad.khoiruddin', 'role' => 'Staff'],
            ['nip' => '125870280', 'name' => 'Rony Prastyo Raharjo', 'org' => 'PKJ', 'area' => 'SRB', 'pos' => 'NO', 'manager_nip' => null, 'email' => 'rony.raharjo@motiva.com', 'username' => 'rony.raharjo', 'role' => 'Staff'],
            ['nip' => '125750298', 'name' => 'Heriyanto Kamarudin', 'org' => 'PKJ', 'area' => 'SRB', 'pos' => 'STF', 'manager_nip' => null, 'email' => 'heriyanto.kamarudin@motiva.com', 'username' => 'heriyanto.kamarudin', 'role' => 'Staff'],
            ['nip' => '126020033', 'name' => 'Akbar Maulana', 'org' => 'PKJ', 'area' => 'SRB', 'pos' => 'STF', 'manager_nip' => null, 'email' => 'akbar.maulana@motiva.com', 'username' => 'akbar.maulana', 'role' => 'Staff'],
            ['nip' => '126770053', 'name' => 'Imam Yulianto', 'org' => 'PKJ', 'area' => 'SRB', 'pos' => 'STF', 'manager_nip' => null, 'email' => 'imam.yulianto@motiva.com', 'username' => 'imam.yulianto', 'role' => 'Staff'],
        ];

        $employeesByNip = [];
        foreach ($employeesData as $data) {
            $user = User::updateOrCreate(
                ['email' => $data['email']],
                [
                    'name' => $data['name'],
                    'username' => $data['username'],
                    'password' => $defaultPasswordHash,
                    'email_verified_at' => now(),
                ]
            );

            $roleName = $data['role'] ?? 'Staff';
            if (isset($roleMap[$roleName])) {
                $user->syncRoles([$roleMap[$roleName]]);
            }

            $org = $orgMap[$data['org']] ?? $orgPkj;
            $area = $workAreas[$data['area']] ?? $workAreas['JKT'];
            $pos = $positions[$data['pos']] ?? $positions['STF'];

            $employee = Employee::updateOrCreate(
                ['identity_number' => $data['nip']],
                [
                    'user_id' => $user->id,
                    'organization_id' => $org->id,
                    'department_id' => $deptMesi->id,
                    'work_area_id' => $area->id,
                    'position_id' => $pos->id,
                    'name' => $data['name'],
                ]
            );

            $employeesByNip[$data['nip']] = $employee;
        }

        // 8. Hubungkan relasi manager_id pada pegawai setelah seluruh pegawai terdaftar
        foreach ($employeesData as $data) {
            $employee = $employeesByNip[$data['nip']] ?? null;
            if (! $employee) {
                continue;
            }

            $managerId = ! empty($data['manager_nip']) && isset($employeesByNip[$data['manager_nip']])
                ? $employeesByNip[$data['manager_nip']]->id
                : null;

            if ((int) $employee->manager_id !== (int) $managerId) {
                $employee->update(['manager_id' => $managerId]);
            }
        }

        // 9. Hubungkan coordinator_employee_id pada Master Area Kerja
        foreach ($workAreasData as $areaMeta) {
            $workArea = $workAreas[$areaMeta['code']] ?? null;
            $coordinator = ! empty($areaMeta['coordinator_nip'])
                ? ($employeesByNip[$areaMeta['coordinator_nip']] ?? null)
                : null;

            if ($workArea && $coordinator && (int) $workArea->coordinator_employee_id !== (int) $coordinator->id) {
                $workArea->update(['coordinator_employee_id' => $coordinator->id]);
            }
        }

        // Bersihkan organisasi lama MOTIVA jika tidak lagi memiliki pegawai atau departemen
        Organization::where('code', 'MOTIVA')
            ->doesntHave('departments')
            ->doesntHave('employees')
            ->delete();
    }
}
