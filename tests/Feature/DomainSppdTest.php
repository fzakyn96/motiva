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
        \App\Models\SppdSetting::current()->update(['form_filler_role' => 'Staff']);

        // Staff opens edit page: verify factual fields are auto-filled, decision/choice fields start empty, back action, and hidden print button
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
                'travel_grade' => null,
                'departure_time' => '08:00',
                'return_time' => '17:00',
                'travel_category' => null,
                'general_travel_type' => null,
                'special_travel_type' => null,
                'transport_departure' => null,
                'transport_arrival' => null,
                'transportation_mode' => null,
                'destination_transportation' => [],
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

        // Once submitted (status is pending), Staff can no longer edit SPPD, only view and print via ReviewSppd
        $this->assertFalse(\App\Filament\Admin\Resources\SppdResource::canEdit($sppd));
        Livewire::actingAs($staffUser)
            ->test(\App\Filament\Admin\Resources\SppdResource\Pages\ReviewSppd::class, ['record' => $sppd->getKey()])
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

    public function test_staff_and_hr_sppd_permissions_follow_strict_status_and_delete_rules(): void
    {
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
        \Filament\Facades\Filament::setCurrentPanel(\Filament\Facades\Filament::getPanel('admin'));

        $org = Organization::create(['name' => 'PT Motiva', 'code' => 'MTV']);
        $dept = Department::create(['organization_id' => $org->id, 'name' => 'Jakarta', 'code' => 'JKT']);
        $pos = Position::create(['name' => 'Staff', 'code' => 'STF', 'level' => 4]);

        $staffUser = User::factory()->create(['name' => 'Staff SPPD']);
        $staffUser->assignRole('Staff');
        $staffEmp = Employee::create([
            'organization_id' => $org->id,
            'department_id' => $dept->id,
            'position_id' => $pos->id,
            'user_id' => $staffUser->id,
            'name' => 'Staff SPPD',
            'identity_number' => 'STF-SPPD-99',
        ]);

        $hrUser = User::factory()->create(['name' => 'HR SPPD']);
        $hrUser->assignRole('HR');
        Employee::create([
            'organization_id' => $org->id,
            'department_id' => $dept->id,
            'position_id' => $pos->id,
            'user_id' => $hrUser->id,
            'name' => 'HR SPPD',
            'identity_number' => 'HR-SPPD-99',
        ]);

        $trip = BusinessTrip::create([
            'activity_name' => 'Dinas Uji Akses SPPD',
            'destination' => 'Yogyakarta',
            'start_date' => '2026-11-10',
            'end_date' => '2026-11-12',
        ]);

        $sppd = Sppd::create([
            'business_trip_id' => $trip->id,
            'employee_id' => $staffEmp->id,
            'sppd_number' => '099/SPPD/JKT/11/2026',
            'status' => 'draft',
        ]);

        // 1. Default setting (HR): Staff hanya bisa melihat draft SPPD, tidak bisa mengedit sampai diatur ke Staff
        $this->actingAs($staffUser);
        $this->assertTrue(\App\Filament\Admin\Resources\SppdResource::canView($sppd));
        $this->assertFalse(\App\Filament\Admin\Resources\SppdResource::canEdit($sppd));
        $this->assertFalse(\App\Filament\Admin\Resources\SppdResource::canDelete($sppd));

        // Saat pengaturan diubah ke Staff: Staff bisa mengedit SPPD draft
        \App\Models\SppdSetting::current()->update(['form_filler_role' => 'Staff']);
        $this->assertTrue(\App\Filament\Admin\Resources\SppdResource::canEdit($sppd));

        // 2. Saat status sudah disetujui (approved): Staff HANYA bisa melihat dan mencetak SPPD (tidak bisa edit & tidak bisa hapus)
        $sppd->update([
            'status' => 'approved',
            'sppd_number' => '099/SPPD/JKT/11/2026',
        ]);

        $this->assertTrue(\App\Filament\Admin\Resources\SppdResource::canView($sppd));
        $this->assertFalse(\App\Filament\Admin\Resources\SppdResource::canEdit($sppd));
        $this->assertFalse(\App\Filament\Admin\Resources\SppdResource::canDelete($sppd));
        $this->get(route('sppds.print', $sppd))->assertSuccessful();

        // 3. HR tidak boleh menghapus SPPD, hanya boleh mengedit, melihat, dan mencetak SPPD
        $this->actingAs($hrUser);
        $this->assertTrue(\App\Filament\Admin\Resources\SppdResource::canView($sppd));
        $this->assertTrue(\App\Filament\Admin\Resources\SppdResource::canEdit($sppd));
        $this->assertFalse(\App\Filament\Admin\Resources\SppdResource::canDelete($sppd));
        $this->assertFalse(\App\Filament\Admin\Resources\SppdResource::canDeleteAny());
        $this->get(route('sppds.print', $sppd))->assertSuccessful();
    }

    public function test_sppd_print_and_cost_print_resolve_authorizing_manager_not_hr_or_supervisor(): void
    {
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);

        $org = Organization::create(['name' => 'PT Permata Karya Jasa', 'code' => 'PKJ']);
        $dept = Department::create(['organization_id' => $org->id, 'name' => 'Operasional', 'code' => 'OPR']);
        $mgrPos = Position::create(['name' => 'Manajer Operasional', 'code' => 'MGR', 'level' => 2]);
        $spvPos = Position::create(['name' => 'Supervisor Operasional', 'code' => 'SPV', 'level' => 3]);
        $hrPos = Position::create(['name' => 'HR Officer', 'code' => 'HR', 'level' => 3]);
        $stfPos = Position::create(['name' => 'Staff Lapangan', 'code' => 'STF', 'level' => 4]);

        $managerUser = User::factory()->create(['name' => 'Manager Operasi']);
        $managerUser->assignRole('Manager');
        $manager = Employee::create([
            'organization_id' => $org->id,
            'department_id' => $dept->id,
            'position_id' => $mgrPos->id,
            'user_id' => $managerUser->id,
            'name' => 'Manager Operasi',
            'identity_number' => 'EMP-MGR-01',
        ]);

        $supervisorUser = User::factory()->create(['name' => 'Supervisor Operasi']);
        $supervisorUser->assignRole('Supervisor');
        $supervisor = Employee::create([
            'organization_id' => $org->id,
            'department_id' => $dept->id,
            'position_id' => $spvPos->id,
            'user_id' => $supervisorUser->id,
            'manager_id' => $manager->id,
            'name' => 'Supervisor Operasi',
            'identity_number' => 'EMP-SPV-01',
        ]);

        $hrUser = User::factory()->create(['name' => 'HR Officer']);
        $hrUser->assignRole('HR');
        $hr = Employee::create([
            'organization_id' => $org->id,
            'department_id' => $dept->id,
            'position_id' => $hrPos->id,
            'user_id' => $hrUser->id,
            'manager_id' => $manager->id,
            'name' => 'HR Officer',
            'identity_number' => 'EMP-HR-01',
        ]);

        $staffUser = User::factory()->create(['name' => 'Staff Lapangan']);
        $staffUser->assignRole('Staff');
        $staff = Employee::create([
            'organization_id' => $org->id,
            'department_id' => $dept->id,
            'position_id' => $stfPos->id,
            'user_id' => $staffUser->id,
            'manager_id' => $supervisor->id,
            'name' => 'Staff Lapangan',
            'identity_number' => 'EMP-STF-01',
        ]);

        $trip = BusinessTrip::create([
            'activity_name' => 'Audit Cabang',
            'destination' => 'Bandung',
            'start_date' => '2026-11-15',
            'end_date' => '2026-11-17',
        ]);

        $memo = Memo::create([
            'nomor_memo' => 'MEMO/MGR/001',
            'title' => 'Penugasan Luar Kota',
            'content' => 'Isi memo',
            'department_id' => $dept->id,
            'creator_employee_id' => $hr->id,
            'date' => now()->toDateString(),
            'status' => 'approved',
        ]);

        $sppd = Sppd::create([
            'sppd_number' => 'SPPD/MGR/001',
            'memo_id' => $memo->id,
            'business_trip_id' => $trip->id,
            'employee_id' => $staff->id,
            'status' => 'approved',
            'total_days' => 3,
        ]);

        $cost = new \App\Models\SppdCost([
            'sppd_id' => $sppd->id,
            'creator_id' => $hrUser->id,
            'base_type' => 'office_base_stay',
            'official_days' => 3,
            'meal_qty' => 3,
            'meal_rate' => 100000,
        ]);
        $cost->recalculateTotals();
        $cost->save();

        $resolved = $sppd->resolveAuthorizingManager();
        $this->assertNotNull($resolved);
        $this->assertSame($manager->id, $resolved->id);

        $this->actingAs($staffUser);
        $this->get(route('sppds.print', $sppd))
            ->assertSuccessful()
            ->assertHeader('Content-Type', 'application/pdf');

        $this->get(route('sppd-costs.print', $cost))
            ->assertSuccessful()
            ->assertHeader('Content-Type', 'application/pdf');
    }

    public function test_staff_saving_draft_sppd_requires_cost_agreement_confirmation_and_signs_requester_slot(): void
    {
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
        \Filament\Facades\Filament::setCurrentPanel(\Filament\Facades\Filament::getPanel('admin'));

        $org = Organization::create(['name' => 'PT Permata Karya Jasa', 'code' => 'PKJ']);
        $dept = Department::create(['organization_id' => $org->id, 'name' => 'Keuangan', 'code' => 'KEU']);
        $mgrPos = Position::create(['name' => 'Manajer Keuangan', 'code' => 'MGR-K', 'level' => 2]);
        $stfPos = Position::create(['name' => 'Staff Keuangan', 'code' => 'STF-K', 'level' => 4]);

        $managerUser = User::factory()->create(['name' => 'Manager Keuangan']);
        $managerUser->assignRole('Manager');
        $manager = Employee::create([
            'organization_id' => $org->id,
            'department_id' => $dept->id,
            'position_id' => $mgrPos->id,
            'user_id' => $managerUser->id,
            'name' => 'Manager Keuangan',
            'identity_number' => 'EMP-MGR-02',
        ]);

        $staffUser = User::factory()->create([
            'name' => 'Staff Keuangan',
            'signature_type' => 'draw',
            'signature_data' => 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==',
        ]);
        $staffUser->assignRole('Staff');
        $staff = Employee::create([
            'organization_id' => $org->id,
            'department_id' => $dept->id,
            'position_id' => $stfPos->id,
            'user_id' => $staffUser->id,
            'manager_id' => $manager->id,
            'name' => 'Staff Keuangan',
            'identity_number' => 'EMP-STF-02',
        ]);

        $trip = BusinessTrip::create([
            'activity_name' => 'Rapat Anggaran',
            'destination' => 'Surabaya',
            'start_date' => '2026-11-20',
            'end_date' => '2026-11-21',
        ]);

        $sppd = Sppd::create([
            'sppd_number' => 'SPPD/KEU/001',
            'business_trip_id' => $trip->id,
            'employee_id' => $staff->id,
            'status' => 'draft',
            'total_days' => 2,
        ]);

        $cost = new \App\Models\SppdCost([
            'sppd_id' => $sppd->id,
            'creator_id' => $managerUser->id,
            'base_type' => 'office_base_stay',
            'official_days' => 2,
            'meal_qty' => 2,
            'meal_rate' => 100000,
        ]);
        $cost->recalculateTotals();
        $cost->save();

        \App\Models\SppdSetting::current()->update(['form_filler_role' => 'Staff']);
        $this->actingAs($staffUser);

        Livewire::test(\App\Filament\Admin\Resources\SppdResource\Pages\EditSppd::class, [
            'record' => $sppd->getRouteKey(),
        ])
            ->assertActionExists('save')
            ->fillForm([
                'travel_grade' => 'Golongan II',
                'origin_location' => 'Jakarta',
                'destination_location' => 'Surabaya',
                'assignment_purpose' => 'Rapat Anggaran',
                'departure_date' => '2026-11-20',
                'return_date' => '2026-11-21',
                'total_days' => 2,
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
            ->callAction('save')
            ->assertHasNoActionErrors();

        $docSignature = \App\Models\DocumentSignature::where('document_type', Sppd::class)
            ->where('document_id', $sppd->id)
            ->where('role_type', 'requester')
            ->first();

        $this->assertNotNull($docSignature);
        $this->assertEquals($staffUser->id, $docSignature->user_id);

        $requesterRender = app(\App\Services\Signature\SignatureService::class)
            ->resolveRenderData($sppd, $staff, 'requester', true);
        $this->assertTrue($requesterRender['signed'] ?? false);

        $managerRender = app(\App\Services\Signature\SignatureService::class)
            ->resolveRenderData($sppd, $manager, 'manager', false);
        $this->assertFalse($managerRender['signed'] ?? false);

        $this->get(route('sppd-costs.print', $cost))
            ->assertSuccessful()
            ->assertHeader('Content-Type', 'application/pdf');
    }

    public function test_sppd_settings_page_defaults_to_hr_and_can_be_configured_by_hr_or_superadmin(): void
    {
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
        \Filament\Facades\Filament::setCurrentPanel(\Filament\Facades\Filament::getPanel('admin'));

        $this->assertSame('HR', \App\Models\SppdSetting::current()->form_filler_role);

        $hrUser = User::factory()->create();
        $hrUser->assignRole('HR');

        $staffUser = User::factory()->create();
        $staffUser->assignRole('Staff');

        $this->actingAs($staffUser);
        $this->assertFalse(\App\Filament\Admin\Pages\SppdSettingsPage::canAccess());

        $this->actingAs($hrUser);
        $this->assertTrue(\App\Filament\Admin\Pages\SppdSettingsPage::canAccess());

        Livewire::test(\App\Filament\Admin\Pages\SppdSettingsPage::class)
            ->assertSuccessful()
            ->fillForm(['form_filler_role' => 'Staff'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('Staff', \App\Models\SppdSetting::current()->form_filler_role);
    }

    public function test_sppd_form_validates_points_9_to_15_with_indonesian_messages(): void
    {
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
        \Filament\Facades\Filament::setCurrentPanel(\Filament\Facades\Filament::getPanel('admin'));

        $org = Organization::create(['name' => 'PT Permata Karya Jasa', 'code' => 'PKJ']);
        $dept = Department::create(['organization_id' => $org->id, 'name' => 'MESI', 'code' => 'MESI']);
        $pos = Position::create(['name' => 'Staff', 'code' => 'STF', 'level' => 4]);

        $hrUser = User::factory()->create(['name' => 'HR Validasi']);
        $hrUser->assignRole('HR');

        $staffUser = User::factory()->create(['name' => 'Staff Validasi']);
        $staffUser->assignRole('Staff');
        $staff = Employee::create([
            'organization_id' => $org->id,
            'department_id' => $dept->id,
            'position_id' => $pos->id,
            'user_id' => $staffUser->id,
            'name' => 'Staff Validasi',
            'identity_number' => 'STF-VAL-01',
        ]);

        $trip = BusinessTrip::create([
            'activity_name' => 'Uji Validasi SPPD',
            'destination' => 'Bandung',
            'start_date' => '2026-11-20',
            'end_date' => '2026-11-21',
        ]);

        $sppd = Sppd::create([
            'business_trip_id' => $trip->id,
            'employee_id' => $staff->id,
            'status' => 'draft',
            'origin_location' => 'Jakarta',
            'destination_location' => 'Bandung',
            'assignment_purpose' => 'Uji Validasi',
            'departure_date' => '2026-11-20',
            'return_date' => '2026-11-21',
            'total_days' => 2,
        ]);

        $this->actingAs($hrUser);

        Livewire::test(EditSppd::class, ['record' => $sppd->getKey()])
            ->call('save')
            ->assertHasFormErrors([
                'travel_category',
                'general_travel_type',
                'special_travel_type',
                'transport_departure',
                'transport_arrival',
                'transportation_mode',
                'destination_transportation',
            ])
            ->assertSee('Kategori wilayah perjalanan dinas (poin 9) wajib dipilih.')
            ->assertDontSee('validation.required');
    }

    public function test_hr_can_apply_same_sppd_form_to_selected_siblings_and_leave_different_ones_in_draft(): void
    {
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
        \Filament\Facades\Filament::setCurrentPanel(\Filament\Facades\Filament::getPanel('admin'));
        \App\Models\SppdSetting::current()->update(['form_filler_role' => 'HR']);

        $org = Organization::create(['name' => 'PT Motiva', 'code' => 'MTV']);
        $dept = Department::create(['organization_id' => $org->id, 'name' => 'Jakarta', 'code' => 'JKT']);
        $pos = Position::create(['name' => 'Staff', 'code' => 'STF', 'level' => 4]);

        $hrUser = User::factory()->create(['name' => 'HR Admin']);
        $hrUser->assignRole('HR');

        $emp1 = Employee::create(['organization_id' => $org->id, 'department_id' => $dept->id, 'position_id' => $pos->id, 'name' => 'Pegawai Satu', 'identity_number' => 'EMP-01']);
        $emp2 = Employee::create(['organization_id' => $org->id, 'department_id' => $dept->id, 'position_id' => $pos->id, 'name' => 'Pegawai Dua', 'identity_number' => 'EMP-02']);
        $emp3 = Employee::create(['organization_id' => $org->id, 'department_id' => $dept->id, 'position_id' => $pos->id, 'name' => 'Pegawai Tiga', 'identity_number' => 'EMP-03']);

        $trip = BusinessTrip::create([
            'activity_name' => 'Kunjungan Lapangan',
            'destination' => 'Lampung',
            'start_date' => '2026-11-20',
            'end_date' => '2026-11-22',
        ]);

        $sppd1 = Sppd::create(['business_trip_id' => $trip->id, 'employee_id' => $emp1->id, 'status' => 'draft']);
        $sppd2 = Sppd::create(['business_trip_id' => $trip->id, 'employee_id' => $emp2->id, 'status' => 'draft']);
        $sppd3 = Sppd::create(['business_trip_id' => $trip->id, 'employee_id' => $emp3->id, 'status' => 'draft']);

        $this->actingAs($hrUser);

        // HR mengisi SPPD 1 dan mencentang SPPD 2 (isian sama), sementara SPPD 3 tidak dicentang (isian berbeda)
        Livewire::test(EditSppd::class, ['record' => $sppd1->getKey()])
            ->fillForm([
                'origin_location' => 'Jakarta',
                'destination_location' => 'Lampung',
                'assignment_purpose' => 'Inspeksi Pipa',
                'departure_date' => '2026-11-20',
                'departure_time' => '08:00',
                'return_date' => '2026-11-22',
                'return_time' => '17:00',
                'total_days' => 3,
                'travel_category' => 'Dalam Negeri',
                'general_travel_type' => 'Sementara',
                'special_travel_type' => 'Lainnya',
                'transport_departure' => 'Ditanggung Perusahaan',
                'transport_arrival' => 'Ditanggung Perusahaan',
                'transportation_mode' => 'Pesawat Udara',
                'destination_transportation' => ['Kendaraan Dinas/Operasional'],
                'apply_to_sibling_sppd_ids' => [$sppd2->id],
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('pending', $sppd1->fresh()->status);
        $this->assertSame('pending', $sppd2->fresh()->status);
        $this->assertSame('Pesawat Udara', $sppd2->fresh()->transportation_mode);
        $this->assertSame('Lampung', $sppd2->fresh()->destination_location);
        $this->assertDatabaseHas('approval_requests', [
            'document_type' => Sppd::class,
            'document_id' => $sppd2->id,
        ]);

        // SPPD 3 tetap draft agar bisa diisi dengan detail berbeda
        $this->assertSame('draft', $sppd3->fresh()->status);
        $this->assertNull($sppd3->fresh()->transportation_mode);
    }

    public function test_hr_can_bulk_fill_selected_draft_sppds_and_leave_others_for_individual_edit(): void
    {
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
        \Filament\Facades\Filament::setCurrentPanel(\Filament\Facades\Filament::getPanel('admin'));
        \App\Models\SppdSetting::current()->update(['form_filler_role' => 'HR']);

        $org = Organization::create(['name' => 'PT Motiva', 'code' => 'MTV']);
        $dept = Department::create(['organization_id' => $org->id, 'name' => 'Jakarta', 'code' => 'JKT']);
        $pos = Position::create(['name' => 'Staff', 'code' => 'STF', 'level' => 4]);

        $hrUser = User::factory()->create(['name' => 'HR Admin']);
        $hrUser->assignRole('HR');

        $emp1 = Employee::create(['organization_id' => $org->id, 'department_id' => $dept->id, 'position_id' => $pos->id, 'name' => 'Pegawai A', 'identity_number' => 'EMP-A']);
        $emp2 = Employee::create(['organization_id' => $org->id, 'department_id' => $dept->id, 'position_id' => $pos->id, 'name' => 'Pegawai B', 'identity_number' => 'EMP-B']);
        $emp3 = Employee::create(['organization_id' => $org->id, 'department_id' => $dept->id, 'position_id' => $pos->id, 'name' => 'Pegawai C', 'identity_number' => 'EMP-C']);

        $trip = BusinessTrip::create([
            'activity_name' => 'Audit Surabaya',
            'destination' => 'Surabaya',
            'start_date' => '2026-11-25',
            'end_date' => '2026-11-27',
        ]);

        $sppdA = Sppd::create(['business_trip_id' => $trip->id, 'employee_id' => $emp1->id, 'status' => 'draft']);
        $sppdB = Sppd::create(['business_trip_id' => $trip->id, 'employee_id' => $emp2->id, 'status' => 'draft']);
        $sppdC = Sppd::create(['business_trip_id' => $trip->id, 'employee_id' => $emp3->id, 'status' => 'draft']);

        $this->actingAs($hrUser);

        Livewire::test(\App\Filament\Admin\Resources\SppdResource\Pages\ListSppds::class)
            ->assertTableBulkActionVisible('bulk_fill_sppd')
            ->callTableBulkAction('bulk_fill_sppd', [$sppdA, $sppdB], data: [
                'origin_location' => 'Jakarta',
                'destination_location' => 'Surabaya',
                'assignment_purpose' => 'Audit Infrastruktur',
                'departure_date' => '2026-11-25',
                'departure_time' => '07:30',
                'return_date' => '2026-11-27',
                'return_time' => '18:00',
                'total_days' => 3,
                'travel_category' => 'Dalam Negeri',
                'general_travel_type' => 'Sementara',
                'special_travel_type' => 'Lainnya',
                'transport_departure' => 'Ditanggung Perusahaan',
                'transport_arrival' => 'Ditanggung Perusahaan',
                'transportation_mode' => 'Kereta Api',
                'destination_transportation' => ['Sewa Kendaraan'],
            ])
            ->assertHasNoTableBulkActionErrors();

        $this->assertSame('pending', $sppdA->fresh()->status);
        $this->assertSame('pending', $sppdB->fresh()->status);
        $this->assertSame('Kereta Api', $sppdA->fresh()->transportation_mode);
        $this->assertSame('Kereta Api', $sppdB->fresh()->transportation_mode);
        $this->assertSame('draft', $sppdC->fresh()->status);
    }

    public function test_manager_bulk_approves_sppds_and_assigned_employee_signs_with_signature_guard(): void
    {
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
        \Filament\Facades\Filament::setCurrentPanel(\Filament\Facades\Filament::getPanel('admin'));
        \App\Models\SppdSetting::current()->update(['form_filler_role' => 'HR']);

        $org = Organization::create(['name' => 'PT Motiva', 'code' => 'MTV']);
        $dept = Department::create(['organization_id' => $org->id, 'name' => 'Jakarta', 'code' => 'JKT']);
        $mgrPos = Position::create(['name' => 'Manager', 'code' => 'MGR', 'level' => 1]);
        $stfPos = Position::create(['name' => 'Staff', 'code' => 'STF', 'level' => 4]);

        $dummyPng = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII=';

        $mgrUser = User::factory()->create([
            'name' => 'M Nuramzan Iftari',
            'signature_type' => 'draw',
            'signature_data' => $dummyPng,
        ]);
        $mgrUser->assignRole('Manager');
        $mgrEmp = Employee::create([
            'organization_id' => $org->id,
            'department_id' => $dept->id,
            'position_id' => $mgrPos->id,
            'user_id' => $mgrUser->id,
            'name' => 'M Nuramzan Iftari',
            'identity_number' => 'MGR-BULK',
        ]);

        $spvPos = Position::create(['name' => 'Supervisor', 'code' => 'SPV', 'level' => 2]);
        $spvUser = User::factory()->create([
            'name' => 'Ikhsan Supervisor',
            'signature_type' => 'draw',
            'signature_data' => $dummyPng,
        ]);
        $spvUser->assignRole('Supervisor');
        $spvEmp = Employee::create([
            'organization_id' => $org->id,
            'department_id' => $dept->id,
            'position_id' => $spvPos->id,
            'user_id' => $spvUser->id,
            'manager_id' => $mgrEmp->id,
            'name' => 'Ikhsan Supervisor',
            'identity_number' => 'SPV-BULK',
        ]);

        // Staff WITHOUT signature initially
        $staffUser = User::factory()->create(['name' => 'Pegawai Penugasan']);
        $staffUser->assignRole('Staff');
        $staffEmp1 = Employee::create([
            'organization_id' => $org->id,
            'department_id' => $dept->id,
            'position_id' => $stfPos->id,
            'user_id' => $staffUser->id,
            'manager_id' => $spvEmp->id,
            'name' => 'Pegawai Penugasan',
            'identity_number' => 'STF-BULK-1',
        ]);
        $staffEmp2 = Employee::create([
            'organization_id' => $org->id,
            'department_id' => $dept->id,
            'position_id' => $stfPos->id,
            'manager_id' => $spvEmp->id,
            'name' => 'Pegawai Penugasan 2',
            'identity_number' => 'STF-BULK-2',
        ]);

        $hrUser = User::factory()->create([
            'name' => 'HR Admin',
            'signature_type' => 'draw',
            'signature_data' => $dummyPng,
        ]);
        $hrUser->assignRole('HR');

        $trip = BusinessTrip::create([
            'activity_name' => 'Dinas Massal',
            'destination' => 'Semarang',
            'start_date' => '2026-11-28',
            'end_date' => '2026-11-29',
        ]);
        $sppd1 = Sppd::create(['business_trip_id' => $trip->id, 'employee_id' => $staffEmp1->id, 'status' => 'draft']);
        $sppd2 = Sppd::create(['business_trip_id' => $trip->id, 'employee_id' => $staffEmp2->id, 'status' => 'draft']);

        // 1. HR submits SPPD1 + SPPD2 without employee requester signature and without confirmation modal
        $this->actingAs($hrUser);
        Livewire::test(EditSppd::class, ['record' => $sppd1->getKey()])
            ->assertDontSee('Konfirmasi Persetujuan Perhitungan Biaya SPPD')
            ->fillForm([
                'origin_location' => 'Jakarta',
                'destination_location' => 'Semarang',
                'assignment_purpose' => 'Audit Semarang',
                'departure_date' => '2026-11-28',
                'departure_time' => '08:00',
                'return_date' => '2026-11-29',
                'return_time' => '17:00',
                'total_days' => 2,
                'travel_category' => 'Dalam Negeri',
                'general_travel_type' => 'Sementara',
                'special_travel_type' => 'Lainnya',
                'transport_departure' => 'Ditanggung Perusahaan',
                'transport_arrival' => 'Ditanggung Perusahaan',
                'transportation_mode' => 'Kereta Api',
                'destination_transportation' => ['Kendaraan Dinas/Operasional'],
                'apply_to_sibling_sppd_ids' => [$sppd2->id],
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertDatabaseMissing('document_signatures', [
            'document_type' => Sppd::class,
            'document_id' => $sppd1->id,
            'role_type' => 'requester',
        ]);

        $req1 = \App\Models\ApprovalRequest::where('document_type', Sppd::class)->where('document_id', $sppd1->id)->firstOrFail();
        $req2 = \App\Models\ApprovalRequest::where('document_type', Sppd::class)->where('document_id', $sppd2->id)->firstOrFail();

        // 2. Supervisor bulk-approves Step 1, then verify Supervisor sees 0 pending & 2 approved while Manager still sees 2 pending
        $this->actingAs($spvUser);
        Livewire::test(\App\Filament\Admin\Resources\SppdApprovalResource\Pages\ListSppdApprovals::class)
            ->callTableBulkAction('bulk_approve', [$req1, $req2], data: ['notes' => 'Disetujui Supervisor'])
            ->assertHasNoTableBulkActionErrors();

        $this->assertFalse(\App\Filament\Admin\Resources\SppdApprovalResource::canUserActOnApproval($req1->fresh(), $spvUser));
        Livewire::test(\App\Filament\Admin\Resources\SppdApprovalResource\Pages\ListSppdApprovals::class)
            ->assertCanNotSeeTableRecords([$req1, $req2]);
        Livewire::test(\App\Filament\Admin\Widgets\SppdOverviewWidget::class)
            ->assertSee('Semua SPPD telah ditinjau');
        Livewire::test(\App\Filament\Admin\Widgets\ExecutiveOverviewWidget::class)
            ->assertSee('Seluruh SPPD telah diverifikasi');

        $this->actingAs($mgrUser);
        $this->assertTrue(\App\Filament\Admin\Resources\SppdApprovalResource::canUserActOnApproval($req1->fresh(), $mgrUser));
        Livewire::test(\App\Filament\Admin\Resources\SppdApprovalResource\Pages\ListSppdApprovals::class)
            ->callTableBulkAction('bulk_approve', [$req1, $req2], data: ['notes' => 'Disetujui Manajer'])
            ->assertHasNoTableBulkActionErrors();

        $this->assertSame('approved', $sppd1->fresh()->status);
        $this->assertSame('approved', $sppd2->fresh()->status);
        $this->assertDatabaseHas('document_signatures', [
            'document_type' => Sppd::class,
            'document_id' => $sppd1->id,
            'role_type' => 'manager',
        ]);

        // 3. Assigned employee without signature is warned/blocked when trying to approve & sign
        $sppd1->refresh();
        $this->actingAs($staffUser);
        Livewire::test(\App\Filament\Admin\Resources\SppdResource\Pages\ListSppds::class)
            ->assertTableActionVisible('employee_approve', $sppd1)
            ->callTableAction('employee_approve', $sppd1)
            ->assertNotified('Tanda Tangan Belum Diatur');

        $this->assertDatabaseMissing('document_signatures', [
            'document_type' => Sppd::class,
            'document_id' => $sppd1->id,
            'role_type' => 'requester',
        ]);

        // 4. After configuring signature, assigned employee approves & signs SPPD -> signature stamped
        $staffUser->update(['signature_type' => 'draw', 'signature_data' => $dummyPng]);
        Livewire::test(\App\Filament\Admin\Resources\SppdResource\Pages\ListSppds::class)
            ->callTableAction('employee_approve', $sppd1)
            ->assertNotified('SPPD Disetujui & Ditandatangani')
            ->assertTableActionHidden('employee_approve', $sppd1);

        $this->assertDatabaseHas('document_signatures', [
            'document_type' => Sppd::class,
            'document_id' => $sppd1->id,
            'user_id' => $staffUser->id,
            'role_type' => 'requester',
        ]);

        $this->get(route('sppds.print', $sppd1))
            ->assertSuccessful()
            ->assertHeader('Content-Type', 'application/pdf');
    }
}
