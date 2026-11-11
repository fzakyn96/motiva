<?php

namespace Tests\Feature;

use App\Filament\Admin\Clusters\DataPegawai;
use App\Filament\Admin\Clusters\HakAkses;
use App\Filament\Admin\Pages\Dashboard;
use App\Filament\Admin\Pages\SignatureSettingsPage;
use App\Filament\Admin\Resources\DepartmentResource;
use App\Filament\Admin\Resources\EmployeeResource;
use App\Filament\Admin\Resources\MemoApprovalResource;
use App\Filament\Admin\Resources\MemoResource;
use App\Filament\Admin\Resources\PermissionResource;
use App\Filament\Admin\Resources\PositionResource;
use App\Filament\Admin\Resources\ReimbursementApprovalResource;
use App\Filament\Admin\Resources\ReimbursementResource;
use App\Filament\Admin\Resources\RoleResource;
use App\Filament\Admin\Resources\SppdApprovalResource;
use App\Filament\Admin\Resources\SppdCostResource;
use App\Filament\Admin\Resources\SppdRateStandardResource;
use App\Filament\Admin\Resources\SppdResource;
use App\Filament\Admin\Resources\UserResource;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RoleMenuAccessControlTest extends TestCase
{
    use RefreshDatabase;

    protected User $superadminUser;
    protected User $hrUser;
    protected User $managerUser;
    protected User $supervisorUser;
    protected User $staffUser;

    protected function setUp(): void
    {
        parent::setUp();
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->superadminUser = User::factory()->create(['name' => 'Superadmin']);
        $this->superadminUser->assignRole('Superadmin');

        $this->hrUser = User::factory()->create(['name' => 'HR Staff']);
        $this->hrUser->assignRole('HR');

        $this->managerUser = User::factory()->create(['name' => 'Manager Unit']);
        $this->managerUser->assignRole('Manager');

        $this->supervisorUser = User::factory()->create(['name' => 'Supervisor Unit']);
        $this->supervisorUser->assignRole('Supervisor');

        $this->staffUser = User::factory()->create(['name' => 'Staff Traveler']);
        $this->staffUser->assignRole('Staff');
    }

    public function test_manager_and_supervisor_cannot_access_generic_memo_and_sppd_menus_but_can_access_approvals(): void
    {
        // 1. Manager check
        $this->actingAs($this->managerUser);
        $this->assertFalse(MemoResource::shouldRegisterNavigation(), 'Manager should not see Memo menu');
        $this->assertFalse(MemoResource::canViewAny(), 'Manager should not view generic memo list');
        $this->assertFalse(SppdResource::shouldRegisterNavigation(), 'Manager should not see SPPD menu');
        $this->assertFalse(SppdResource::canViewAny(), 'Manager should not view generic SPPD list');
        $this->assertFalse(ReimbursementResource::shouldRegisterNavigation(), 'Manager should not see Keuangan -> Reimbursement menu');
        $this->assertFalse(ReimbursementResource::canViewAny(), 'Manager should not view generic reimbursement list');
        $this->assertTrue(MemoApprovalResource::canViewAny(), 'Manager can access Persetujuan Memo');
        $this->assertTrue(SppdApprovalResource::canViewAny(), 'Manager can access Persetujuan SPPD');
        $this->assertTrue(ReimbursementApprovalResource::canViewAny(), 'Manager can access Persetujuan Reimbursement');

        // 2. Supervisor check
        $this->actingAs($this->supervisorUser);
        $this->assertFalse(MemoResource::shouldRegisterNavigation(), 'Supervisor should not see Memo menu');
        $this->assertFalse(MemoResource::canViewAny(), 'Supervisor should not view generic memo list');
        $this->assertFalse(SppdResource::shouldRegisterNavigation(), 'Supervisor should not see SPPD menu');
        $this->assertFalse(SppdResource::canViewAny(), 'Supervisor should not view generic SPPD list');
        $this->assertFalse(ReimbursementResource::shouldRegisterNavigation(), 'Supervisor should not see Keuangan -> Reimbursement menu');
        $this->assertFalse(ReimbursementResource::canViewAny(), 'Supervisor should not view generic reimbursement list');
        $this->assertFalse(MemoApprovalResource::canViewAny(), 'Supervisor should not access Persetujuan Memo');
        $this->assertTrue(SppdApprovalResource::canViewAny(), 'Supervisor can access Persetujuan SPPD');
        $this->assertFalse(ReimbursementApprovalResource::canViewAny(), 'Supervisor should not access Persetujuan Reimbursement');

        // 3. Staff check (Staff can create/view their own memos, sppd, reimbursement, but cannot access Persetujuan)
        $this->actingAs($this->staffUser);
        $this->assertTrue(MemoResource::shouldRegisterNavigation());
        $this->assertTrue(MemoResource::canViewAny());
        $this->assertTrue(SppdResource::shouldRegisterNavigation());
        $this->assertTrue(SppdResource::canViewAny());
        $this->assertTrue(ReimbursementResource::shouldRegisterNavigation());
        $this->assertTrue(ReimbursementResource::canViewAny());
        $this->assertFalse(MemoApprovalResource::canViewAny(), 'Staff should not access Persetujuan Memo');
        $this->assertFalse(SppdApprovalResource::canViewAny(), 'Staff should not access Persetujuan SPPD');
        $this->assertFalse(ReimbursementApprovalResource::canViewAny(), 'Staff should not access Persetujuan Reimbursement');

        // 4. HR check (HR maintains full operational access)
        $this->actingAs($this->hrUser);
        $this->assertTrue(MemoResource::shouldRegisterNavigation());
        $this->assertTrue(MemoResource::canViewAny());
        $this->assertTrue(SppdResource::shouldRegisterNavigation());
        $this->assertTrue(SppdResource::canViewAny());
        $this->assertTrue(ReimbursementResource::shouldRegisterNavigation());
        $this->assertTrue(ReimbursementResource::canViewAny());
        $this->assertTrue(MemoApprovalResource::canViewAny());
        $this->assertTrue(SppdApprovalResource::canViewAny());
        $this->assertTrue(ReimbursementApprovalResource::canViewAny());
    }

    public function test_user_management_is_only_accessible_by_hr_and_superadmin(): void
    {
        // A. Manager should NOT be able to access clusters and resources under Manajemen Pengguna
        $this->actingAs($this->managerUser);
        $this->assertFalse(DataPegawai::canAccess(), 'Manager cannot access Data Pegawai cluster');
        $this->assertFalse(HakAkses::canAccess(), 'Manager cannot access Hak Akses cluster');
        $this->assertFalse(UserResource::canViewAny());
        $this->assertFalse(EmployeeResource::canViewAny());
        $this->assertFalse(DepartmentResource::canViewAny());
        $this->assertFalse(PositionResource::canViewAny());
        $this->assertFalse(RoleResource::canViewAny());
        $this->assertFalse(PermissionResource::canViewAny());

        // B. Supervisor should NOT be able to access
        $this->actingAs($this->supervisorUser);
        $this->assertFalse(DataPegawai::canAccess());
        $this->assertFalse(HakAkses::canAccess());
        $this->assertFalse(UserResource::canViewAny());
        $this->assertFalse(EmployeeResource::canViewAny());
        $this->assertFalse(DepartmentResource::canViewAny());
        $this->assertFalse(PositionResource::canViewAny());
        $this->assertFalse(RoleResource::canViewAny());
        $this->assertFalse(PermissionResource::canViewAny());

        // C. Staff should NOT be able to access
        $this->actingAs($this->staffUser);
        $this->assertFalse(DataPegawai::canAccess());
        $this->assertFalse(HakAkses::canAccess());
        $this->assertFalse(UserResource::canViewAny());
        $this->assertFalse(EmployeeResource::canViewAny());
        $this->assertFalse(DepartmentResource::canViewAny());
        $this->assertFalse(PositionResource::canViewAny());
        $this->assertFalse(RoleResource::canViewAny());
        $this->assertFalse(PermissionResource::canViewAny());

        // D. HR CAN access Data Pegawai (Pegawai, Unit Kerja, Jabatan), but CANNOT access Hak Akses & Pengguna (User, Role, Permission)
        $this->actingAs($this->hrUser);
        $this->assertTrue(DataPegawai::canAccess(), 'HR must be able to access Data Pegawai');
        $this->assertTrue(EmployeeResource::canViewAny());
        $this->assertTrue(DepartmentResource::canViewAny());
        $this->assertTrue(PositionResource::canViewAny());

        $this->assertFalse(HakAkses::canAccess(), 'HR must NOT access Hak Akses & Pengguna');
        $this->assertFalse(UserResource::canViewAny(), 'HR must NOT access UserResource');
        $this->assertFalse(RoleResource::canViewAny(), 'HR must NOT access RoleResource');
        $this->assertFalse(PermissionResource::canViewAny(), 'HR must NOT access PermissionResource');

        // E. Superadmin CAN access all user management modules (both Data Pegawai and Hak Akses & Pengguna)
        $this->actingAs($this->superadminUser);
        $this->assertTrue(DataPegawai::canAccess());
        $this->assertTrue(HakAkses::canAccess());
        $this->assertTrue(UserResource::canViewAny());
        $this->assertTrue(EmployeeResource::canViewAny());
        $this->assertTrue(DepartmentResource::canViewAny());
        $this->assertTrue(PositionResource::canViewAny());
        $this->assertTrue(RoleResource::canViewAny());
        $this->assertTrue(PermissionResource::canViewAny());
    }

    public function test_sppd_cost_is_only_accessible_by_hr_and_superadmin(): void
    {
        // 1. Manager CANNOT access Biaya SPPD
        $this->actingAs($this->managerUser);
        $this->assertFalse(SppdCostResource::canViewAny(), 'Manager must not access Biaya SPPD');

        // 2. Supervisor CANNOT access Biaya SPPD
        $this->actingAs($this->supervisorUser);
        $this->assertFalse(SppdCostResource::canViewAny(), 'Supervisor must not access Biaya SPPD');

        // 3. Staff CANNOT access Biaya SPPD
        $this->actingAs($this->staffUser);
        $this->assertFalse(SppdCostResource::canViewAny(), 'Staff must not access Biaya SPPD');

        // 4. HR CAN access and edit Biaya SPPD & Tarif Standar SPPD
        $this->actingAs($this->hrUser);
        $this->assertTrue(SppdCostResource::canViewAny(), 'HR must be able to view Biaya SPPD');
        $this->assertTrue(SppdRateStandardResource::canViewAny(), 'HR must be able to view Tarif Standar SPPD');

        // 5. Superadmin CAN access Biaya SPPD & Tarif Standar SPPD
        $this->actingAs($this->superadminUser);
        $this->assertTrue(SppdCostResource::canViewAny(), 'Superadmin must be able to view Biaya SPPD');
        $this->assertTrue(SppdRateStandardResource::canViewAny(), 'Superadmin must be able to view Tarif Standar SPPD');

        // 6. Manager, Supervisor, Staff CANNOT access Tarif Standar SPPD
        $this->actingAs($this->managerUser);
        $this->assertFalse(SppdRateStandardResource::canViewAny(), 'Manager must not access Tarif Standar SPPD');

        $this->actingAs($this->supervisorUser);
        $this->assertFalse(SppdRateStandardResource::canViewAny(), 'Supervisor must not access Tarif Standar SPPD');

        $this->actingAs($this->staffUser);
        $this->assertFalse(SppdRateStandardResource::canViewAny(), 'Staff must not access Tarif Standar SPPD');
    }

    public function test_navigation_groups_and_sort_orders_are_properly_structured(): void
    {
        // 1. Grup Dokumen
        $this->assertSame('Dokumen', MemoResource::getNavigationGroup());
        $this->assertSame(1, MemoResource::getNavigationSort());
        $this->assertSame('Dokumen', SppdResource::getNavigationGroup());
        $this->assertSame(2, SppdResource::getNavigationSort());

        // 2. Grup Persetujuan
        $this->assertSame('Persetujuan', MemoApprovalResource::getNavigationGroup());
        $this->assertSame(1, MemoApprovalResource::getNavigationSort());
        $this->assertSame('Persetujuan', SppdApprovalResource::getNavigationGroup());
        $this->assertSame(2, SppdApprovalResource::getNavigationSort());
        $this->assertSame('Persetujuan', ReimbursementApprovalResource::getNavigationGroup());
        $this->assertSame(3, ReimbursementApprovalResource::getNavigationSort());

        // 3. Grup Keuangan
        $this->assertSame('Keuangan', ReimbursementResource::getNavigationGroup());
        $this->assertSame(1, ReimbursementResource::getNavigationSort());
        $this->assertSame('Keuangan', SppdCostResource::getNavigationGroup());
        $this->assertSame(2, SppdCostResource::getNavigationSort());
        $this->assertSame('Keuangan', SppdRateStandardResource::getNavigationGroup());
        $this->assertSame(3, SppdRateStandardResource::getNavigationSort());

        // 4. Grup Manajemen Pengguna
        $this->assertSame('Manajemen Pengguna', DataPegawai::getNavigationGroup());
        $this->assertSame(1, DataPegawai::getNavigationSort());
        $this->assertSame(1, EmployeeResource::getNavigationSort());
        $this->assertSame(2, DepartmentResource::getNavigationSort());
        $this->assertSame(3, PositionResource::getNavigationSort());

        $this->assertSame('Manajemen Pengguna', HakAkses::getNavigationGroup());
        $this->assertSame(2, HakAkses::getNavigationSort());
        $this->assertSame(1, UserResource::getNavigationSort());
        $this->assertSame(2, RoleResource::getNavigationSort());
        $this->assertSame(3, PermissionResource::getNavigationSort());

        // 5. Grup Pengaturan
        $this->assertSame('Pengaturan', SignatureSettingsPage::getNavigationGroup());
        $this->assertSame(1, SignatureSettingsPage::getNavigationSort());

        // Semua role dapat mengakses halaman Dashboard & Pengaturan Tanda Tangan
        foreach ([$this->superadminUser, $this->hrUser, $this->managerUser, $this->supervisorUser, $this->staffUser] as $user) {
            $this->actingAs($user);
            $this->assertTrue(Dashboard::canAccess());
            $this->assertTrue(SignatureSettingsPage::canAccess());
        }

        // Badge navigasi menampilkan jumlah data pada menu yang memiliki record
        $this->actingAs($this->superadminUser);
        $this->assertSame((string) User::count(), UserResource::getNavigationBadge());
        $this->assertSame((string) User::count(), HakAkses::getNavigationBadge());
        $this->assertNotNull(RoleResource::getNavigationBadge());
        $this->assertNotNull(PermissionResource::getNavigationBadge());
    }
}
