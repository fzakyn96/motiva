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
}
