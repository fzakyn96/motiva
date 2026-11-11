<?php

namespace Tests\Feature;

use App\Filament\Admin\Resources\SppdResource\Pages\EditSppd;
use App\Models\BusinessTrip;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Memo;
use App\Models\Organization;
use App\Models\Position;
use App\Models\Sppd;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class DomainSppdTest extends TestCase
{
    use RefreshDatabase;

    public function test_assigned_employee_can_fill_and_submit_sppd_form_with_automatic_status_transition()
    {
        $org = Organization::create(['name' => 'PT Permata Karya Jasa', 'code' => 'PKJ']);
        $dept = Department::create(['organization_id' => $org->id, 'name' => 'MESI', 'code' => 'MESI']);
        $staffPos = Position::create(['name' => 'QA SPASIAL OMR 2', 'code' => 'QA-SP', 'level' => 4]);
        $mgrPos = Position::create(['name' => 'Manajer Area', 'code' => 'MGR', 'level' => 1]);

        Role::firstOrCreate(['name' => 'Staff', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'Manager', 'guard_name' => 'web']);

        $mgrUser = User::factory()->create();
        $mgrUser->assignRole('Manager');
        $mgr = Employee::create([
            'organization_id' => $org->id,
            'department_id' => $dept->id,
            'position_id' => $mgrPos->id,
            'user_id' => $mgrUser->id,
            'name' => 'M Nuramzan Iftari',
            'identity_number' => 'MGR-001',
        ]);

        $staffUser = User::factory()->create();
        $staffUser->assignRole('Staff');
        $staff = Employee::create([
            'organization_id' => $org->id,
            'department_id' => $dept->id,
            'position_id' => $staffPos->id,
            'user_id' => $staffUser->id,
            'manager_id' => $mgr->id,
            'name' => 'Chairunnisa',
            'identity_number' => '125980056',
            'area' => 'Jakarta',
        ]);

        $memo = Memo::create([
            'department_id' => $dept->id,
            'creator_employee_id' => $mgr->id,
            'to_recipient' => 'Staff Lapangan',
            'agenda' => 'Singkronisasi Data Aset DIGIO - I NICO',
            'location' => 'PGN Distribution Pipeline - Area Operasi Lampung',
            'content' => 'Penugasan SPPD Lapangan',
            'nomor_memo' => '001/MEMO/MESI/10/2026',
            'date' => '2026-04-01',
            'status' => 'approved',
        ]);

        $trip = BusinessTrip::create([
            'memo_id' => $memo->id,
            'activity_name' => 'Singkronisasi Data Aset DIGIO - I NICO',
            'destination' => 'Lampung',
            'start_date' => '2026-04-06',
            'end_date' => '2026-04-10',
        ]);

        $sppd = Sppd::create([
            'business_trip_id' => $trip->id,
            'employee_id' => $staff->id,
            'sppd_number' => '0001/SPPD/MESI/04/2026',
            'status' => 'draft',
        ]);

        \Filament\Facades\Filament::setCurrentPanel(\Filament\Facades\Filament::getPanel('admin'));

        // Staff opens edit page: verify auto-filled fields, back action, and hidden print button
        $test = Livewire::actingAs($staffUser)
            ->test(EditSppd::class, ['record' => $sppd->getKey()])
            ->assertActionExists('back')
            ->assertActionHidden('print')
            ->assertFormSet([
                'origin_location' => 'Jakarta',
                'destination_location' => 'PGN Distribution Pipeline - Area Operasi Lampung',
                'assignment_purpose' => 'Singkronisasi Data Aset DIGIO - I NICO',
                'departure_date' => fn ($state) => str_starts_with($state, '2026-04-06'),
                'return_date' => fn ($state) => str_starts_with($state, '2026-04-10'),
                'total_days' => 5,
            ])
            ->fillForm([
                'travel_grade' => 'Golongan II',
                'departure_time' => '08:00',
                'return_time' => '17:00',
                'travel_category' => 'Dalam Negeri',
                'general_travel_type' => 'Kunjungan Dinas Umum/Rapat Dinas/Dinas Survei *)',
                'special_travel_type' => '≥ 80km dan menginap',
                'transport_departure' => 'Sendiri',
                'transport_arrival' => 'Kendaraan Dinas/Operasional',
                'transportation_mode' => 'KA',
                'destination_transportation' => ['Penugasan = Penginapan', 'Kendaraan Dinas/Operasional'],
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $sppd->refresh();

        // Status should automatically transition from draft to pending
        $this->assertEquals('pending', $sppd->status);
        $this->assertEquals('Golongan II', $sppd->travel_grade);
        $this->assertEquals('2026-04-06', $sppd->departure_date->format('Y-m-d'));
        $this->assertEquals('2026-04-10', $sppd->return_date->format('Y-m-d'));
        $this->assertEquals(5, $sppd->total_days);
        $this->assertEquals('KA', $sppd->transportation_mode);
        $this->assertContains('Penugasan = Penginapan', $sppd->destination_transportation);

        // Now print action should be visible since status is pending
        Livewire::actingAs($staffUser)
            ->test(EditSppd::class, ['record' => $sppd->getKey()])
            ->assertActionVisible('print');
    }

    public function test_sppd_can_be_printed_as_pdf_with_pkj_logo_and_all_14_fields()
    {
        $org = Organization::create(['name' => 'PT Permata Karya Jasa', 'code' => 'PKJ']);
        $dept = Department::create(['organization_id' => $org->id, 'name' => 'MESI', 'code' => 'MESI']);
        $staffPos = Position::create(['name' => 'QA SPASIAL OMR 2', 'code' => 'QA-SP', 'level' => 4]);
        $mgrPos = Position::create(['name' => 'Manajer Area', 'code' => 'MGR', 'level' => 1]);

        $mgrUser = User::factory()->create();
        $mgr = Employee::create([
            'organization_id' => $org->id,
            'department_id' => $dept->id,
            'position_id' => $mgrPos->id,
            'user_id' => $mgrUser->id,
            'name' => 'M Nuramzan Iftari',
            'identity_number' => 'MGR-001',
        ]);

        $staffUser = User::factory()->create();
        $staff = Employee::create([
            'organization_id' => $org->id,
            'department_id' => $dept->id,
            'position_id' => $staffPos->id,
            'user_id' => $staffUser->id,
            'manager_id' => $mgr->id,
            'name' => 'Chairunnisa',
            'identity_number' => '125980056',
            'area' => 'Jakarta',
        ]);

        $memo = Memo::create([
            'department_id' => $dept->id,
            'creator_employee_id' => $mgr->id,
            'to_recipient' => 'Staff',
            'agenda' => 'Singkronisasi Data Aset DIGIO - I NICO',
            'location' => 'PGN Distribution Pipeline - Area Operasi Lampung',
            'content' => 'Penugasan SPPD Lapangan',
            'nomor_memo' => '001/MEMO/MESI/10/2026',
            'date' => '2026-04-01',
            'status' => 'approved',
        ]);

        $trip = BusinessTrip::create([
            'memo_id' => $memo->id,
            'activity_name' => 'Singkronisasi Data Aset DIGIO - I NICO',
            'destination' => 'Lampung',
            'start_date' => '2026-04-06',
            'end_date' => '2026-04-10',
        ]);

        $sppd = Sppd::create([
            'business_trip_id' => $trip->id,
            'employee_id' => $staff->id,
            'sppd_number' => '0001/SPPD/MESI/04/2026',
            'status' => 'approved',
            'travel_grade' => 'Golongan II',
            'departure_date' => '2026-04-06',
            'departure_time' => '08:00',
            'return_date' => '2026-04-10',
            'return_time' => '17:00',
            'total_days' => 5,
            'origin_location' => 'Jakarta',
            'destination_location' => 'PGN Distribution Pipeline - Area Operasi Lampung',
            'assignment_purpose' => 'Singkronisasi Data Aset DIGIO - I NICO',
            'travel_category' => 'Dalam Negeri',
            'general_travel_type' => 'Kunjungan Dinas Umum/Rapat Dinas/Dinas Survei *)',
            'special_travel_type' => '≥ 80km dan menginap',
            'transport_departure' => 'Sendiri',
            'transport_arrival' => 'Kendaraan Dinas/Operasional',
            'transportation_mode' => 'KA',
            'destination_transportation' => ['Penugasan = Penginapan', 'Kendaraan Dinas/Operasional'],
        ]);

        // Test Blade rendering directly
        $renderedHtml = view('sppds.print', [
            'sppd' => $sppd->load(['employee.position', 'employee.department', 'businessTrip.memo.creator.manager.position']),
            'signer' => $mgr,
            'signerName' => $mgr->name,
            'signerPosition' => $mgr->position->name,
        ])->render();

        $this->assertStringContainsString('FORMULIR PERINTAH PERJALANAN DINAS', $renderedHtml);
        $this->assertStringContainsString('PEKERJA PENEMPATAN PT PERMATA KARYA JASA', $renderedHtml);
        $this->assertStringContainsString('Chairunnisa', $renderedHtml);
        $this->assertStringContainsString('QA SPASIAL OMR 2', $renderedHtml);
        $this->assertStringContainsString('PGN Distribution Pipeline - Area Operasi Lampung', $renderedHtml);
        $this->assertStringContainsString('Singkronisasi Data Aset DIGIO - I NICO', $renderedHtml);
        $this->assertStringContainsString('M Nuramzan Iftari', $renderedHtml);
        $this->assertStringContainsString('FM-BS-01.00', $renderedHtml);

        // Test HTTP stream endpoint
        $response = $this->actingAs($staffUser)->get(route('sppds.print', $sppd));
        $response->assertStatus(200);
        $this->assertEquals('application/pdf', $response->headers->get('Content-Type'));
    }

    public function test_supervisor_can_approve_sppd_and_manager_sees_supervisor_approval_and_performs_final_approval()
    {
        $org = Organization::create(['name' => 'PT Permata Karya Jasa', 'code' => 'PKJ']);
        $dept = Department::create(['organization_id' => $org->id, 'name' => 'MESI', 'code' => 'MESI']);
        $mgrPos = Position::create(['name' => 'Manajer Area', 'code' => 'MGR', 'level' => 1]);
        $spvPos = Position::create(['name' => 'Supervisor Lapangan', 'code' => 'SPV', 'level' => 2]);
        $staffPos = Position::create(['name' => 'Staff Lapangan', 'code' => 'STF', 'level' => 4]);

        Role::firstOrCreate(['name' => 'Manager', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'Supervisor', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'Staff', 'guard_name' => 'web']);

        $mgrUser = User::factory()->create([
            'name' => 'M Nuramzan Iftari',
            'signature_type' => 'draw',
            'signature_data' => 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==',
            'signature_updated_at' => now(),
        ]);
        $mgrUser->assignRole('Manager');
        $mgr = Employee::create([
            'organization_id' => $org->id,
            'department_id' => $dept->id,
            'position_id' => $mgrPos->id,
            'user_id' => $mgrUser->id,
            'name' => 'M Nuramzan Iftari',
            'identity_number' => 'MGR-001',
        ]);

        $spvUser = User::factory()->create([
            'name' => 'Ikhsan Aditya Wardana',
            'signature_type' => 'draw',
            'signature_data' => 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==',
            'signature_updated_at' => now(),
        ]);
        $spvUser->assignRole('Supervisor');
        $spv = Employee::create([
            'organization_id' => $org->id,
            'department_id' => $dept->id,
            'position_id' => $spvPos->id,
            'user_id' => $spvUser->id,
            'manager_id' => $mgr->id,
            'name' => 'Ikhsan Aditya Wardana',
            'identity_number' => 'SPV-001',
        ]);

        $staffUser = User::factory()->create(['name' => 'Chairunnisa']);
        $staffUser->assignRole('Staff');
        $staff = Employee::create([
            'organization_id' => $org->id,
            'department_id' => $dept->id,
            'position_id' => $staffPos->id,
            'user_id' => $staffUser->id,
            'manager_id' => $spv->id,
            'name' => 'Chairunnisa',
            'identity_number' => '125980056',
        ]);

        $trip = BusinessTrip::create([
            'activity_name' => 'Singkronisasi Data Aset DIGIO - I NICO',
            'destination' => 'Lampung',
            'start_date' => '2026-10-25',
            'end_date' => '2026-10-30',
        ]);

        // Buat SPPD langsung dengan status pending (diajukan oleh pegawai)
        $sppd = Sppd::create([
            'business_trip_id' => $trip->id,
            'employee_id' => $staff->id,
            'sppd_number' => '0001/SPPD/MESI/10/2026',
            'status' => 'pending',
            'origin_location' => 'Jakarta',
            'destination_location' => 'Lampung',
            'assignment_purpose' => 'Singkronisasi Data Aset DIGIO - I NICO',
            'departure_date' => '2026-10-25',
            'return_date' => '2026-10-30',
            'total_days' => 6,
        ]);

        // 1. Verifikasi ApprovalRequest otomatis dibuat dengan 2 langkah
        $approvalRequest = \App\Models\ApprovalRequest::with('steps.policyStep')
            ->where('document_type', Sppd::class)
            ->where('document_id', $sppd->id)
            ->first();

        $this->assertNotNull($approvalRequest);
        $this->assertEquals('pending', $approvalRequest->status);
        $this->assertCount(2, $approvalRequest->steps);

        $step1 = $approvalRequest->steps->first(fn ($s) => (int) $s->policyStep?->step_order === 1);
        $step2 = $approvalRequest->steps->first(fn ($s) => (int) $s->policyStep?->step_order === 2);

        $this->assertNotNull($step1);
        $this->assertNotNull($step2);
        $this->assertEquals('pending', $step1->status);
        $this->assertEquals('pending', $step2->status);

        // 2. Tahap 1: Verifikasi Otoritas
        $resolver = new \App\Services\Approval\ApprovalResolver();

        // Supervisor BERWENANG di Step 1
        $this->assertTrue($resolver->canApprove($approvalRequest, $spv));

        // Manajer BELUM BISA menyetujui Step 1 (karena resolver Step 1 adalah SupervisorAuthorityResolver)
        $this->assertFalse($resolver->canApprove($approvalRequest, $mgr));

        // 3. Supervisor Menyetujui Step 1
        $resolver->approve($approvalRequest, $spv, 'Disetujui oleh Supervisor Ikhsan - Tugas valid');

        $approvalRequest->refresh();
        $sppd->refresh();
        $step1->refresh();
        $step2->refresh();

        // Verifikasi Step 1 Approved, tapi status SPPD dan ApprovalRequest masih PENDING
        $this->assertEquals('approved', $step1->status);
        $this->assertEquals($spv->id, $step1->approver_employee_id);
        $this->assertEquals('Disetujui oleh Supervisor Ikhsan - Tugas valid', $step1->notes);
        $this->assertEquals('pending', $approvalRequest->status);
        $this->assertEquals('pending', $sppd->status);
        $this->assertEquals('Menunggu Manajer (Disetujui SPV)', $sppd->approval_stage_label);

        // 4. Manajer melihat catatan Supervisor dan sekarang giliran Manajer
        $currentStep = $approvalRequest->currentStep();
        $this->assertNotNull($currentStep);
        $this->assertEquals(2, $currentStep->policyStep->step_order);

        // Supervisor sudah tidak bisa menyetujui Step 2
        $this->assertFalse($resolver->canApprove($approvalRequest, $spv));

        // Manajer sekarang BERWENANG menyetujui Step 2
        $this->assertTrue($resolver->canApprove($approvalRequest, $mgr));

        // 5. Manajer Melakukan Persetujuan Terakhir (Final Approval)
        $resolver->approve($approvalRequest, $mgr, 'Disetujui tingkat akhir oleh Manajer');

        $approvalRequest->refresh();
        $sppd->refresh();
        $step2->refresh();

        // Verifikasi Step 2 Approved, ApprovalRequest Approved, dan SPPD resmi APPROVED
        $this->assertEquals('approved', $step2->status);
        $this->assertEquals($mgr->id, $step2->approver_employee_id);
        $this->assertEquals('Disetujui tingkat akhir oleh Manajer', $step2->notes);
        $this->assertEquals('approved', $approvalRequest->status);
        $this->assertEquals('approved', $sppd->status);
        $this->assertEquals('Disetujui', $sppd->approval_stage_label);
    }

    public function test_supervisor_and_manager_can_review_and_approve_via_review_page()
    {
        $org = Organization::create(['name' => 'PT Permata Karya Jasa', 'code' => 'PKJ']);
        $dept = Department::create(['organization_id' => $org->id, 'name' => 'MESI', 'code' => 'MESI']);
        $mgrPos = Position::create(['name' => 'Manajer Area', 'code' => 'MGR', 'level' => 1]);
        $spvPos = Position::create(['name' => 'Supervisor Lapangan', 'code' => 'SPV', 'level' => 2]);
        $staffPos = Position::create(['name' => 'Staff Lapangan', 'code' => 'STF', 'level' => 4]);

        Role::firstOrCreate(['name' => 'Manager', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'Supervisor', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'Staff', 'guard_name' => 'web']);

        $mgrUser = User::factory()->create([
            'name' => 'M Nuramzan Iftari',
            'signature_type' => 'draw',
            'signature_data' => 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==',
            'signature_updated_at' => now(),
        ]);
        $mgrUser->assignRole('Manager');
        $mgr = Employee::create([
            'organization_id' => $org->id,
            'department_id' => $dept->id,
            'position_id' => $mgrPos->id,
            'user_id' => $mgrUser->id,
            'name' => 'M Nuramzan Iftari',
            'identity_number' => 'MGR-001',
        ]);

        $spvUser = User::factory()->create([
            'name' => 'Ikhsan Aditya Wardana',
            'signature_type' => 'draw',
            'signature_data' => 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==',
            'signature_updated_at' => now(),
        ]);
        $spvUser->assignRole('Supervisor');
        $spv = Employee::create([
            'organization_id' => $org->id,
            'department_id' => $dept->id,
            'position_id' => $spvPos->id,
            'user_id' => $spvUser->id,
            'manager_id' => $mgr->id,
            'name' => 'Ikhsan Aditya Wardana',
            'identity_number' => 'SPV-001',
        ]);

        $staffUser = User::factory()->create(['name' => 'Chairunnisa']);
        $staffUser->assignRole('Staff');
        $staff = Employee::create([
            'organization_id' => $org->id,
            'department_id' => $dept->id,
            'position_id' => $staffPos->id,
            'user_id' => $staffUser->id,
            'manager_id' => $spv->id,
            'name' => 'Chairunnisa',
            'identity_number' => '125980056',
        ]);

        $trip = BusinessTrip::create([
            'activity_name' => 'Survei Jalur Distribusi',
            'destination' => 'Lampung',
            'start_date' => '2026-10-25',
            'end_date' => '2026-10-30',
        ]);

        $sppd = Sppd::create([
            'business_trip_id' => $trip->id,
            'employee_id' => $staff->id,
            'sppd_number' => '0002/SPPD/MESI/10/2026',
            'status' => 'pending',
            'origin_location' => 'Jakarta',
            'destination_location' => 'Lampung',
            'assignment_purpose' => 'Survei Jalur Distribusi',
            'departure_date' => '2026-10-25',
            'return_date' => '2026-10-30',
            'total_days' => 6,
        ]);

        \Filament\Facades\Filament::setCurrentPanel(\Filament\Facades\Filament::getPanel('admin'));

        // 1. Supervisor membuka ReviewSppd
        Livewire::actingAs($spvUser)
            ->test(\App\Filament\Admin\Resources\SppdResource\Pages\ReviewSppd::class, ['record' => $sppd->getKey()])
            ->assertActionVisible('approve')
            ->call('approveSppd', 'Disetujui oleh SPV via Review Page');

        $sppd->refresh();
        $this->assertEquals('pending', $sppd->status);

        // 2. Manajer membuka ReviewSppd dan melihat catatan SPV, lalu approve final
        Livewire::actingAs($mgrUser)
            ->test(\App\Filament\Admin\Resources\SppdResource\Pages\ReviewSppd::class, ['record' => $sppd->getKey()])
            ->assertActionVisible('approve')
            ->call('approveSppd', 'Disetujui oleh Manajer via Review Page');

        $sppd->refresh();
        $this->assertEquals('approved', $sppd->status);
    }

    public function test_supervisor_or_manager_rejection_marks_sppd_as_rejected()
    {
        $org = Organization::create(['name' => 'PT Permata Karya Jasa', 'code' => 'PKJ']);
        $dept = Department::create(['organization_id' => $org->id, 'name' => 'MESI', 'code' => 'MESI']);
        $mgrPos = Position::create(['name' => 'Manajer Area', 'code' => 'MGR', 'level' => 1]);
        $spvPos = Position::create(['name' => 'Supervisor Lapangan', 'code' => 'SPV', 'level' => 2]);
        $staffPos = Position::create(['name' => 'Staff Lapangan', 'code' => 'STF', 'level' => 4]);

        Role::firstOrCreate(['name' => 'Supervisor', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'Staff', 'guard_name' => 'web']);

        $spvUser = User::factory()->create();
        $spvUser->assignRole('Supervisor');
        $spv = Employee::create([
            'organization_id' => $org->id,
            'department_id' => $dept->id,
            'position_id' => $spvPos->id,
            'user_id' => $spvUser->id,
            'name' => 'Supervisor Test',
            'identity_number' => 'SPV-999',
        ]);

        $staffUser = User::factory()->create();
        $staffUser->assignRole('Staff');
        $staff = Employee::create([
            'organization_id' => $org->id,
            'department_id' => $dept->id,
            'position_id' => $staffPos->id,
            'user_id' => $staffUser->id,
            'manager_id' => $spv->id,
            'name' => 'Staff Test',
            'identity_number' => 'STF-999',
        ]);

        $trip = BusinessTrip::create([
            'activity_name' => 'Rapat Dinas Koordinasi',
            'destination' => 'Surabaya',
            'start_date' => '2026-10-25',
            'end_date' => '2026-10-26',
        ]);

        $sppd = Sppd::create([
            'business_trip_id' => $trip->id,
            'employee_id' => $staff->id,
            'sppd_number' => '0003/SPPD/MESI/10/2026',
            'status' => 'pending',
            'origin_location' => 'Jakarta',
            'destination_location' => 'Surabaya',
            'assignment_purpose' => 'Rapat Dinas Koordinasi',
            'departure_date' => '2026-10-25',
            'return_date' => '2026-10-26',
            'total_days' => 2,
        ]);

        \Filament\Facades\Filament::setCurrentPanel(\Filament\Facades\Filament::getPanel('admin'));

        // Supervisor menolak
        Livewire::actingAs($spvUser)
            ->test(\App\Filament\Admin\Resources\SppdResource\Pages\ReviewSppd::class, ['record' => $sppd->getKey()])
            ->call('rejectSppd', 'Rapat dilakukan via Zoom, dinas dibatalkan.');

        $sppd->refresh();
        $this->assertEquals('rejected', $sppd->status);
    }

    public function test_sppd_approvals_page_renders_stats_widget_and_tabs(): void
    {
        $role = Role::firstOrCreate(['name' => 'Manager', 'guard_name' => 'web']);
        $permission = \Spatie\Permission\Models\Permission::firstOrCreate(['name' => 'view_any_sppd::approval', 'guard_name' => 'web']);
        $role->givePermissionTo($permission);

        $user = User::factory()->create();
        $user->assignRole('Manager');

        $this->actingAs($user);
        \Filament\Facades\Filament::setCurrentPanel(\Filament\Facades\Filament::getPanel('admin'));

        Livewire::actingAs($user)
            ->test(\App\Filament\Admin\Resources\SppdApprovalResource\Pages\ListSppdApprovals::class)
            ->assertSuccessful()
            ->assertSeeHtml('Menunggu Persetujuan')
            ->assertSeeHtml('Disetujui')
            ->assertSeeHtml('Ditolak')
            ->set('activeTab', 'approved')
            ->assertSet('activeTab', 'approved');

        Livewire::actingAs($user)
            ->test(\App\Filament\Admin\Widgets\SppdOverviewWidget::class)
            ->assertSuccessful()
            ->assertSeeHtml('Menunggu Persetujuan')
            ->assertSeeHtml('Disetujui')
            ->assertSeeHtml('Ditolak')
            ->assertSeeHtml('Total Pengajuan');
    }
}
