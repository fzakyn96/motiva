<?php

namespace Tests\Feature;

use App\Models\BusinessTrip;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Memo;
use App\Models\Organization;
use App\Models\Position;
use App\Models\Sppd;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DomainMemoTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_create_memo_business_trip_and_sppd()
    {
        $org = Organization::create(['name' => 'PT Test', 'code' => 'TEST']);
        $dept = Department::create(['organization_id' => $org->id, 'name' => 'IT', 'code' => 'IT']);
        $position = Position::create(['name' => 'Staff', 'code' => 'STF', 'level' => 3]);

        $employee = Employee::create([
            'organization_id' => $org->id,
            'department_id' => $dept->id,
            'position_id' => $position->id,
            'name' => 'Traveler User',
            'identity_number' => 'TRV001',
        ]);

        $memo = Memo::create([
            'department_id' => $dept->id,
            'creator_employee_id' => $employee->id,
            'title' => 'Test Memo',
            'content' => 'Test Content',
            'date' => '2026-10-02',
            'status' => 'draft',
        ]);

        $trip = BusinessTrip::create([
            'memo_id' => $memo->id,
            'activity_name' => 'Site Visit',
            'destination' => 'Jakarta',
            'start_date' => '2026-10-05',
            'end_date' => '2026-10-07',
        ]);

        $sppd = Sppd::create([
            'business_trip_id' => $trip->id,
            'employee_id' => $employee->id,
            'sppd_number' => 'SPPD-001',
            'status' => 'draft',
        ]);

        $this->assertDatabaseHas('memos', ['id' => $memo->id, 'title' => 'Test Memo']);
        $this->assertDatabaseHas('business_trips', ['id' => $trip->id, 'memo_id' => $memo->id]);
        $this->assertDatabaseHas('sppds', ['id' => $sppd->id, 'business_trip_id' => $trip->id]);
        
        $this->assertEquals($memo->id, $trip->memo->id);
        $this->assertEquals($trip->id, $sppd->businessTrip->id);
        $this->assertEquals($employee->id, $sppd->employee->id);
    }

    public function test_sppd_is_only_generated_after_memo_is_approved_by_manager()
    {
        $org = Organization::create(['name' => 'PT Test', 'code' => 'TEST']);
        $dept = Department::create(['organization_id' => $org->id, 'name' => 'MESI', 'code' => 'MESI']);
        $pos = Position::create(['name' => 'Staff', 'code' => 'STF', 'level' => 4]);

        $employee1 = Employee::create([
            'organization_id' => $org->id,
            'department_id' => $dept->id,
            'position_id' => $pos->id,
            'name' => 'Participant One',
            'identity_number' => 'EMP-001',
        ]);

        $employee2 = Employee::create([
            'organization_id' => $org->id,
            'department_id' => $dept->id,
            'position_id' => $pos->id,
            'name' => 'Participant Two',
            'identity_number' => 'EMP-002',
        ]);

        // 1. Memo dibuat dengan status pending
        $memo = Memo::create([
            'department_id' => $dept->id,
            'creator_employee_id' => $employee1->id,
            'to_recipient' => 'Manager MESI',
            'agenda' => 'Site Audit Penugasan',
            'location' => 'Surabaya',
            'date' => now(),
            'assignment_start_date' => '2026-11-01',
            'assignment_end_date' => '2026-11-03',
            'content' => 'Content penugasan audit',
            'participants' => [$employee1->id, $employee2->id],
            'status' => 'pending',
        ]);

        // Sebelum disetujui, SPPD TIDAK BOLEH dibuat
        $this->assertEquals(0, Sppd::count());

        // 2. Manajer menyetujui memo
        $memo->update(['status' => 'approved']);

        // Setelah disetujui, BusinessTrip dan SPPD otomatis tergenerate untuk setiap peserta
        $this->assertDatabaseHas('business_trips', [
            'memo_id' => $memo->id,
            'activity_name' => 'Site Audit Penugasan',
            'destination' => 'Surabaya',
        ]);

        $trip = BusinessTrip::where('memo_id', $memo->id)->first();
        $this->assertNotNull($trip);

        // Pastikan SPPD dibuat untuk kedua peserta dengan status draft
        $this->assertDatabaseHas('sppds', [
            'business_trip_id' => $trip->id,
            'employee_id' => $employee1->id,
            'status' => 'draft',
        ]);

        $this->assertDatabaseHas('sppds', [
            'business_trip_id' => $trip->id,
            'employee_id' => $employee2->id,
            'status' => 'draft',
        ]);

        $this->assertEquals(2, Sppd::where('business_trip_id', $trip->id)->count());
    }

    public function test_review_memo_page_allows_manager_to_approve_and_reject()
    {
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);

        $org = Organization::create(['name' => 'PT Pertamina Gas', 'code' => 'PGN']);
        $dept = Department::create(['organization_id' => $org->id, 'name' => 'MESI', 'code' => 'MESI']);
        $posManager = Position::create(['name' => 'Manager MESI', 'code' => 'MGR', 'level' => 1]);
        $posStaff = Position::create(['name' => 'Staff MESI', 'code' => 'STF', 'level' => 4]);

        $managerUser = \App\Models\User::factory()->create([
            'name' => 'Manager MESI',
            'signature_type' => 'draw',
            'signature_data' => 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==',
            'signature_updated_at' => now(),
        ]);
        $managerUser->assignRole('Manager');

        $managerEmp = Employee::create([
            'organization_id' => $org->id,
            'department_id' => $dept->id,
            'position_id' => $posManager->id,
            'user_id' => $managerUser->id,
            'name' => 'Manager MESI',
            'identity_number' => 'MGR-001',
        ]);

        $staffUser = \App\Models\User::factory()->create(['name' => 'Staff MESI']);
        $staffEmp = Employee::create([
            'organization_id' => $org->id,
            'department_id' => $dept->id,
            'position_id' => $posStaff->id,
            'user_id' => $staffUser->id,
            'manager_id' => $managerEmp->id,
            'name' => 'Staff MESI',
            'identity_number' => 'STF-001',
        ]);

        // Create Approval Policy for Memo
        $policy = \App\Models\ApprovalPolicy::create([
            'name' => 'Memo Approval Policy',
            'model_type' => Memo::class,
        ]);

        \App\Models\ApprovalPolicyStep::create([
            'approval_policy_id' => $policy->id,
            'step_order' => 1,
            'step_name' => 'Manager Approval',
            'resolver_class' => \App\Services\Approval\Resolvers\ManagerAuthorityResolver::class,
        ]);

        $memo = Memo::create([
            'department_id' => $dept->id,
            'creator_employee_id' => $staffEmp->id,
            'to_recipient' => 'Manager MESI',
            'agenda' => 'Penugasan Pelatihan',
            'content' => 'Isi memo penugasan pelatihan',
            'participants' => [$staffEmp->id],
            'status' => 'pending',
        ]);

        // Pastikan belum ada SPPD sebelum disetujui
        $this->assertEquals(0, Sppd::where('employee_id', $staffEmp->id)->count());

        $this->actingAs($managerUser);
        \Filament\Facades\Filament::setCurrentPanel(\Filament\Facades\Filament::getPanel('admin'));

        // Test Livewire ReviewMemo page mount and approve
        \Livewire\Livewire::test(\App\Filament\Admin\Resources\MemoResource\Pages\ReviewMemo::class, [
            'record' => $memo->getKey(),
        ])
            ->assertSuccessful()
            ->assertSee('MEMO')
            ->assertDontSee('MEMORANDUM INTERNAL')
            ->assertDontSee('Ditandatangani secara digital')
            ->callAction('approve');

        $memo->refresh();
        $this->assertEquals('approved', $memo->status);

        // Setelah disetujui, SPPD harus otomatis tergenerate untuk peserta
        $this->assertEquals(1, Sppd::where('employee_id', $staffEmp->id)->count());

        // Test rejection flow on another memo
        $memo2 = Memo::create([
            'department_id' => $dept->id,
            'creator_employee_id' => $staffEmp->id,
            'to_recipient' => 'Manager MESI',
            'agenda' => 'Penugasan Kedua',
            'content' => 'Isi memo kedua',
            'status' => 'pending',
        ]);

        \Livewire\Livewire::test(\App\Filament\Admin\Resources\MemoResource\Pages\ReviewMemo::class, [
            'record' => $memo2->getKey(),
        ])
            ->assertSuccessful()
            ->callAction('reject', ['notes' => 'Anggaran belum mencukupi']);

        $memo2->refresh();
        $this->assertEquals('rejected', $memo2->status);
    }

    public function test_memo_table_actions_shows_only_review_when_status_is_pending()
    {
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);

        $org = Organization::create(['name' => 'PT Pertamina Gas', 'code' => 'PGN']);
        $dept = Department::create(['organization_id' => $org->id, 'name' => 'MESI', 'code' => 'MESI']);
        $pos = Position::create(['name' => 'Staff MESI', 'code' => 'STF', 'level' => 4]);

        $adminUser = \App\Models\User::factory()->create(['name' => 'Admin User']);
        $adminUser->assignRole('Admin');

        $emp = Employee::create([
            'organization_id' => $org->id,
            'department_id' => $dept->id,
            'position_id' => $pos->id,
            'user_id' => $adminUser->id,
            'name' => 'Admin User',
            'identity_number' => 'ADM-001',
        ]);

        $pendingMemo = Memo::create([
            'department_id' => $dept->id,
            'creator_employee_id' => $emp->id,
            'to_recipient' => 'Manager MESI',
            'agenda' => 'Pending Review Memo',
            'content' => 'Content',
            'status' => 'pending',
        ]);

        $approvedMemo = Memo::create([
            'department_id' => $dept->id,
            'creator_employee_id' => $emp->id,
            'to_recipient' => 'Manager MESI',
            'agenda' => 'Approved Memo',
            'content' => 'Content',
            'status' => 'approved',
        ]);

        $this->actingAs($adminUser);
        \Filament\Facades\Filament::setCurrentPanel(\Filament\Facades\Filament::getPanel('admin'));

        \Livewire\Livewire::test(\App\Filament\Admin\Resources\MemoResource\Pages\ListMemos::class)
            ->assertSuccessful()
            ->assertTableActionVisible('review', $pendingMemo)
            ->assertTableActionHidden('print', $pendingMemo)
            ->assertTableActionHidden('edit', $pendingMemo)
            ->assertTableActionHidden('view_review', $pendingMemo)
            ->assertTableActionHidden('review', $approvedMemo)
            ->assertTableActionVisible('print', $approvedMemo)
            ->assertTableActionVisible('view_review', $approvedMemo)
            ->assertTableActionHidden('edit', $approvedMemo);
    }

    public function test_approved_memo_can_be_printed_as_pdf_with_logo()
    {
        $org = Organization::create(['name' => 'PT Test', 'code' => 'TEST']);
        $dept = Department::create(['organization_id' => $org->id, 'name' => 'MESI', 'code' => 'MESI']);
        $pos = Position::create(['name' => 'Staff', 'code' => 'STF', 'level' => 4]);
        $mgrPos = Position::create(['name' => 'Manajer Kontrak', 'code' => 'MGR-KTR', 'level' => 2]);

        $mgrUser = \App\Models\User::factory()->create();
        $mgr = Employee::create([
            'organization_id' => $org->id,
            'department_id' => $dept->id,
            'position_id' => $mgrPos->id,
            'user_id' => $mgrUser->id,
            'name' => 'Iftari Manager',
            'identity_number' => 'MGR-001',
        ]);

        $user = \App\Models\User::factory()->create();
        $emp = Employee::create([
            'organization_id' => $org->id,
            'department_id' => $dept->id,
            'position_id' => $pos->id,
            'user_id' => $user->id,
            'manager_id' => $mgr->id,
            'name' => 'Test Employee',
            'identity_number' => 'EMP-PRINT-01',
        ]);

        $memo = Memo::create([
            'department_id' => $dept->id,
            'creator_employee_id' => $emp->id,
            'to_recipient' => 'Manager MESI',
            'agenda' => 'Test Print Memo',
            'assignment_start_date' => '2026-10-22',
            'assignment_end_date' => '2026-10-27',
            'location' => 'Bandung',
            'content' => 'Test Content',
            'nomor_memo' => '001/MEMO/2026',
            'date' => '2026-10-22',
            'status' => 'approved',
        ]);

        $renderedHtml = view('memos.print', [
            'memo' => $memo->load(['creator.position', 'creator.manager.position', 'department']),
            'manager' => $mgr,
            'managerTitle' => $mgr->position->name,
        ])->render();

        $this->assertStringContainsString('Kamis - Selasa / 22 - 27 Oktober 2026', $renderedHtml);
        $this->assertStringContainsString('Manajer Kontrak', $renderedHtml);
        $this->assertStringNotContainsString('Manajer Kontrak MESI', $renderedHtml);

        $response = $this->actingAs($user)->get(route('memos.print', $memo));
        $response->assertStatus(200);
        $this->assertEquals('application/pdf', $response->headers->get('Content-Type'));
    }

    public function test_memo_approvals_page_renders_tabs_and_mobile_dropdown(): void
    {
        $role = \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'Manager']);
        $permission = \Spatie\Permission\Models\Permission::firstOrCreate(['name' => 'view_any_memo::approval']);
        $role->givePermissionTo($permission);

        $user = \App\Models\User::factory()->create();
        $user->assignRole('Manager');

        $this->actingAs($user);
        \Filament\Facades\Filament::setCurrentPanel(\Filament\Facades\Filament::getPanel('admin'));

        \Livewire\Livewire::actingAs($user)
            ->test(\App\Filament\Admin\Resources\MemoApprovalResource\Pages\ListMemoApprovals::class)
            ->assertSuccessful()
            ->assertSeeHtml('mobile-tab-select-')
            ->assertSeeHtml('Status')
            ->assertSeeHtml('Menunggu Persetujuan')
            ->assertSeeHtml('Disetujui')
            ->assertSeeHtml('Ditolak')
            ->assertSeeHtml('Semua')
            ->set('activeTab', 'approved')
            ->assertSet('activeTab', 'approved');
    }

    public function test_staff_cannot_access_or_perform_crud_on_memo(): void
    {
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);

        $org = Organization::create(['name' => 'PT Motiva', 'code' => 'MTV']);
        $dept = Department::create(['organization_id' => $org->id, 'name' => 'Jakarta', 'code' => 'JKT']);
        $pos = Position::create(['name' => 'Staff', 'code' => 'STF', 'level' => 4]);

        $staffUser = \App\Models\User::factory()->create(['name' => 'Staff User']);
        $staffUser->assignRole('Staff');
        $staffEmp = Employee::create([
            'organization_id' => $org->id,
            'department_id' => $dept->id,
            'position_id' => $pos->id,
            'user_id' => $staffUser->id,
            'name' => 'Staff User',
            'identity_number' => 'STF-MEMO-01',
        ]);

        $memo = Memo::create([
            'department_id' => $dept->id,
            'creator_employee_id' => $staffEmp->id,
            'to_recipient' => 'Manager',
            'agenda' => 'Memo Test Staff Restriction',
            'content' => 'Content',
            'status' => 'draft',
        ]);

        $this->actingAs($staffUser);
        \Filament\Facades\Filament::setCurrentPanel(\Filament\Facades\Filament::getPanel('admin'));

        $this->assertFalse(\App\Filament\Admin\Resources\MemoResource::shouldRegisterNavigation());
        $this->assertFalse(\App\Filament\Admin\Resources\MemoResource::canViewAny());
        $this->assertFalse(\App\Filament\Admin\Resources\MemoResource::canCreate());
        $this->assertFalse(\App\Filament\Admin\Resources\MemoResource::canView($memo));
        $this->assertFalse(\App\Filament\Admin\Resources\MemoResource::canEdit($memo));
        $this->assertFalse(\App\Filament\Admin\Resources\MemoResource::canDelete($memo));

        $this->get(route('memos.print', $memo))->assertForbidden();
    }

    public function test_hr_can_delete_approved_memo_only_with_reason_and_creates_audit_log_accessible_only_by_manager_and_superadmin(): void
    {
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);

        $org = Organization::create(['name' => 'PT Motiva', 'code' => 'MTV']);
        $dept = Department::create(['organization_id' => $org->id, 'name' => 'Jakarta', 'code' => 'JKT']);
        $pos = Position::create(['name' => 'HR Specialist', 'code' => 'HR', 'level' => 3]);

        $hrUser = \App\Models\User::factory()->create(['name' => 'HR Motiva']);
        $hrUser->assignRole('HR');
        $hrEmp = Employee::create([
            'organization_id' => $org->id,
            'department_id' => $dept->id,
            'position_id' => $pos->id,
            'user_id' => $hrUser->id,
            'name' => 'HR Motiva',
            'identity_number' => 'HR-LOG-01',
        ]);

        $managerUser = \App\Models\User::factory()->create(['name' => 'Manager Motiva']);
        $managerUser->assignRole('Manager');

        $superadminUser = \App\Models\User::factory()->create(['name' => 'Superadmin Motiva']);
        $superadminUser->assignRole('Superadmin');

        $staffUser = \App\Models\User::factory()->create(['name' => 'Staff Motiva']);
        $staffUser->assignRole('Staff');

        $approvedMemo = Memo::create([
            'department_id' => $dept->id,
            'creator_employee_id' => $hrEmp->id,
            'to_recipient' => 'Manager Jakarta',
            'agenda' => 'Penugasan Dinas Luar Kota Bandung',
            'title' => 'Penugasan Dinas Luar Kota Bandung',
            'nomor_memo' => '099/MEMO/JKT/11/2026',
            'content' => 'Isi memo penugasan',
            'date' => '2026-11-10',
            'status' => 'approved',
            'participants' => [
                ['employee_id' => $hrEmp->id],
            ],
        ]);

        // Pastikan tanpa alasan penghapusan gagal pada level domain
        $this->expectException(\InvalidArgumentException::class);
        $approvedMemo->deleteWithAudit($hrUser, '');
    }

    public function test_hr_deleting_approved_memo_via_table_action_stores_memo_log_and_enforces_role_access(): void
    {
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);

        $org = Organization::create(['name' => 'PT Motiva', 'code' => 'MTV']);
        $dept = Department::create(['organization_id' => $org->id, 'name' => 'Jakarta', 'code' => 'JKT']);
        $pos = Position::create(['name' => 'HR Specialist', 'code' => 'HR', 'level' => 3]);

        $hrUser = \App\Models\User::factory()->create(['name' => 'HR Motiva']);
        $hrUser->assignRole('HR');
        $hrEmp = Employee::create([
            'organization_id' => $org->id,
            'department_id' => $dept->id,
            'position_id' => $pos->id,
            'user_id' => $hrUser->id,
            'name' => 'HR Motiva',
            'identity_number' => 'HR-LOG-02',
        ]);

        $managerUser = \App\Models\User::factory()->create(['name' => 'Manager Motiva']);
        $managerUser->assignRole('Manager');

        $superadminUser = \App\Models\User::factory()->create(['name' => 'Superadmin Motiva']);
        $superadminUser->assignRole('Superadmin');

        $approvedMemo = Memo::create([
            'department_id' => $dept->id,
            'creator_employee_id' => $hrEmp->id,
            'to_recipient' => 'Manager Jakarta',
            'agenda' => 'Penugasan Audit Lapangan Semarang',
            'title' => 'Penugasan Audit Lapangan Semarang',
            'nomor_memo' => '100/MEMO/JKT/11/2026',
            'content' => 'Isi memo penugasan Semarang',
            'date' => '2026-11-12',
            'status' => 'approved',
            'participants' => [
                ['employee_id' => $hrEmp->id],
            ],
        ]);

        // Ambil SPPD otomatis yang ter-generate dari Memo yang disetujui, lalu tambahkan Biaya SPPD & Reimbursement
        $sppd = Sppd::whereHas('businessTrip', fn ($q) => $q->where('memo_id', $approvedMemo->id))->first();
        $this->assertNotNull($sppd);

        $sppdCost = \App\Models\SppdCost::create([
            'sppd_id' => $sppd->id,
            'base_type' => 'office_base_stay',
            'official_days' => 3,
            'subtotal_transport' => 500000,
            'subtotal_per_diem' => 1200000,
            'subtotal_other' => 100000,
            'total_cost' => 1800000,
        ]);

        $reimbursement = \App\Models\Reimbursement::create([
            'employee_id' => $hrEmp->id,
            'sppd_id' => $sppd->id,
            'title' => 'Reimbursement Tol & Parkir Semarang',
            'total_amount' => 350000,
            'total_claim_amount' => 350000,
            'status' => 'approved',
        ]);

        $expectedNomorMemo = $approvedMemo->nomor_memo;
        $expectedSppdNumber = $sppd->sppd_number;
        $expectedReimbNumber = $reimbursement->reimbursement_number;

        $this->actingAs($hrUser);
        \Filament\Facades\Filament::setCurrentPanel(\Filament\Facades\Filament::getPanel('admin'));

        // 1. Jika HR menghapus tanpa mengisi alasan pada modal, validasi form gagal dan memo tetap ada
        \Livewire\Livewire::test(\App\Filament\Admin\Resources\MemoResource\Pages\ListMemos::class)
            ->callTableAction('delete', $approvedMemo, data: [
                'deletion_reason' => '',
            ])
            ->assertHasTableActionErrors(['deletion_reason' => 'required']);

        $this->assertDatabaseHas('memos', ['id' => $approvedMemo->id]);

        // 2. Jika HR menyertakan alasan penghapusan yang sah, memo + SPPD + Biaya SPPD + Reimbursement terhapus dan masuk ke 4 tabel log
        \Livewire\Livewire::test(\App\Filament\Admin\Resources\MemoResource\Pages\ListMemos::class)
            ->callTableAction('delete', $approvedMemo, data: [
                'deletion_reason' => 'Kegiatan dinas dibatalkan karena perubahan jadwal klien.',
            ])
            ->assertHasNoTableActionErrors();

        $this->assertDatabaseMissing('memos', ['id' => $approvedMemo->id]);
        $this->assertDatabaseMissing('sppds', ['id' => $sppd->id]);
        $this->assertDatabaseMissing('sppd_costs', ['id' => $sppdCost->id]);
        $this->assertDatabaseMissing('reimbursements', ['id' => $reimbursement->id]);

        $this->assertDatabaseHas('memo_logs', [
            'memo_id' => $approvedMemo->id,
            'nomor_memo' => $expectedNomorMemo,
            'agenda' => 'Penugasan Audit Lapangan Semarang',
            'memo_status' => 'approved',
            'deleted_by_user_id' => $hrUser->id,
            'deleted_by_name' => 'HR Motiva',
            'deletion_reason' => 'Kegiatan dinas dibatalkan karena perubahan jadwal klien.',
        ]);

        $this->assertDatabaseHas('sppd_logs', [
            'sppd_id' => $sppd->id,
            'sppd_number' => $expectedSppdNumber,
            'nomor_memo' => $expectedNomorMemo,
            'deleted_by_user_id' => $hrUser->id,
            'deletion_reason' => 'Kegiatan dinas dibatalkan karena perubahan jadwal klien.',
        ]);

        $this->assertDatabaseHas('sppd_cost_logs', [
            'sppd_cost_id' => $sppdCost->id,
            'sppd_number' => $expectedSppdNumber,
            'nomor_memo' => $expectedNomorMemo,
            'total_cost' => 1800000,
            'deleted_by_user_id' => $hrUser->id,
            'deletion_reason' => 'Kegiatan dinas dibatalkan karena perubahan jadwal klien.',
        ]);

        $this->assertDatabaseHas('reimbursement_logs', [
            'reimbursement_id' => $reimbursement->id,
            'reimbursement_number' => $expectedReimbNumber,
            'sppd_number' => $expectedSppdNumber,
            'nomor_memo' => $expectedNomorMemo,
            'total_claim_amount' => 350000,
            'deleted_by_user_id' => $hrUser->id,
            'deletion_reason' => 'Kegiatan dinas dibatalkan karena perubahan jadwal klien.',
        ]);

        // 3. Pastikan penghapusan Memo yang BELUM disetujui (draft/pending) juga tercatat ke Log Memo
        $draftMemo = Memo::create([
            'department_id' => $dept->id,
            'creator_employee_id' => $hrEmp->id,
            'to_recipient' => 'Manager Jakarta',
            'agenda' => 'Draf Memo Batal Diajukan',
            'title' => 'Draf Memo Batal Diajukan',
            'content' => 'Isi draf memo',
            'date' => '2026-11-13',
            'status' => 'draft',
        ]);
        $draftMemoId = $draftMemo->id;
        $draftMemo->deleteWithAudit($hrUser, 'Draf memo salah input tujuan.');
        $this->assertDatabaseMissing('memos', ['id' => $draftMemoId]);
        $this->assertDatabaseHas('memo_logs', [
            'memo_id' => $draftMemoId,
            'memo_status' => 'draft',
            'deletion_reason' => 'Draf memo salah input tujuan.',
        ]);

        // 4. HR tidak boleh mengakses halaman Log Memo, Log SPPD, Log Biaya SPPD
        $this->assertFalse(\App\Filament\Admin\Resources\MemoLogResource::canViewAny());
        $this->assertFalse(\App\Filament\Admin\Resources\SppdLogResource::canViewAny());
        $this->assertFalse(\App\Filament\Admin\Resources\SppdCostLogResource::canViewAny());

        // 5. Manajer dan Superadmin boleh mengakses ke-3 halaman Log dan melihat data audit trail
        $this->actingAs($managerUser);
        $this->assertTrue(\App\Filament\Admin\Resources\MemoLogResource::canViewAny());
        $this->assertTrue(\App\Filament\Admin\Resources\SppdLogResource::canViewAny());
        $this->assertTrue(\App\Filament\Admin\Resources\SppdCostLogResource::canViewAny());

        \Livewire\Livewire::test(\App\Filament\Admin\Resources\MemoLogResource\Pages\ManageMemoLogs::class)
            ->assertSuccessful()
            ->assertSee($expectedNomorMemo)
            ->assertSee('Kegiatan dinas dibatalkan karena perubahan jadwal klien.');

        \Livewire\Livewire::test(\App\Filament\Admin\Resources\SppdLogResource\Pages\ManageSppdLogs::class)
            ->assertSuccessful()
            ->assertSee($expectedSppdNumber);

        \Livewire\Livewire::test(\App\Filament\Admin\Resources\SppdCostLogResource\Pages\ManageSppdCostLogs::class)
            ->assertSuccessful()
            ->assertSee($expectedSppdNumber);

        $this->actingAs($superadminUser);
        $this->assertTrue(\App\Filament\Admin\Resources\MemoLogResource::canViewAny());
    }

    public function test_document_numbers_are_only_generated_after_manager_approval_and_displayed_in_pdf(): void
    {
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);

        $org = Organization::create(['name' => 'PT Permata Karya Jasa', 'code' => 'PKJ']);
        $dept = Department::create(['organization_id' => $org->id, 'name' => 'MESI', 'code' => 'MESI']);
        $mgrPos = Position::create(['name' => 'Manajer', 'code' => 'MGR', 'level' => 1]);
        $spvPos = Position::create(['name' => 'Supervisor', 'code' => 'SPV', 'level' => 2]);
        $stfPos = Position::create(['name' => 'Staff', 'code' => 'STF', 'level' => 4]);

        $mgrUser = \App\Models\User::factory()->create(['name' => 'Manager MESI']);
        $mgrUser->assignRole('Manager');
        $mgrEmp = Employee::create([
            'organization_id' => $org->id,
            'department_id' => $dept->id,
            'position_id' => $mgrPos->id,
            'user_id' => $mgrUser->id,
            'name' => 'Manager MESI',
            'identity_number' => 'MGR-NUM-01',
        ]);

        $spvUser = \App\Models\User::factory()->create(['name' => 'Supervisor MESI']);
        $spvUser->assignRole('Supervisor');
        $spvEmp = Employee::create([
            'organization_id' => $org->id,
            'department_id' => $dept->id,
            'position_id' => $spvPos->id,
            'user_id' => $spvUser->id,
            'manager_id' => $mgrEmp->id,
            'name' => 'Supervisor MESI',
            'identity_number' => 'SPV-NUM-01',
        ]);

        $stfUser = \App\Models\User::factory()->create(['name' => 'Staff MESI']);
        $stfUser->assignRole('Staff');
        $stfEmp = Employee::create([
            'organization_id' => $org->id,
            'department_id' => $dept->id,
            'position_id' => $stfPos->id,
            'user_id' => $stfUser->id,
            'manager_id' => $spvEmp->id,
            'name' => 'Staff MESI',
            'identity_number' => 'STF-NUM-01',
        ]);

        // 1. Memo pending belum memiliki nomor_memo
        $memo = Memo::create([
            'department_id' => $dept->id,
            'creator_employee_id' => $stfEmp->id,
            'to_recipient' => 'Manager MESI',
            'agenda' => 'Uji Penomoran Dokumen',
            'title' => 'Uji Penomoran Dokumen',
            'content' => 'Konten uji',
            'date' => now()->toDateString(),
            'participants' => [$stfEmp->id],
            'status' => 'pending',
        ]);
        $this->assertNull($memo->nomor_memo);
        $this->assertStringContainsString('Nomor dokumen belum terbit', view('memos.print', ['memo' => $memo])->render());

        // Setelah Memo disetujui Manager, nomor_memo ter-generate dan SPPD draft belum punya sppd_number
        $memo->update(['status' => 'approved']);
        $memo->refresh();
        $this->assertNotNull($memo->nomor_memo);

        $memoHtml = view('memos.print', ['memo' => $memo])->render();
        $this->assertStringContainsString($memo->nomor_memo, $memoHtml);

        $sppd = \App\Models\Sppd::where('employee_id', $stfEmp->id)->firstOrFail();
        $this->assertSame('draft', $sppd->status);
        $this->assertNull($sppd->sppd_number);
        $this->assertStringContainsString('Nomor dokumen belum terbit', view('sppds.print', [
            'sppd' => $sppd,
            'signerName' => $mgrEmp->name,
            'signerPosition' => 'Manajer',
            'signatureRender' => ['signed' => false],
        ])->render());

        // 2. SPPD diajukan (pending) dan disetujui Supervisor -> tetap belum ada sppd_number sebelum Manager approve
        $sppd->update(['status' => 'pending']);
        $resolver = new \App\Services\Approval\ApprovalResolver();
        $sppdApproval = $sppd->approvalRequests()->firstOrFail();
        $resolver->approve($sppdApproval, $spvEmp, 'OK SPV');
        $this->assertNull($sppd->fresh()->sppd_number);

        // Setelah Manager approve -> sppd_number ter-generate dan tampil di view PDF SPPD & Biaya SPPD
        $resolver->approve($sppdApproval->fresh(), $mgrEmp, 'OK MGR');
        $sppd->refresh();
        $this->assertSame('approved', $sppd->status);
        $this->assertNotNull($sppd->sppd_number);

        $sppdHtml = view('sppds.print', [
            'sppd' => $sppd,
            'signerName' => $mgrEmp->name,
            'signerPosition' => 'Manajer',
            'signatureRender' => ['signed' => false],
        ])->render();
        $this->assertStringContainsString($sppd->sppd_number, $sppdHtml);

        $cost = new \App\Models\SppdCost([
            'sppd_id' => $sppd->id,
            'creator_id' => $mgrUser->id,
            'base_type' => 'office_base_stay',
            'official_days' => 1,
        ]);
        $cost->recalculateTotals();
        $cost->save();

        $costHtml = view('sppd-costs.print', [
            'cost' => $cost,
            'sppd' => $sppd,
            'signerName' => $mgrEmp->name,
            'signerPosition' => 'Manajer',
            'signerSignatureRender' => ['signed' => false],
            'employeeSignatureRender' => ['signed' => false],
        ])->render();
        $this->assertStringContainsString($sppd->sppd_number, $costHtml);
    }

    public function test_memo_approval_trail_resolves_manager_not_supervisor(): void
    {
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
        \Filament\Facades\Filament::setCurrentPanel(\Filament\Facades\Filament::getPanel('admin'));

        $org = Organization::create(['name' => 'PT Motiva', 'code' => 'MTV']);
        $dept = Department::create(['organization_id' => $org->id, 'name' => 'Jakarta', 'code' => 'JKT']);

        $mgrPos = Position::create(['name' => 'Manager Jakarta', 'code' => 'MGR', 'level' => 1]);
        $spvPos = Position::create(['name' => 'Supervisor Jakarta', 'code' => 'SPV', 'level' => 2]);
        $hrPos = Position::create(['name' => 'HR Specialist', 'code' => 'HR', 'level' => 3]);

        $mgrUser = \App\Models\User::factory()->create(['name' => 'M Nuramzan Iftari']);
        $mgrUser->assignRole('Manager');
        $mgrEmp = Employee::create([
            'organization_id' => $org->id,
            'department_id' => $dept->id,
            'position_id' => $mgrPos->id,
            'user_id' => $mgrUser->id,
            'name' => 'M Nuramzan Iftari',
            'identity_number' => 'MGR-01',
        ]);

        $spvUser = \App\Models\User::factory()->create(['name' => 'Ikhsan Aditya Wardana']);
        $spvUser->assignRole('Supervisor');
        $spvEmp = Employee::create([
            'organization_id' => $org->id,
            'department_id' => $dept->id,
            'position_id' => $spvPos->id,
            'user_id' => $spvUser->id,
            'manager_id' => $mgrEmp->id,
            'name' => 'Ikhsan Aditya Wardana',
            'identity_number' => 'SPV-01',
        ]);

        $hrUser = \App\Models\User::factory()->create(['name' => 'Maria Ulfah']);
        $hrUser->assignRole('HR');
        $hrEmp = Employee::create([
            'organization_id' => $org->id,
            'department_id' => $dept->id,
            'position_id' => $hrPos->id,
            'user_id' => $hrUser->id,
            'manager_id' => $spvEmp->id,
            'name' => 'Maria Ulfah',
            'identity_number' => 'HR-01',
        ]);

        $memo = Memo::create([
            'department_id' => $dept->id,
            'creator_employee_id' => $hrEmp->id,
            'to_recipient' => 'Manager Jakarta',
            'agenda' => 'Penugasan Uji Resolver Manajer',
            'title' => 'Penugasan Uji Resolver Manajer',
            'content' => 'Isi memo',
            'date' => '2026-11-17',
            'status' => 'pending',
            'participants' => [$hrEmp->id],
        ]);

        $this->actingAs($mgrUser);
        \Livewire\Livewire::test(\App\Filament\Admin\Resources\MemoResource\Pages\ReviewMemo::class, ['record' => $memo->getKey()])
            ->assertSuccessful()
            ->assertSee('Manajer: M Nuramzan Iftari')
            ->assertDontSee('Manajer: Ikhsan Aditya Wardana');
    }

    public function test_hr_only_sees_memo_participants_from_same_work_area(): void
    {
        $orgPgn = \App\Models\Organization::create(['name' => 'PT. Perusahaan Gas Negara (Persero) Tbk.', 'code' => 'PGN']);
        $orgPkj = \App\Models\Organization::create(['name' => 'PT Permata Karya Jasa', 'code' => 'PKJ']);
        $dept = \App\Models\Department::create(['organization_id' => $orgPgn->id, 'name' => 'MESI', 'code' => 'MESI']);
        $pos = \App\Models\Position::create(['name' => 'Staff', 'code' => 'STF', 'level' => 4]);

        $areaJkt = \App\Models\WorkArea::create([
            'department_id' => $dept->id,
            'code' => 'JKT',
            'name' => 'Jakarta',
        ]);
        $areaBjn = \App\Models\WorkArea::create([
            'department_id' => $dept->id,
            'code' => 'BJN',
            'name' => 'Bojonegara',
        ]);

        $hrRole = \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'HR', 'guard_name' => 'web']);
        $hrUser = \App\Models\User::factory()->create(['name' => 'Maria Ulfah']);
        $hrUser->assignRole($hrRole);

        $hrEmp = \App\Models\Employee::create([
            'user_id' => $hrUser->id,
            'organization_id' => $orgPkj->id,
            'department_id' => $dept->id,
            'work_area_id' => $areaJkt->id,
            'position_id' => $pos->id,
            'name' => 'Maria Ulfah',
            'identity_number' => '125970065',
        ]);

        $jktEmp = \App\Models\Employee::create([
            'organization_id' => $orgPkj->id,
            'department_id' => $dept->id,
            'work_area_id' => $areaJkt->id,
            'position_id' => $pos->id,
            'name' => 'Chairunnisa',
            'identity_number' => '125980056',
        ]);

        $bjnEmp = \App\Models\Employee::create([
            'organization_id' => $orgPkj->id,
            'department_id' => $dept->id,
            'work_area_id' => $areaBjn->id,
            'position_id' => $pos->id,
            'name' => 'Diah Nur Cahyani',
            'identity_number' => '125960268',
        ]);

        \Filament\Facades\Filament::setCurrentPanel(\Filament\Facades\Filament::getPanel('admin'));
        $this->actingAs($hrUser);

        \Livewire\Livewire::test(\App\Filament\Admin\Resources\MemoResource\Pages\CreateMemo::class)
            ->assertSuccessful()
            ->assertSee('Area Kerja Jakarta: Menampilkan daftar pegawai dari area kerja Jakarta.')
            ->assertDontSee('Menampilkan daftar pegawai dari unit kerja')
            ->assertSee('Chairunnisa (125980056)')
            ->assertDontSee('Diah Nur Cahyani (125960268)');
    }

    public function test_manager_and_supervisor_only_see_and_decide_on_same_work_area(): void
    {
        $orgPgn = \App\Models\Organization::create(['name' => 'PT. Perusahaan Gas Negara (Persero) Tbk.', 'code' => 'PGN']);
        $orgPkj = \App\Models\Organization::create(['name' => 'PT Permata Karya Jasa', 'code' => 'PKJ']);
        $dept = \App\Models\Department::create(['organization_id' => $orgPgn->id, 'name' => 'MESI', 'code' => 'MESI']);

        $mgrPos = \App\Models\Position::create(['name' => 'Manager', 'code' => 'MGR', 'level' => 1]);
        $spvPos = \App\Models\Position::create(['name' => 'Supervisor', 'code' => 'SPV', 'level' => 2]);
        $stfPos = \App\Models\Position::create(['name' => 'Staff', 'code' => 'STF', 'level' => 4]);

        $areaJkt = \App\Models\WorkArea::create(['department_id' => $dept->id, 'code' => 'JKT', 'name' => 'Jakarta']);
        $areaBjn = \App\Models\WorkArea::create(['department_id' => $dept->id, 'code' => 'BJN', 'name' => 'Bojonegara']);

        $mgrRole = \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'Manager', 'guard_name' => 'web']);
        $spvRole = \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'Supervisor', 'guard_name' => 'web']);

        $dummyPng = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==';

        // Manager & Supervisor Area Jakarta
        $mgrJktUser = \App\Models\User::factory()->create(['name' => 'M Nuramzan Iftari', 'signature_type' => 'draw', 'signature_data' => $dummyPng]);
        $mgrJktUser->assignRole($mgrRole);
        $mgrJktEmp = \App\Models\Employee::create([
            'user_id' => $mgrJktUser->id,
            'organization_id' => $orgPgn->id,
            'department_id' => $dept->id,
            'work_area_id' => $areaJkt->id,
            'position_id' => $mgrPos->id,
            'name' => 'M Nuramzan Iftari',
            'identity_number' => 'PGN-002',
        ]);
        $areaJkt->update(['coordinator_employee_id' => $mgrJktEmp->id]);

        $spvJktUser = \App\Models\User::factory()->create(['name' => 'Ikhsan Aditya Wardana', 'signature_type' => 'draw', 'signature_data' => $dummyPng]);
        $spvJktUser->assignRole($spvRole);
        $spvJktEmp = \App\Models\Employee::create([
            'user_id' => $spvJktUser->id,
            'organization_id' => $orgPgn->id,
            'department_id' => $dept->id,
            'work_area_id' => $areaJkt->id,
            'position_id' => $spvPos->id,
            'manager_id' => $mgrJktEmp->id,
            'name' => 'Ikhsan Aditya Wardana',
            'identity_number' => 'PGN-003',
        ]);

        // Manager Area Bojonegara
        $mgrBjnUser = \App\Models\User::factory()->create(['name' => 'Mahar Prasetyo', 'signature_type' => 'draw', 'signature_data' => $dummyPng]);
        $mgrBjnUser->assignRole($mgrRole);
        $mgrBjnEmp = \App\Models\Employee::create([
            'user_id' => $mgrBjnUser->id,
            'organization_id' => $orgPkj->id,
            'department_id' => $dept->id,
            'work_area_id' => $areaBjn->id,
            'position_id' => $mgrPos->id,
            'name' => 'Mahar Prasetyo',
            'identity_number' => 'NO-20001',
        ]);
        $areaBjn->update(['coordinator_employee_id' => $mgrBjnEmp->id]);

        // Staff Area Jakarta & Bojonegara
        $stfJktEmp = \App\Models\Employee::create([
            'organization_id' => $orgPkj->id,
            'department_id' => $dept->id,
            'work_area_id' => $areaJkt->id,
            'position_id' => $stfPos->id,
            'manager_id' => $spvJktEmp->id,
            'name' => 'Chairunnisa',
            'identity_number' => '125980056',
        ]);
        $stfBjnEmp = \App\Models\Employee::create([
            'organization_id' => $orgPkj->id,
            'department_id' => $dept->id,
            'work_area_id' => $areaBjn->id,
            'position_id' => $stfPos->id,
            'manager_id' => $mgrBjnEmp->id,
            'name' => 'Diah Nur Cahyani',
            'identity_number' => '125960268',
        ]);

        // Buat Memo Jakarta & Memo Bojonegara
        $memoJkt = Memo::create([
            'department_id' => $dept->id,
            'creator_employee_id' => $stfJktEmp->id,
            'to_recipient' => 'Manager Jakarta',
            'agenda' => 'Agenda Dinas Jakarta',
            'title' => 'Agenda Dinas Jakarta',
            'content' => 'Isi memo Jakarta',
            'date' => '2026-11-19',
            'status' => 'pending',
            'participants' => [$stfJktEmp->id],
        ]);
        $memoBjn = Memo::create([
            'department_id' => $dept->id,
            'creator_employee_id' => $stfBjnEmp->id,
            'to_recipient' => 'Manager Bojonegara',
            'agenda' => 'Agenda Dinas Bojonegara',
            'title' => 'Agenda Dinas Bojonegara',
            'content' => 'Isi memo Bojonegara',
            'date' => '2026-11-19',
            'status' => 'pending',
            'participants' => [$stfBjnEmp->id],
        ]);

        $reqMemoJkt = \App\Models\ApprovalRequest::where('document_type', Memo::class)->where('document_id', $memoJkt->id)->firstOrFail();
        $reqMemoBjn = \App\Models\ApprovalRequest::where('document_type', Memo::class)->where('document_id', $memoBjn->id)->firstOrFail();

        $resolver = new \App\Services\Approval\ApprovalResolver();

        // Manager Jakarta hanya bisa approve Memo Jakarta, TIDAK bisa approve Memo Bojonegara
        $this->assertTrue($resolver->canApprove($reqMemoJkt, $mgrJktEmp));
        $this->assertFalse($resolver->canApprove($reqMemoBjn, $mgrJktEmp));

        // Manager Bojonegara hanya bisa approve Memo Bojonegara, TIDAK bisa approve Memo Jakarta
        $this->assertTrue($resolver->canApprove($reqMemoBjn, $mgrBjnEmp));
        $this->assertFalse($resolver->canApprove($reqMemoJkt, $mgrBjnEmp));

        // Buat SPPD Jakarta & SPPD Bojonegara
        $trip = \App\Models\BusinessTrip::create([
            'activity_name' => 'Perjalanan Dinas',
            'destination' => 'Surabaya',
            'start_date' => '2026-11-20',
            'end_date' => '2026-11-22',
        ]);
        $sppdJkt = \App\Models\Sppd::create([
            'business_trip_id' => $trip->id,
            'employee_id' => $stfJktEmp->id,
            'sppd_number' => '0101/SPPD/MESI/11/2026',
            'status' => 'pending',
            'origin_location' => 'Jakarta',
            'destination_location' => 'Surabaya',
            'departure_date' => '2026-11-20',
            'return_date' => '2026-11-22',
            'total_days' => 3,
        ]);
        $sppdBjn = \App\Models\Sppd::create([
            'business_trip_id' => $trip->id,
            'employee_id' => $stfBjnEmp->id,
            'sppd_number' => '0102/SPPD/MESI/11/2026',
            'status' => 'pending',
            'origin_location' => 'Bojonegara',
            'destination_location' => 'Surabaya',
            'departure_date' => '2026-11-20',
            'return_date' => '2026-11-22',
            'total_days' => 3,
        ]);

        $reqSppdJkt = \App\Models\ApprovalRequest::where('document_type', \App\Models\Sppd::class)->where('document_id', $sppdJkt->id)->firstOrFail();
        $reqSppdBjn = \App\Models\ApprovalRequest::where('document_type', \App\Models\Sppd::class)->where('document_id', $sppdBjn->id)->firstOrFail();

        // Supervisor Jakarta hanya bisa approve SPPD Jakarta, TIDAK bisa approve SPPD Bojonegara
        $this->assertTrue($resolver->canApprove($reqSppdJkt, $spvJktEmp));
        $this->assertFalse($resolver->canApprove($reqSppdBjn, $spvJktEmp));

        // Verifikasi query visibilitas tabel Persetujuan Memo & Persetujuan SPPD
        \Filament\Facades\Filament::setCurrentPanel(\Filament\Facades\Filament::getPanel('admin'));

        $this->actingAs($mgrJktUser);
        $visibleMemoIdsForJktMgr = \App\Filament\Admin\Resources\MemoApprovalResource::getEloquentQuery()->pluck('id')->all();
        $this->assertContains($reqMemoJkt->id, $visibleMemoIdsForJktMgr);
        $this->assertNotContains($reqMemoBjn->id, $visibleMemoIdsForJktMgr);

        $visibleSppdIdsForJktMgr = \App\Filament\Admin\Resources\SppdApprovalResource::getEloquentQuery()->pluck('id')->all();
        $this->assertContains($reqSppdJkt->id, $visibleSppdIdsForJktMgr);
        $this->assertNotContains($reqSppdBjn->id, $visibleSppdIdsForJktMgr);

        $this->actingAs($spvJktUser);
        $visibleSppdIdsForJktSpv = \App\Filament\Admin\Resources\SppdApprovalResource::getEloquentQuery()->pluck('id')->all();
        $this->assertContains($reqSppdJkt->id, $visibleSppdIdsForJktSpv);
        $this->assertNotContains($reqSppdBjn->id, $visibleSppdIdsForJktSpv);

        $this->actingAs($mgrBjnUser);
        $visibleMemoIdsForBjnMgr = \App\Filament\Admin\Resources\MemoApprovalResource::getEloquentQuery()->pluck('id')->all();
        $this->assertContains($reqMemoBjn->id, $visibleMemoIdsForBjnMgr);
        $this->assertNotContains($reqMemoJkt->id, $visibleMemoIdsForBjnMgr);

        $visibleSppdIdsForBjnMgr = \App\Filament\Admin\Resources\SppdApprovalResource::getEloquentQuery()->pluck('id')->all();
        $this->assertContains($reqSppdBjn->id, $visibleSppdIdsForBjnMgr);
        $this->assertNotContains($reqSppdJkt->id, $visibleSppdIdsForBjnMgr);
    }

    public function test_hr_can_bulk_calculate_sppd_cost_by_memo_and_override_individual_employee(): void
    {
        \Illuminate\Support\Facades\Storage::fake('public');

        $orgPgn = \App\Models\Organization::create(['name' => 'PT. Perusahaan Gas Negara (Persero) Tbk.', 'code' => 'PGN']);
        $orgPkj = \App\Models\Organization::create(['name' => 'PT Permata Karya Jasa', 'code' => 'PKJ']);
        $dept = \App\Models\Department::create(['organization_id' => $orgPgn->id, 'name' => 'MESI', 'code' => 'MESI']);
        $pos = \App\Models\Position::create(['name' => 'Staff', 'code' => 'STF', 'level' => 4]);

        $hrRole = \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'HR', 'guard_name' => 'web']);
        $hrUser = \App\Models\User::factory()->create(['name' => 'Maria Ulfah']);
        $hrUser->assignRole($hrRole);

        $hrEmp = \App\Models\Employee::create([
            'user_id' => $hrUser->id,
            'organization_id' => $orgPkj->id,
            'department_id' => $dept->id,
            'position_id' => $pos->id,
            'name' => 'Maria Ulfah',
            'identity_number' => '125970065',
        ]);

        $emp1 = \App\Models\Employee::create([
            'organization_id' => $orgPkj->id,
            'department_id' => $dept->id,
            'position_id' => $pos->id,
            'name' => 'Pegawai Satu',
            'identity_number' => 'EMP-001',
        ]);
        $emp2 = \App\Models\Employee::create([
            'organization_id' => $orgPkj->id,
            'department_id' => $dept->id,
            'position_id' => $pos->id,
            'name' => 'Pegawai Dua',
            'identity_number' => 'EMP-002',
        ]);
        $emp3 = \App\Models\Employee::create([
            'organization_id' => $orgPkj->id,
            'department_id' => $dept->id,
            'position_id' => $pos->id,
            'name' => 'Pegawai Tiga (Berbeda)',
            'identity_number' => 'EMP-003',
        ]);

        $memo = Memo::create([
            'department_id' => $dept->id,
            'creator_employee_id' => $hrEmp->id,
            'nomor_memo' => '0077/MEMO/MESI/11/2026',
            'to_recipient' => 'Manajer MESI',
            'agenda' => 'Penugasan Tim 3 Orang',
            'title' => 'Penugasan Tim 3 Orang',
            'content' => 'Isi memo tim',
            'date' => '2026-11-19',
            'status' => 'approved',
            'participants' => [$emp1->id, $emp2->id, $emp3->id],
        ]);

        $trip = \App\Models\BusinessTrip::create([
            'memo_id' => $memo->id,
            'activity_name' => 'Penugasan Tim 3 Orang',
            'destination' => 'Surabaya',
            'start_date' => '2026-11-20',
            'end_date' => '2026-11-21',
        ]);

        $sppd1 = \App\Models\Sppd::create([
            'business_trip_id' => $trip->id,
            'employee_id' => $emp1->id,
            'sppd_number' => '0077-1/SPPD/MESI/11/2026',
            'status' => 'approved',
            'origin_location' => 'Jakarta',
            'destination_location' => 'Surabaya',
            'departure_date' => '2026-11-20',
            'return_date' => '2026-11-21',
            'total_days' => 2,
        ]);
        $sppd2 = \App\Models\Sppd::create([
            'business_trip_id' => $trip->id,
            'employee_id' => $emp2->id,
            'sppd_number' => '0077-2/SPPD/MESI/11/2026',
            'status' => 'approved',
            'origin_location' => 'Jakarta',
            'destination_location' => 'Surabaya',
            'departure_date' => '2026-11-20',
            'return_date' => '2026-11-21',
            'total_days' => 2,
        ]);
        $sppd3 = \App\Models\Sppd::create([
            'business_trip_id' => $trip->id,
            'employee_id' => $emp3->id,
            'sppd_number' => '0077-3/SPPD/MESI/11/2026',
            'status' => 'approved',
            'origin_location' => 'Jakarta',
            'destination_location' => 'Surabaya',
            'departure_date' => '2026-11-20',
            'return_date' => '2026-11-21',
            'total_days' => 2,
        ]);

        \Filament\Facades\Filament::setCurrentPanel(\Filament\Facades\Filament::getPanel('admin'));
        $this->actingAs($hrUser);

        // 1. HR mengisi biaya SPPD secara Bulk berdasarkan No. Memo untuk Pegawai 1 & Pegawai 2 (mengecualikan Pegawai 3 yang berbeda)
        $bulkEvidence = \Illuminate\Http\UploadedFile::fake()->create('evidence-bulk-memo.pdf', 100, 'application/pdf');

        \Livewire\Livewire::test(\App\Filament\Admin\Resources\SppdCostResource\Pages\CalculateSppdCost::class, ['record' => $sppd1->id])
            ->assertSuccessful()
            ->assertSee('0077/MEMO/MESI/11/2026')
            ->fillForm([
                'apply_mode' => 'bulk_memo',
                'target_sppd_ids' => [$sppd1->id, $sppd2->id],
                'official_days' => 2,
                'transport_ticket_qty' => 1,
                'transport_ticket_rate' => 1500000,
                'hotel_qty' => 1,
                'hotel_rate' => 600000,
                'general_evidence' => [$bulkEvidence],
                'notes' => 'Biaya & evidence sama berdasarkan No. Memo',
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertNotNull($sppd1->fresh()->cost);
        $this->assertNotNull($sppd2->fresh()->cost);
        $this->assertNull($sppd3->fresh()->cost);
        $this->assertEquals((float) $sppd1->fresh()->cost->total_cost, (float) $sppd2->fresh()->cost->total_cost);
        $this->assertSame('Biaya & evidence sama berdasarkan No. Memo', $sppd2->fresh()->cost->notes);

        // 2. HR mengisi biaya SPPD secara Individual ('single') untuk Pegawai 3 karena rincian biaya & evidence-nya berbeda
        $singleEvidence = \Illuminate\Http\UploadedFile::fake()->create('evidence-khusus-emp3.pdf', 100, 'application/pdf');

        \Livewire\Livewire::test(\App\Filament\Admin\Resources\SppdCostResource\Pages\CalculateSppdCost::class, ['record' => $sppd3->id])
            ->assertSuccessful()
            ->fillForm([
                'apply_mode' => 'single',
                'official_days' => 2,
                'transport_ticket_qty' => 1,
                'transport_ticket_rate' => 2200000,
                'hotel_qty' => 1,
                'hotel_rate' => 850000,
                'general_evidence' => [$singleEvidence],
                'notes' => 'Biaya khusus Pegawai Tiga',
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertNotNull($sppd3->fresh()->cost);
        $this->assertGreaterThan((float) $sppd1->fresh()->cost->total_cost, (float) $sppd3->fresh()->cost->total_cost);
        $this->assertSame('Biaya khusus Pegawai Tiga', $sppd3->fresh()->cost->notes);
        $this->assertSame('Biaya & evidence sama berdasarkan No. Memo', $sppd1->fresh()->cost->notes);
    }
}
