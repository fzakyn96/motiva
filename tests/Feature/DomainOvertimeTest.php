<?php

namespace Tests\Feature;

use App\Filament\Admin\Pages\OvertimeSettingsPage;
use App\Filament\Admin\Resources\OvertimeApprovalResource;
use App\Filament\Admin\Resources\OvertimeOrderResource;
use App\Models\Department;
use App\Models\DocumentSignature;
use App\Models\Employee;
use App\Models\Organization;
use App\Models\OvertimeOrder;
use App\Models\OvertimeSetting;
use App\Models\Position;
use App\Models\User;
use App\Models\WorkArea;
use App\Notifications\ApprovalStatusNotification;
use App\Services\Overtime\OvertimeWorkflowService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class DomainOvertimeTest extends TestCase
{
    use RefreshDatabase;

    private string $dummyPng = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==';

    private function seedRoles(): void
    {
        foreach (['Superadmin', 'Admin', 'HR', 'Manager', 'Supervisor', 'Division Head', 'Staff'] as $role) {
            Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
        }
    }

    public function test_overtime_settings_controls_who_can_create_spkl(): void
    {
        $this->seedRoles();

        $hr = User::factory()->create();
        $hr->assignRole('HR');

        $spv = User::factory()->create();
        $spv->assignRole('Supervisor');

        $mgr = User::factory()->create();
        $mgr->assignRole('Manager');

        $staff = User::factory()->create();
        $staff->assignRole('Staff');

        $this->actingAs($hr);
        $this->assertTrue(OvertimeOrderResource::canCreate());

        \Filament\Facades\Filament::setCurrentPanel(\Filament\Facades\Filament::getPanel('admin'));

        Livewire::test(OvertimeSettingsPage::class)
            ->fillForm([
                'allowed_creator_roles' => ['Manager'],
                'fs_number' => '05',
                'revision_number' => '01',
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->actingAs($hr);
        $this->assertFalse(OvertimeOrderResource::canCreate());

        $this->actingAs($spv);
        $this->assertFalse(OvertimeOrderResource::canCreate());

        $this->actingAs($mgr);
        $this->assertTrue(OvertimeOrderResource::canCreate());

        $this->actingAs($staff);
        $this->assertFalse(OvertimeOrderResource::canCreate());
    }

    public function test_two_stage_overtime_workflow_signatures_notifications_and_pdf(): void
    {
        $this->seedRoles();
        Notification::fake();

        $org = Organization::create(['name' => 'PT Perkasa', 'code' => 'PKJ']);
        $dept = Department::create(['organization_id' => $org->id, 'name' => 'Operation and Maintenance Management Region III', 'code' => 'MESI']);
        $areaSby = WorkArea::create(['department_id' => $dept->id, 'name' => 'Surabaya', 'code' => 'SBY']);
        $areaJkt = WorkArea::create(['department_id' => $dept->id, 'name' => 'Jakarta', 'code' => 'JKT']);

        $staffPos = Position::create(['name' => 'Operator', 'code' => 'OPR', 'level' => 4]);
        $mgrPos = Position::create(['name' => 'Manager', 'code' => 'MGR', 'level' => 1]);

        $hrUser = User::factory()->create(['email' => 'hr.sby@motiva.test']);
        $hrUser->assignRole('HR');
        $hrEmp = Employee::create([
            'user_id' => $hrUser->id,
            'organization_id' => $org->id,
            'department_id' => $dept->id,
            'work_area_id' => $areaSby->id,
            'position_id' => $staffPos->id,
            'name' => 'HR Surabaya',
            'identity_number' => 'HR-SBY',
        ]);

        $mgrSbyUser = User::factory()->create([
            'email' => 'azwar@motiva.test',
            'signature_type' => 'draw',
            'signature_data' => $this->dummyPng,
        ]);
        $mgrSbyUser->assignRole('Manager');
        $mgrSbyEmp = Employee::create([
            'user_id' => $mgrSbyUser->id,
            'organization_id' => $org->id,
            'department_id' => $dept->id,
            'work_area_id' => $areaSby->id,
            'position_id' => $mgrPos->id,
            'name' => 'Azwar Oktaviansyah',
            'identity_number' => 'MGR-SBY',
        ]);

        $mgrJktUser = User::factory()->create(['email' => 'mgr.jkt@motiva.test']);
        $mgrJktUser->assignRole('Manager');
        Employee::create([
            'user_id' => $mgrJktUser->id,
            'organization_id' => $org->id,
            'department_id' => $dept->id,
            'work_area_id' => $areaJkt->id,
            'position_id' => $mgrPos->id,
            'name' => 'Manager Jakarta',
            'identity_number' => 'MGR-JKT',
        ]);

        $workerUser = User::factory()->create([
            'email' => 'heru@motiva.test',
            'signature_type' => 'draw',
            'signature_data' => $this->dummyPng,
        ]);
        $workerUser->assignRole('Staff');
        $workerEmp = Employee::create([
            'user_id' => $workerUser->id,
            'organization_id' => $org->id,
            'department_id' => $dept->id,
            'work_area_id' => $areaSby->id,
            'position_id' => $staffPos->id,
            'name' => 'Heru Widodo',
            'identity_number' => 'EMP-HERU',
        ]);

        // 1. HR Surabaya creates SPKL (Bagian A)
        $order = OvertimeOrder::create([
            'employee_id' => $workerEmp->id,
            'creator_employee_id' => $hrEmp->id,
            'creator_user_id' => $hrUser->id,
            'satuan_kerja' => 'Operation and Maintenance Management Region III',
            'location_city' => 'Surabaya',
            'overtime_date' => '2026-04-02',
            'command_date' => '2026-04-01',
            'overtime_type' => 'Biasa',
            'work_description' => 'Lembur 4 jam menggantikan cuti Fariz, monitoring dan pengaturan penyaluran jaringan Gas Bumi.',
        ]);

        $workflow = app(OvertimeWorkflowService::class);
        $workflow->submitCommand($order);

        Notification::assertSentTo($mgrSbyUser, ApprovalStatusNotification::class);
        Notification::assertNotSentTo($mgrJktUser, ApprovalStatusNotification::class);

        // Manager Jakarta cannot see Surabaya overtime
        $this->actingAs($mgrJktUser);
        $this->assertSame(0, OvertimeApprovalResource::getEloquentQuery()->count());
        $this->assertSame(0, OvertimeOrderResource::getEloquentQuery()->count());

        // 2. Manager Surabaya approves Stage 1 (Bagian A) -> SPKL number generated + command_manager signature
        $this->actingAs($mgrSbyUser);
        $this->assertSame(1, OvertimeApprovalResource::getEloquentQuery()->count());
        $workflow->approveCommand($order->fresh(), $mgrSbyEmp, 'Laksanakan sesuai jadwal');

        $order->refresh();
        $this->assertSame('command_approved', $order->status);
        $this->assertSame('approved', $order->command_status);
        $this->assertMatchesRegularExpression('#^\d{4}/SPKL/MESI/SBY/\d{2}/\d{4}$#', (string) $order->overtime_number);

        $this->assertDatabaseHas('document_signatures', [
            'document_type' => OvertimeOrder::class,
            'document_id' => $order->id,
            'user_id' => $mgrSbyUser->id,
            'role_type' => 'command_manager',
        ]);

        Notification::assertSentTo($workerUser, ApprovalStatusNotification::class);

        // 3. Assigned worker submits Stage 2 (Bagian B) with time & result + requester signature
        $this->actingAs($workerUser);
        $this->assertSame(1, OvertimeOrderResource::getEloquentQuery()->count());
        $workflow->submitReport($order->fresh(), $workerEmp, [
            'start_time' => '19:00',
            'end_time' => '23:00',
            'report_date' => '2026-04-08',
            'actual_result' => 'Lembur 4 jam menggantikan cuti Fariz, monitoring dan pengaturan penyaluran jaringan Gas Bumi.',
        ]);

        $order->refresh();
        $this->assertSame('report_pending', $order->status);
        $this->assertSame('pending', $order->report_status);
        $this->assertSame('19:00', $order->start_time);
        $this->assertSame('23:00', $order->end_time);

        $this->assertDatabaseHas('document_signatures', [
            'document_type' => OvertimeOrder::class,
            'document_id' => $order->id,
            'user_id' => $workerUser->id,
            'role_type' => 'requester',
        ]);

        // 4. Manager Surabaya approves Stage 2 (Bagian B) -> approved + report_manager signature
        $this->actingAs($mgrSbyUser);
        $workflow->approveReport($order->fresh(), $mgrSbyEmp, 'Pertanggungjawaban disahkan');

        $order->refresh();
        $this->assertSame('approved', $order->status);
        $this->assertSame('approved', $order->report_status);

        $reportSig = DocumentSignature::where('document_type', OvertimeOrder::class)
            ->where('document_id', $order->id)
            ->where('role_type', 'report_manager')
            ->first();
        $this->assertNotNull($reportSig);

        // 5. Verify PDF rendering and QR signature verification & download
        $this->actingAs($workerUser)
            ->get(route('overtimes.print', $order))
            ->assertSuccessful()
            ->assertHeader('Content-Type', 'application/pdf');

        $this->get(route('signatures.verify', $reportSig->token))
            ->assertSuccessful()
            ->assertSee($order->overtime_number)
            ->assertSee('Azwar Oktaviansyah');

        $this->get(route('signatures.download', $reportSig->token))
            ->assertSuccessful()
            ->assertHeader('Content-Type', 'application/pdf');
    }

    public function test_overtime_tariff_multiplier_and_holiday_classification(): void
    {
        $calculator = app(\App\Services\Overtime\OvertimeCalculatorService::class);

        // Formula: MAX(75% * (UD + TP) / 173, UD / 173)
        // Heru Widodo: UD = 5,730,000, TP = 900,000 -> 33,121.3873
        $tariff1 = $calculator->calculateHourlyTariff(5730000, 900000);
        $this->assertEqualsWithDelta(33121.3873, $tariff1, 0.01);

        // Asep Najiulloh: UD = 5,500,000, TP = 250,000 -> 31,791.9075
        $tariff2 = $calculator->calculateHourlyTariff(5500000, 250000);
        $this->assertEqualsWithDelta(31791.9075, $tariff2, 0.01);

        // 5-day workweek multiplier (N vs L)
        $this->assertSame(7.5, $calculator->calculateMultiplier(4, 'N'));
        $this->assertSame(16.0, $calculator->calculateMultiplier(8, 'L'));
        $this->assertSame(19.0, $calculator->calculateMultiplier(9, 'L'));
        $this->assertSame(23.0, $calculator->calculateMultiplier(10, 'L'));

        // Exact Excel match: 4h on N @ 33,121.3873 = 248,410.40
        $this->assertEqualsWithDelta(248410.40, $tariff1 * 7.5, 0.5);

        // National Holiday classification
        \App\Models\NationalHoliday::create([
            'holiday_date' => '2026-04-03',
            'name' => 'Wafat Yesus Kristus',
            'is_active' => true,
        ]);

        // 2026-04-02 is Thursday (weekday) -> N for both non_shift & shift
        $this->assertSame('N', $calculator->resolveDayType('2026-04-02', 'non_shift'));
        $this->assertSame('N', $calculator->resolveDayType('2026-04-02', 'shift'));

        // 2026-04-03 is National Holiday -> L for both non_shift & shift
        $this->assertSame('L', $calculator->resolveDayType('2026-04-03', 'non_shift'));
        $this->assertSame('L', $calculator->resolveDayType('2026-04-03', 'shift'));

        // 2026-04-04 is Saturday -> L for non_shift, N for shift (unless is_shift_day_off)
        $this->assertSame('L', $calculator->resolveDayType('2026-04-04', 'non_shift'));
        $this->assertSame('N', $calculator->resolveDayType('2026-04-04', 'shift', false));
        $this->assertSame('L', $calculator->resolveDayType('2026-04-04', 'shift', true));
    }

    public function test_overtime_compliance_validation_warning_and_block_modes(): void
    {
        $org = Organization::create(['code' => 'PKJ', 'name' => 'PT Permata Karya Jasa']);
        $dept = Department::create(['organization_id' => $org->id, 'code' => 'MESI', 'name' => 'Operation & Maintenance']);
        $area = WorkArea::create(['department_id' => $dept->id, 'code' => 'SBY', 'name' => 'Surabaya']);
        $staffPos = Position::create(['code' => 'NO', 'name' => 'Network Operator']);
        $mgrPos = Position::create(['code' => 'MGR', 'name' => 'Manajer Area']);

        Role::firstOrCreate(['name' => 'Staff', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'Manager', 'guard_name' => 'web']);

        $mgrUser = User::factory()->create();
        $mgrUser->assignRole('Manager');
        $mgrEmp = Employee::create([
            'user_id' => $mgrUser->id,
            'organization_id' => $org->id,
            'department_id' => $dept->id,
            'work_area_id' => $area->id,
            'position_id' => $mgrPos->id,
            'name' => 'Manajer Area',
            'identity_number' => 'EMP-MGR-CMP',
        ]);

        $workerUser = User::factory()->create();
        $workerUser->assignRole('Staff');
        $workerEmp = Employee::create([
            'user_id' => $workerUser->id,
            'organization_id' => $org->id,
            'department_id' => $dept->id,
            'work_area_id' => $area->id,
            'position_id' => $staffPos->id,
            'manager_id' => $mgrEmp->id,
            'name' => 'Operator Test',
            'identity_number' => 'EMP-OP-CMP',
            'base_salary' => 5730000,
            'position_allowance' => 900000,
            'overtime_work_mode' => 'non_shift',
        ]);

        $workflow = app(OvertimeWorkflowService::class);

        // Warning mode: > 4h on weekday without compliance_override_reason must fail
        OvertimeSetting::current()->update([
            'max_daily_hours_weekday' => 4,
            'max_weekly_hours' => 18,
            'compliance_mode' => 'warning',
        ]);

        $order = OvertimeOrder::create([
            'employee_id' => $workerEmp->id,
            'creator_employee_id' => $mgrEmp->id,
            'creator_user_id' => $mgrUser->id,
            'satuan_kerja' => 'O&M',
            'location_city' => 'Surabaya',
            'overtime_date' => '2026-04-02',
            'command_date' => '2026-04-01',
            'overtime_type' => 'Emergency',
            'work_mode' => 'non_shift',
            'planned_hours' => 6,
            'work_description' => 'Perbaikan darurat pipa',
        ]);

        try {
            $workflow->submitCommand($order);
            $this->fail('Expected exception when exceeding weekday 4h limit without override reason');
        } catch (\Exception $e) {
            $this->assertStringContainsString('Wajib isi Catatan Khusus', $e->getMessage());
        }

        // Providing override reason in warning mode succeeds
        $order->update(['compliance_override_reason' => 'Darurat kebocoran pipa utama']);
        $workflow->submitCommand($order->fresh());
        $order->refresh();
        $this->assertSame('warning', $order->compliance_status);

        // Block mode rejects even with override reason
        OvertimeSetting::current()->update(['compliance_mode' => 'block']);
        $order2 = OvertimeOrder::create([
            'employee_id' => $workerEmp->id,
            'creator_employee_id' => $mgrEmp->id,
            'creator_user_id' => $mgrUser->id,
            'satuan_kerja' => 'O&M',
            'location_city' => 'Surabaya',
            'overtime_date' => '2026-04-09',
            'command_date' => '2026-04-08',
            'overtime_type' => 'Biasa',
            'work_mode' => 'non_shift',
            'planned_hours' => 6,
            'compliance_override_reason' => 'Tetap coba ajukan',
            'work_description' => 'Pekerjaan rutin',
        ]);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('melebihi batas PP 35/2021');
        $workflow->submitCommand($order2);
    }

    public function test_monthly_overtime_recap_separates_shift_and_non_shift_and_prints_pdf(): void
    {
        $org = Organization::create(['code' => 'PKJ', 'name' => 'PT Permata Karya Jasa']);
        $dept = Department::create(['organization_id' => $org->id, 'code' => 'MESI', 'name' => 'Operation & Maintenance']);
        $area = WorkArea::create(['department_id' => $dept->id, 'code' => 'SBY', 'name' => 'Surabaya']);
        $staffPos = Position::create(['code' => 'NO', 'name' => 'Network Operator']);
        $bdhPos = Position::create(['code' => 'BDH', 'name' => 'Business Division Head']);

        $user = User::factory()->create();
        $emp = Employee::create([
            'user_id' => $user->id,
            'organization_id' => $org->id,
            'department_id' => $dept->id,
            'work_area_id' => $area->id,
            'position_id' => $staffPos->id,
            'name' => 'Heru Widodo',
            'identity_number' => '2119900050',
            'base_salary' => 5730000,
            'position_allowance' => 900000,
            'overtime_work_mode' => 'shift',
        ]);

        Employee::create([
            'organization_id' => $org->id,
            'department_id' => $dept->id,
            'work_area_id' => $area->id,
            'position_id' => $bdhPos->id,
            'name' => 'Khairul Khitam',
            'identity_number' => 'BDH-001',
        ]);

        // 1. Shift order on weekday (4h -> 248,410.40)
        OvertimeOrder::create([
            'overtime_number' => '0001/SPKL/MESI/SBY/04/2026',
            'employee_id' => $emp->id,
            'satuan_kerja' => 'O&M',
            'location_city' => 'Surabaya',
            'overtime_date' => '2026-04-02',
            'command_date' => '2026-04-01',
            'overtime_type' => 'Biasa',
            'work_mode' => 'shift',
            'day_type' => 'N',
            'actual_hours' => 4,
            'hourly_tariff' => 33121.3873,
            'multiplier' => 7.5,
            'overtime_pay' => 248410.40,
            'work_description' => 'Lembur Shift',
            'command_status' => 'approved',
            'report_status' => 'approved',
            'status' => 'approved',
        ]);

        // 2. Non-Shift order on holiday (15h -> 993,641.62)
        OvertimeOrder::create([
            'overtime_number' => '0002/SPKL/MESI/SBY/04/2026',
            'employee_id' => $emp->id,
            'satuan_kerja' => 'O&M',
            'location_city' => 'Surabaya',
            'overtime_date' => '2026-04-03',
            'command_date' => '2026-04-02',
            'overtime_type' => 'Biasa',
            'work_mode' => 'non_shift',
            'day_type' => 'L',
            'actual_hours' => 15,
            'hourly_tariff' => 33121.3873,
            'multiplier' => 30.0,
            'overtime_pay' => 993641.62,
            'work_description' => 'Lembur Non-Shift',
            'command_status' => 'approved',
            'report_status' => 'approved',
            'status' => 'approved',
        ]);

        $recap = \App\Models\OvertimeRecap::create([
            'recap_number' => \App\Models\OvertimeRecap::generateRecapNumber(4, 2026),
            'period_month' => 4,
            'period_year' => 2026,
            'title' => 'Rekapitulasi Biaya Reimbursement Lembur Personil',
            'department_id' => $dept->id,
            'creator_id' => $user->id,
            'status' => 'draft',
        ])->recalculateFromOrders();

        $this->assertEquals(248410, $recap->total_shift_amount);
        $this->assertEquals(993642, $recap->total_non_shift_amount);
        $this->assertEquals(1242052, $recap->grand_total);
        $this->assertCount(1, $recap->items);

        $this->actingAs($user)
            ->get(route('overtime-recaps.print', $recap))
            ->assertSuccessful()
            ->assertHeader('Content-Type', 'application/pdf');
    }

    public function test_google_calendar_holiday_sync_imports_holidays_and_cuti_bersama(): void
    {
        $ics = <<<ICS
BEGIN:VCALENDAR
VERSION:2.0
BEGIN:VEVENT
DTSTART;VALUE=DATE:20260403
SUMMARY:Wafat Yesus Kristus
DESCRIPTION:Hari libur nasional
END:VEVENT
BEGIN:VEVENT
DTSTART;VALUE=DATE:20260320
SUMMARY:Cuti Bersama Idul Fitri
DESCRIPTION:Cuti bersama
END:VEVENT
BEGIN:VEVENT
DTSTART;VALUE=DATE:20250101
SUMMARY:Tahun Baru 2025
DESCRIPTION:Tahun lalu
END:VEVENT
END:VCALENDAR
ICS;

        \Illuminate\Support\Facades\Http::fake([
            \App\Services\Overtime\NationalHolidaySyncService::GOOGLE_INDONESIA_ICS_URL => \Illuminate\Support\Facades\Http::response($ics, 200),
        ]);

        $result = app(\App\Services\Overtime\NationalHolidaySyncService::class)->syncYear(2026);

        $this->assertSame(2, $result['created']);
        $this->assertSame(0, $result['updated']);
        $this->assertSame(2, $result['total']);

        $this->assertTrue(\App\Models\NationalHoliday::isHoliday('2026-04-03'));
        $this->assertTrue(\App\Models\NationalHoliday::isHoliday('2026-03-20'));
        $this->assertFalse(\App\Models\NationalHoliday::isHoliday('2025-01-01'));
    }

    public function test_workflow_guide_page_renders_with_live_counters(): void
    {
        Role::firstOrCreate(['name' => 'Staff', 'guard_name' => 'web']);
        $user = User::factory()->create();
        $user->assignRole('Staff');

        $this->actingAs($user);
        \Filament\Facades\Filament::setCurrentPanel(\Filament\Facades\Filament::getPanel('admin'));

        Livewire::test(\App\Filament\Admin\Pages\WorkflowGuidePage::class)
            ->assertSuccessful()
            ->assertSee('Flowchart Perjalanan Dinas')
            ->assertSee('Flowchart Kerja Lembur')
            ->assertSee('Matriks Peran');
    }
}
