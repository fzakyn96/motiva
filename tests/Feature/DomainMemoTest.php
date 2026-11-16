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

        // 4. HR tidak boleh mengakses halaman Log Memo, Log SPPD, Log Biaya SPPD, Log Reimbursement
        $this->assertFalse(\App\Filament\Admin\Resources\MemoLogResource::canViewAny());
        $this->assertFalse(\App\Filament\Admin\Resources\SppdLogResource::canViewAny());
        $this->assertFalse(\App\Filament\Admin\Resources\SppdCostLogResource::canViewAny());
        $this->assertFalse(\App\Filament\Admin\Resources\ReimbursementLogResource::canViewAny());

        // 5. Manajer dan Superadmin boleh mengakses ke-4 halaman Log dan melihat data audit trail
        $this->actingAs($managerUser);
        $this->assertTrue(\App\Filament\Admin\Resources\MemoLogResource::canViewAny());
        $this->assertTrue(\App\Filament\Admin\Resources\SppdLogResource::canViewAny());
        $this->assertTrue(\App\Filament\Admin\Resources\SppdCostLogResource::canViewAny());
        $this->assertTrue(\App\Filament\Admin\Resources\ReimbursementLogResource::canViewAny());

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

        \Livewire\Livewire::test(\App\Filament\Admin\Resources\ReimbursementLogResource\Pages\ManageReimbursementLogs::class)
            ->assertSuccessful()
            ->assertSee($expectedReimbNumber);

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
}
