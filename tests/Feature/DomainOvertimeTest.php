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
}
