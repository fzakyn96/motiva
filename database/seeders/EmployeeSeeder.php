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
            'view_any_reimbursement', 'view_reimbursement', 'create_reimbursement', 'update_reimbursement', 'delete_reimbursement',
            'view_any_employee', 'view_employee', 'create_employee', 'update_employee', 'delete_employee',
            'approve_memo', 'approve_sppd', 'approve_reimbursement',
        ];

        foreach ($permissions as $perm) {
            Permission::firstOrCreate(['name' => $perm, 'guard_name' => 'web']);
        }

        // 2. Setup Roles
        $roleAdmin = Role::firstOrCreate(['name' => 'Admin', 'guard_name' => 'web']);
        $roleSuperadmin = Role::firstOrCreate(['name' => 'Superadmin', 'guard_name' => 'web']);
        $roleManager = Role::firstOrCreate(['name' => 'Manager', 'guard_name' => 'web']);
        $roleSupervisor = Role::firstOrCreate(['name' => 'Supervisor', 'guard_name' => 'web']);
        $roleHR = Role::firstOrCreate(['name' => 'HR', 'guard_name' => 'web']);
        $roleStaff = Role::firstOrCreate(['name' => 'Staff', 'guard_name' => 'web']);

        $roleAdmin->syncPermissions($permissions);
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
            'view_any_memo', 'view_memo', 'create_memo', 'update_memo',
            'view_any_sppd', 'view_sppd', 'create_sppd',
            'view_any_reimbursement', 'view_reimbursement', 'create_reimbursement',
            'view_any_employee', 'view_employee', 'create_employee', 'update_employee',
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

        // 4. Setup Departments (Units)
        $units = [
            'MESI' => Department::firstOrCreate(['code' => 'MESI'], ['name' => 'MESI', 'organization_id' => $org->id]),
            'Regional 1' => Department::firstOrCreate(['code' => 'REG1'], ['name' => 'Regional 1', 'organization_id' => $org->id]),
            'Regional 2' => Department::firstOrCreate(['code' => 'REG2'], ['name' => 'Regional 2', 'organization_id' => $org->id]),
            'Regional 3' => Department::firstOrCreate(['code' => 'REG3'], ['name' => 'Regional 3', 'organization_id' => $org->id]),
        ];

        // 5. Setup Positions
        $posManager = Position::firstOrCreate(['code' => 'MGR'], ['name' => 'Manager', 'level' => 1]);
        $posSupervisor = Position::firstOrCreate(['code' => 'SPV'], ['name' => 'Supervisor', 'level' => 2]);
        $posHR = Position::firstOrCreate(['code' => 'HR'], ['name' => 'HR Specialist', 'level' => 3]);
        $posStaff = Position::firstOrCreate(['code' => 'STF'], ['name' => 'Staff', 'level' => 4]);

        // 6. Data Definitions: 30 Pegawai (4 Units)
        $employeeData = [
            // ==================== UNIT 1: MESI (8 Pegawai) ====================
            [
                'unit' => 'MESI',
                'name' => 'Bambang Wijaya',
                'nip' => '198001152005011001',
                'email' => 'manager.mesi@motiva.com',
                'position' => $posManager,
                'role' => 'Manager',
                'is_manager' => true,
            ],
            [
                'unit' => 'MESI',
                'name' => 'Agus Setiawan',
                'nip' => '198503202008011002',
                'email' => 'spv.mesi@motiva.com',
                'position' => $posSupervisor,
                'role' => 'Supervisor',
                'is_manager' => false,
            ],
            [
                'unit' => 'MESI',
                'name' => 'Dewi Lestari',
                'nip' => '198807122010012003',
                'email' => 'hr.mesi@motiva.com',
                'position' => $posHR,
                'role' => 'HR',
                'is_manager' => false,
            ],
            [
                'unit' => 'MESI',
                'name' => 'Budi Santoso',
                'nip' => '199005042014011004',
                'email' => 'budi.santoso@motiva.com',
                'position' => $posStaff,
                'role' => 'Staff',
                'is_manager' => false,
            ],
            [
                'unit' => 'MESI',
                'name' => 'Siti Nurhaliza',
                'nip' => '199209182015012005',
                'email' => 'siti.nurhaliza@motiva.com',
                'position' => $posStaff,
                'role' => 'Staff',
                'is_manager' => false,
            ],
            [
                'unit' => 'MESI',
                'name' => 'Rian Hidayat',
                'nip' => '199311252016011006',
                'email' => 'rian.hidayat@motiva.com',
                'position' => $posStaff,
                'role' => 'Staff',
                'is_manager' => false,
            ],
            [
                'unit' => 'MESI',
                'name' => 'Maya Putri',
                'nip' => '199402142017012007',
                'email' => 'maya.putri@motiva.com',
                'position' => $posStaff,
                'role' => 'Staff',
                'is_manager' => false,
            ],
            [
                'unit' => 'MESI',
                'name' => 'Dimas Pratama',
                'nip' => '199508302018011008',
                'email' => 'dimas.pratama@motiva.com',
                'position' => $posStaff,
                'role' => 'Staff',
                'is_manager' => false,
            ],

            // ==================== UNIT 2: Regional 1 (8 Pegawai) ====================
            [
                'unit' => 'Regional 1',
                'name' => 'Hendra Gunawan',
                'nip' => '198104102005021009',
                'email' => 'manager.reg1@motiva.com',
                'position' => $posManager,
                'role' => 'Manager',
                'is_manager' => true,
            ],
            [
                'unit' => 'Regional 1',
                'name' => 'Eko Prasetyo',
                'nip' => '198606152009011010',
                'email' => 'spv.reg1@motiva.com',
                'position' => $posSupervisor,
                'role' => 'Supervisor',
                'is_manager' => false,
            ],
            [
                'unit' => 'Regional 1',
                'name' => 'Rini Astuti',
                'nip' => '198902282011012011',
                'email' => 'hr.reg1@motiva.com',
                'position' => $posHR,
                'role' => 'HR',
                'is_manager' => false,
            ],
            [
                'unit' => 'Regional 1',
                'name' => 'Ahmad Fauzi',
                'nip' => '199108172014021012',
                'email' => 'ahmad.fauzi@motiva.com',
                'position' => $posStaff,
                'role' => 'Staff',
                'is_manager' => false,
            ],
            [
                'unit' => 'Regional 1',
                'name' => 'Putri Anggraini',
                'nip' => '199304052016022013',
                'email' => 'putri.anggraini@motiva.com',
                'position' => $posStaff,
                'role' => 'Staff',
                'is_manager' => false,
            ],
            [
                'unit' => 'Regional 1',
                'name' => 'Rizky Ramadhan',
                'nip' => '199410122017021014',
                'email' => 'rizky.ramadhan@motiva.com',
                'position' => $posStaff,
                'role' => 'Staff',
                'is_manager' => false,
            ],
            [
                'unit' => 'Regional 1',
                'name' => 'Indah Permata',
                'nip' => '199501232018022015',
                'email' => 'indah.permata@motiva.com',
                'position' => $posStaff,
                'role' => 'Staff',
                'is_manager' => false,
            ],
            [
                'unit' => 'Regional 1',
                'name' => 'Fajar Nugraha',
                'nip' => '199607192019011016',
                'email' => 'fajar.nugraha@motiva.com',
                'position' => $posStaff,
                'role' => 'Staff',
                'is_manager' => false,
            ],

            // ==================== UNIT 3: Regional 2 (7 Pegawai) ====================
            [
                'unit' => 'Regional 2',
                'name' => 'Arif Budiman',
                'nip' => '198205122006011017',
                'email' => 'manager.reg2@motiva.com',
                'position' => $posManager,
                'role' => 'Manager',
                'is_manager' => true,
            ],
            [
                'unit' => 'Regional 2',
                'name' => 'Joko Susilo',
                'nip' => '198708212010021018',
                'email' => 'spv.reg2@motiva.com',
                'position' => $posSupervisor,
                'role' => 'Supervisor',
                'is_manager' => false,
            ],
            [
                'unit' => 'Regional 2',
                'name' => 'Ratna Sari',
                'nip' => '199003142012012019',
                'email' => 'hr.reg2@motiva.com',
                'position' => $posHR,
                'role' => 'HR',
                'is_manager' => false,
            ],
            [
                'unit' => 'Regional 2',
                'name' => 'Dian Wahyuni',
                'nip' => '199212012015022020',
                'email' => 'dian.wahyuni@motiva.com',
                'position' => $posStaff,
                'role' => 'Staff',
                'is_manager' => false,
            ],
            [
                'unit' => 'Regional 2',
                'name' => 'Bayu Saputra',
                'nip' => '199406182017011021',
                'email' => 'bayu.saputra@motiva.com',
                'position' => $posStaff,
                'role' => 'Staff',
                'is_manager' => false,
            ],
            [
                'unit' => 'Regional 2',
                'name' => 'Tri Utami',
                'nip' => '199511092018012022',
                'email' => 'tri.utami@motiva.com',
                'position' => $posStaff,
                'role' => 'Staff',
                'is_manager' => false,
            ],
            [
                'unit' => 'Regional 2',
                'name' => 'Wahyu Hidayat',
                'nip' => '199609252019021023',
                'email' => 'wahyu.hidayat@motiva.com',
                'position' => $posStaff,
                'role' => 'Staff',
                'is_manager' => false,
            ],

            // ==================== UNIT 4: Regional 3 (7 Pegawai) ====================
            [
                'unit' => 'Regional 3',
                'name' => 'Dedi Supriadi',
                'nip' => '198309142007011024',
                'email' => 'manager.reg3@motiva.com',
                'position' => $posManager,
                'role' => 'Manager',
                'is_manager' => true,
            ],
            [
                'unit' => 'Regional 3',
                'name' => 'Suryanto',
                'nip' => '198801202011021025',
                'email' => 'spv.reg3@motiva.com',
                'position' => $posSupervisor,
                'role' => 'Supervisor',
                'is_manager' => false,
            ],
            [
                'unit' => 'Regional 3',
                'name' => 'Nita Rahmawati',
                'nip' => '199105162013012026',
                'email' => 'hr.reg3@motiva.com',
                'position' => $posHR,
                'role' => 'HR',
                'is_manager' => false,
            ],
            [
                'unit' => 'Regional 3',
                'name' => 'Ilham Pratama',
                'nip' => '199307222016011027',
                'email' => 'ilham.pratama@motiva.com',
                'position' => $posStaff,
                'role' => 'Staff',
                'is_manager' => false,
            ],
            [
                'unit' => 'Regional 3',
                'name' => 'Ayu Wulandari',
                'nip' => '199503112018012028',
                'email' => 'ayu.wulandari@motiva.com',
                'position' => $posStaff,
                'role' => 'Staff',
                'is_manager' => false,
            ],
            [
                'unit' => 'Regional 3',
                'name' => 'Bagus Kurniawan',
                'nip' => '199608142019011029',
                'email' => 'bagus.kurniawan@motiva.com',
                'position' => $posStaff,
                'role' => 'Staff',
                'is_manager' => false,
            ],
            [
                'unit' => 'Regional 3',
                'name' => 'Cindy Claudia',
                'nip' => '199710052020012030',
                'email' => 'cindy.claudia@motiva.com',
                'position' => $posStaff,
                'role' => 'Staff',
                'is_manager' => false,
            ],
        ];

        // 7. Insert Managers First so we can link manager_id
        $unitManagers = [];
        foreach ($employeeData as $data) {
            if ($data['is_manager']) {
                $user = User::firstOrCreate(
                    ['email' => $data['email']],
                    [
                        'name' => $data['name'],
                        'password' => Hash::make('password'),
                        'email_verified_at' => now(),
                    ]
                );
                $user->syncRoles([$data['role']]);

                $dept = $units[$data['unit']];

                $employee = Employee::firstOrCreate(
                    ['identity_number' => $data['nip']],
                    [
                        'user_id' => $user->id,
                        'organization_id' => $org->id,
                        'department_id' => $dept->id,
                        'position_id' => $data['position']->id,
                        'manager_id' => null,
                        'name' => $data['name'],
                    ]
                );
                $employee->update([
                    'user_id' => $user->id,
                    'organization_id' => $org->id,
                    'department_id' => $dept->id,
                    'position_id' => $data['position']->id,
                    'name' => $data['name'],
                ]);

                $unitManagers[$data['unit']] = $employee;
            }
        }

        // 8. Insert Other Employees (Supervisor, HR, Staff) linked to their Unit Manager
        foreach ($employeeData as $data) {
            if (! $data['is_manager']) {
                $user = User::firstOrCreate(
                    ['email' => $data['email']],
                    [
                        'name' => $data['name'],
                        'password' => Hash::make('password'),
                        'email_verified_at' => now(),
                    ]
                );
                $user->syncRoles([$data['role']]);

                $dept = $units[$data['unit']];
                $manager = $unitManagers[$data['unit']] ?? null;

                $employee = Employee::firstOrCreate(
                    ['identity_number' => $data['nip']],
                    [
                        'user_id' => $user->id,
                        'organization_id' => $org->id,
                        'department_id' => $dept->id,
                        'position_id' => $data['position']->id,
                        'manager_id' => $manager?->id,
                        'name' => $data['name'],
                    ]
                );
                $employee->update([
                    'user_id' => $user->id,
                    'organization_id' => $org->id,
                    'department_id' => $dept->id,
                    'position_id' => $data['position']->id,
                    'manager_id' => $manager?->id,
                    'name' => $data['name'],
                ]);
            }
        }
    }
}
