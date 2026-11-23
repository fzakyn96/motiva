<?php

namespace Tests\Feature;

use App\Models\ApprovalRequest;
use App\Models\BusinessTrip;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Memo;
use App\Models\Organization;
use App\Models\Position;
use App\Models\Sppd;
use App\Models\User;
use App\Notifications\ApprovalStatusNotification;
use App\Services\Approval\ApprovalResolver;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ApprovalEmailNotificationTest extends TestCase
{
    use RefreshDatabase;

    private function seedRoles(): void
    {
        foreach (['Staff', 'Supervisor', 'Manager', 'HR', 'Division Head', 'Admin', 'Superadmin'] as $roleName) {
            Role::firstOrCreate(['name' => $roleName, 'guard_name' => 'web']);
        }
    }

    public function test_approval_notification_implements_should_queue(): void
    {
        $notification = new ApprovalStatusNotification(
            event: 'submitted',
            documentTypeLabel: 'Memo',
            documentTitle: '0001/MEMO/MESI/JKT/11/2026',
            requesterName: 'Pemohon Test',
        );

        $this->assertInstanceOf(ShouldQueue::class, $notification);
    }

    public function test_pending_memo_queues_email_to_authorized_manager_and_approval_notifies_requester_and_assigned_employees(): void
    {
        $this->seedRoles();
        Notification::fake();

        $org = Organization::create(['name' => 'PT Motiva', 'code' => 'PGN']);
        $dept = Department::create(['organization_id' => $org->id, 'name' => 'IT', 'code' => 'MESI']);
        $staffPos = Position::create(['name' => 'Staff', 'code' => 'STF', 'level' => 3]);
        $mgrPos = Position::create(['name' => 'Manager', 'code' => 'MGR', 'level' => 1]);
        $bdhPos = Position::create(['name' => 'Business Division Head', 'code' => 'BDH', 'level' => 1]);
        $mgrKtrPos = Position::create(['name' => 'Manajer Kontrak', 'code' => 'MGR-KTR', 'level' => 4]);

        $requesterUser = User::factory()->create(['email' => 'pemohon@motiva.test']);
        $requesterUser->assignRole('Staff');
        $requester = Employee::create([
            'user_id' => $requesterUser->id,
            'organization_id' => $org->id,
            'department_id' => $dept->id,
            'position_id' => $staffPos->id,
            'name' => 'Pemohon',
            'identity_number' => 'EMP-001',
        ]);

        $assignedUser = User::factory()->create(['email' => 'peserta@motiva.test']);
        $assignedUser->assignRole('Staff');
        $assignedEmp = Employee::create([
            'user_id' => $assignedUser->id,
            'organization_id' => $org->id,
            'department_id' => $dept->id,
            'position_id' => $staffPos->id,
            'name' => 'Pegawai Ditugaskan',
            'identity_number' => 'EMP-003',
        ]);

        $bdhUser = User::factory()->create(['email' => 'bdh@motiva.test']);
        $bdhUser->assignRole('Manager');
        Employee::create([
            'user_id' => $bdhUser->id,
            'organization_id' => $org->id,
            'department_id' => $dept->id,
            'position_id' => $bdhPos->id,
            'name' => 'Manajer Kontrak BDH',
            'identity_number' => 'EMP-BDH',
        ]);

        $contractMgrUser = User::factory()->create(['email' => 'kontrak@motiva.test']);
        $contractMgrUser->assignRole('Staff');
        Employee::create([
            'user_id' => $contractMgrUser->id,
            'organization_id' => $org->id,
            'department_id' => $dept->id,
            'position_id' => $mgrKtrPos->id,
            'name' => 'Manajer Kontrak',
            'identity_number' => 'EMP-KTR',
        ]);

        $managerUser = User::factory()->create(['email' => 'manager@motiva.test']);
        $managerUser->assignRole('Manager');
        $manager = Employee::create([
            'user_id' => $managerUser->id,
            'organization_id' => $org->id,
            'department_id' => $dept->id,
            'position_id' => $mgrPos->id,
            'name' => 'Manager IT',
            'identity_number' => 'EMP-002',
        ]);

        $memo = Memo::create([
            'department_id' => $dept->id,
            'creator_employee_id' => $requester->id,
            'title' => 'Memo Kunjungan Site',
            'content' => 'Koordinasi infrastruktur',
            'date' => '2026-11-20',
            'participants' => [$assignedEmp->id],
            'status' => 'pending',
        ]);

        Notification::assertSentTo(
            $managerUser,
            ApprovalStatusNotification::class,
            function (ApprovalStatusNotification $n) use ($managerUser, $memo): bool {
                $mail = $n->toMail($managerUser);

                return $n->event === 'submitted'
                    && $n->documentTypeLabel === 'Memo'
                    && $n->requesterName === 'Pemohon'
                    && $mail->subject === '[MOTIVA] Permohonan Persetujuan Memo - Memo Kunjungan Site'
                    && $n->actionUrl === \App\Filament\Admin\Resources\MemoResource::getUrl('review', ['record' => $memo, 'from' => 'approvals'], panel: 'admin');
            }
        );
        Notification::assertNotSentTo($bdhUser, ApprovalStatusNotification::class);
        Notification::assertNotSentTo($contractMgrUser, ApprovalStatusNotification::class);
        Notification::assertNotSentTo($requesterUser, ApprovalStatusNotification::class);

        $request = ApprovalRequest::where('document_type', Memo::class)
            ->where('document_id', $memo->id)
            ->firstOrFail();

        (new ApprovalResolver())->approve($request, $manager, 'Disetujui sesuai jadwal');

        Notification::assertSentTo(
            $requesterUser,
            ApprovalStatusNotification::class,
            function (ApprovalStatusNotification $n) use ($requesterUser, $memo): bool {
                $mail = $n->toMail($requesterUser);
                $intro = implode("\n", $mail->introLines);

                return $n->event === 'approved'
                    && $n->documentTypeLabel === 'Memo'
                    && $n->notes === 'Disetujui sesuai jadwal'
                    && $mail->subject === '[MOTIVA] Pemberitahuan Persetujuan Memo - Memo Kunjungan Site'
                    && $mail->actionUrl === route('memos.print', $memo)
                    && str_contains($intro, 'Dengan hormat, kami informasikan bahwa pengajuan Memo untuk agenda **Memo Kunjungan Site** telah **DISETUJUI**.');
            }
        );
        // Pegawai yang ditugaskan belum mendapat email saat Memo disetujui (menunggu persetujuan Biaya SPPD)
        Notification::assertNotSentTo($assignedUser, ApprovalStatusNotification::class);
        Notification::assertNotSentTo($bdhUser, ApprovalStatusNotification::class);
    }

    public function test_assigned_employee_receives_assignment_email_only_after_sppd_cost_is_approved(): void
    {
        $this->seedRoles();
        Notification::fake();

        $org = Organization::create(['name' => 'PT Motiva', 'code' => 'PGN']);
        $dept = Department::create(['organization_id' => $org->id, 'name' => 'IT', 'code' => 'MESI']);
        $staffPos = Position::create(['name' => 'Staff', 'code' => 'STF', 'level' => 3]);
        $mgrPos = Position::create(['name' => 'Manager', 'code' => 'MGR', 'level' => 1]);

        $hrUser = User::factory()->create(['email' => 'hr.cost@motiva.test']);
        $hrUser->assignRole('HR');
        $hrEmp = Employee::create([
            'user_id' => $hrUser->id,
            'organization_id' => $org->id,
            'department_id' => $dept->id,
            'position_id' => $staffPos->id,
            'name' => 'HR Area',
            'identity_number' => 'EMP-HR-1',
        ]);

        $assignedUser = User::factory()->create(['email' => 'pegawai.dinas@motiva.test']);
        $assignedUser->assignRole('Staff');
        $assignedEmp = Employee::create([
            'user_id' => $assignedUser->id,
            'organization_id' => $org->id,
            'department_id' => $dept->id,
            'position_id' => $staffPos->id,
            'name' => 'Andi Wijaya',
            'identity_number' => 'EMP-ASN-1',
        ]);

        $mgrUser = User::factory()->create(['email' => 'mgr.cost@motiva.test']);
        $mgrUser->assignRole('Manager');
        $mgrEmp = Employee::create([
            'user_id' => $mgrUser->id,
            'organization_id' => $org->id,
            'department_id' => $dept->id,
            'position_id' => $mgrPos->id,
            'name' => 'Manager Area',
            'identity_number' => 'EMP-MGR-1',
        ]);

        $memo = Memo::create([
            'department_id' => $dept->id,
            'creator_employee_id' => $hrEmp->id,
            'title' => 'Memo Dinas Balikpapan',
            'agenda' => 'Audit Sistem Informasi',
            'content' => 'Audit',
            'date' => '2026-11-20',
            'status' => 'approved',
        ]);

        $trip = BusinessTrip::create([
            'memo_id' => $memo->id,
            'activity_name' => 'Audit Sistem Informasi',
            'destination' => 'Balikpapan',
            'start_date' => '2026-11-25',
            'end_date' => '2026-11-27',
        ]);

        $sppd = Sppd::create([
            'business_trip_id' => $trip->id,
            'employee_id' => $assignedEmp->id,
            'destination_location' => 'Balikpapan',
            'departure_date' => '2026-11-25',
            'return_date' => '2026-11-27',
            'status' => 'approved',
        ]);

        $cost = \App\Models\SppdCost::create([
            'sppd_id' => $sppd->id,
            'creator_id' => $hrUser->id,
            'official_days' => 3,
            'grand_total' => 2500000,
            'status' => 'pending',
        ]);

        $costReq = $cost->ensureApprovalRequestExists($hrEmp);

        // Sebelum Biaya SPPD disetujui, pegawai yang ditugaskan belum mendapat email penugasan
        Notification::assertNotSentTo($assignedUser, ApprovalStatusNotification::class);

        (new ApprovalResolver())->approve($costReq, $mgrEmp, 'Biaya disetujui');

        Notification::assertSentTo(
            $assignedUser,
            ApprovalStatusNotification::class,
            function (ApprovalStatusNotification $n) use ($assignedUser): bool {
                $mail = $n->toMail($assignedUser);

                return $n->event === 'assigned'
                    && $mail->subject === '[MOTIVA] Pemberitahuan Penugasan - Audit Sistem Informasi';
            }
        );
    }

    public function test_sppd_notification_for_manager_includes_employee_count_and_names(): void
    {
        $this->seedRoles();
        Notification::fake();

        $org = Organization::create(['name' => 'PT Motiva', 'code' => 'PGN']);
        $dept = Department::create(['organization_id' => $org->id, 'name' => 'IT', 'code' => 'MESI']);
        $staffPos = Position::create(['name' => 'Staff', 'code' => 'STF', 'level' => 3]);
        $mgrPos = Position::create(['name' => 'Manager', 'code' => 'MGR', 'level' => 1]);

        $managerUser = User::factory()->create(['email' => 'mgr.sppd@motiva.test']);
        $managerUser->assignRole('Manager');
        Employee::create([
            'user_id' => $managerUser->id,
            'organization_id' => $org->id,
            'department_id' => $dept->id,
            'position_id' => $mgrPos->id,
            'name' => 'Manager Area',
            'identity_number' => 'EMP-MGR',
        ]);

        $emp1User = User::factory()->create(['email' => 'budi@motiva.test']);
        $emp1User->assignRole('Staff');
        $emp1 = Employee::create([
            'user_id' => $emp1User->id,
            'organization_id' => $org->id,
            'department_id' => $dept->id,
            'position_id' => $staffPos->id,
            'name' => 'Budi Santoso',
            'identity_number' => 'EMP-101',
        ]);

        $emp2User = User::factory()->create(['email' => 'sari@motiva.test']);
        $emp2User->assignRole('Staff');
        $emp2 = Employee::create([
            'user_id' => $emp2User->id,
            'organization_id' => $org->id,
            'department_id' => $dept->id,
            'position_id' => $staffPos->id,
            'name' => 'Sari Lestari',
            'identity_number' => 'EMP-102',
        ]);

        $memo = Memo::create([
            'department_id' => $dept->id,
            'creator_employee_id' => $emp1->id,
            'title' => 'Dinas Surabaya',
            'content' => 'Inspeksi jaringan',
            'date' => '2026-11-20',
            'status' => 'draft',
        ]);

        $trip = BusinessTrip::create([
            'memo_id' => $memo->id,
            'activity_name' => 'Inspeksi Jaringan',
            'destination' => 'Surabaya',
            'start_date' => '2026-11-25',
            'end_date' => '2026-11-27',
        ]);

        Sppd::create([
            'business_trip_id' => $trip->id,
            'employee_id' => $emp1->id,
            'destination_location' => 'Surabaya',
            'departure_date' => '2026-11-25',
            'return_date' => '2026-11-27',
            'status' => 'draft',
        ]);

        Sppd::create([
            'business_trip_id' => $trip->id,
            'employee_id' => $emp2->id,
            'destination_location' => 'Surabaya',
            'departure_date' => '2026-11-25',
            'return_date' => '2026-11-27',
            'status' => 'pending',
        ]);

        Notification::assertSentTo(
            $managerUser,
            ApprovalStatusNotification::class,
            function (ApprovalStatusNotification $n) use ($managerUser): bool {
                $mail = $n->toMail($managerUser);
                $lines = implode("\n", $mail->introLines);

                return $n->event === 'submitted'
                    && $n->documentTypeLabel === 'SPPD'
                    && ($n->details['Jumlah Pegawai'] ?? null) === '2 orang'
                    && str_contains($n->details['Nama Pegawai'] ?? '', 'Budi Santoso')
                    && str_contains($n->details['Nama Pegawai'] ?? '', 'Sari Lestari')
                    && str_contains($lines, '2 orang')
                    && str_contains($lines, 'Budi Santoso');
            }
        );
    }

    public function test_rejection_queues_email_with_notes_to_requester(): void
    {
        $this->seedRoles();
        Notification::fake();

        $org = Organization::create(['name' => 'PT Motiva', 'code' => 'PGN']);
        $dept = Department::create(['organization_id' => $org->id, 'name' => 'Ops', 'code' => 'MESI']);
        $staffPos = Position::create(['name' => 'Staff', 'code' => 'STF', 'level' => 3]);
        $mgrPos = Position::create(['name' => 'Manager', 'code' => 'MGR', 'level' => 1]);

        $requesterUser = User::factory()->create(['email' => 'staff@motiva.test']);
        $requesterUser->assignRole('Staff');
        $requester = Employee::create([
            'user_id' => $requesterUser->id,
            'organization_id' => $org->id,
            'department_id' => $dept->id,
            'position_id' => $staffPos->id,
            'name' => 'Staff Ops',
            'identity_number' => 'EMP-010',
        ]);

        $managerUser = User::factory()->create(['email' => 'mgr.ops@motiva.test']);
        $managerUser->assignRole('Manager');
        $manager = Employee::create([
            'user_id' => $managerUser->id,
            'organization_id' => $org->id,
            'department_id' => $dept->id,
            'position_id' => $mgrPos->id,
            'name' => 'Manager Ops',
            'identity_number' => 'EMP-011',
        ]);

        $memo = Memo::create([
            'department_id' => $dept->id,
            'creator_employee_id' => $requester->id,
            'title' => 'Memo Pengadaan',
            'content' => 'Perjalanan dinas',
            'date' => '2026-11-20',
            'status' => 'pending',
        ]);

        $request = ApprovalRequest::where('document_type', Memo::class)
            ->where('document_id', $memo->id)
            ->firstOrFail();

        (new ApprovalResolver())->reject($request, $manager, 'Tanggal belum sesuai');

        Notification::assertSentTo(
            $requesterUser,
            ApprovalStatusNotification::class,
            fn (ApprovalStatusNotification $n) => $n->event === 'rejected'
                && $n->notes === 'Tanggal belum sesuai'
        );
    }

    public function test_spv_then_manager_and_division_head_receive_notifications_while_bdh_is_skipped(): void
    {
        $this->seedRoles();
        Notification::fake();

        $org = Organization::create(['name' => 'PT Motiva', 'code' => 'PGN']);
        $dept = Department::create(['organization_id' => $org->id, 'name' => 'IT', 'code' => 'MESI']);
        $area = \App\Models\WorkArea::create(['department_id' => $dept->id, 'name' => 'Jakarta', 'code' => 'JKT']);

        $staffPos = Position::create(['name' => 'Staff', 'code' => 'STF', 'level' => 3]);
        $spvPos = Position::create(['name' => 'Supervisor', 'code' => 'SPV', 'level' => 2]);
        $mgrPos = Position::create(['name' => 'Manager', 'code' => 'MGR', 'level' => 1]);
        $bdhPos = Position::create(['name' => 'Business Division Head', 'code' => 'BDH', 'level' => 1]);
        $mgrKtrPos = Position::create(['name' => 'Manajer Kontrak', 'code' => 'MGR-KTR', 'level' => 4]);
        $divPos = Position::create(['name' => 'Division Head', 'code' => 'DIV', 'level' => 0]);

        $spvUser = User::factory()->create(['email' => 'spv.jkt@motiva.test']);
        $spvUser->assignRole('Supervisor');
        $spv = Employee::create([
            'user_id' => $spvUser->id,
            'organization_id' => $org->id,
            'department_id' => $dept->id,
            'work_area_id' => $area->id,
            'position_id' => $spvPos->id,
            'name' => 'Ikhsan Supervisor',
            'identity_number' => 'EMP-SPV',
        ]);

        $otherSpvUser = User::factory()->create(['email' => 'didiet.jkt@motiva.test']);
        $otherSpvUser->assignRole('Supervisor');
        Employee::create([
            'user_id' => $otherSpvUser->id,
            'organization_id' => $org->id,
            'department_id' => $dept->id,
            'work_area_id' => $area->id,
            'position_id' => $spvPos->id,
            'name' => 'Didiet Supervisor Lain',
            'identity_number' => 'EMP-SPV-2',
        ]);

        $mgrUser = User::factory()->create(['email' => 'mgr.jkt@motiva.test']);
        $mgrUser->assignRole('Manager');
        $mgr = Employee::create([
            'user_id' => $mgrUser->id,
            'organization_id' => $org->id,
            'department_id' => $dept->id,
            'work_area_id' => $area->id,
            'position_id' => $mgrPos->id,
            'name' => 'Manager Jakarta',
            'identity_number' => 'EMP-MGR-JKT',
        ]);
        $area->update(['coordinator_employee_id' => $mgr->id]);

        $bdhUser = User::factory()->create(['email' => 'bdh.kontrak@motiva.test']);
        $bdhUser->assignRole('Manager');
        $bdh = Employee::create([
            'user_id' => $bdhUser->id,
            'organization_id' => $org->id,
            'department_id' => $dept->id,
            'work_area_id' => $area->id,
            'position_id' => $bdhPos->id,
            'name' => 'BDH Kontrak',
            'identity_number' => 'EMP-BDH-2',
        ]);

        $divUser = User::factory()->create(['email' => 'divhead@motiva.test']);
        $divUser->assignRole('Division Head');
        $divHead = Employee::create([
            'user_id' => $divUser->id,
            'organization_id' => $org->id,
            'department_id' => $dept->id,
            'position_id' => $divPos->id,
            'name' => 'Division Head',
            'identity_number' => 'EMP-DIV',
        ]);

        $staffUser = User::factory()->create(['email' => 'staff.jkt@motiva.test']);
        $staffUser->assignRole('Staff');
        $staff = Employee::create([
            'user_id' => $staffUser->id,
            'organization_id' => $org->id,
            'department_id' => $dept->id,
            'work_area_id' => $area->id,
            'position_id' => $staffPos->id,
            'manager_id' => $spv->id,
            'name' => 'Staff Jakarta',
            'identity_number' => 'EMP-STF-JKT',
        ]);

        $memo = Memo::create([
            'department_id' => $dept->id,
            'creator_employee_id' => $staff->id,
            'title' => 'Dinas JKT',
            'content' => 'Visit',
            'date' => '2026-11-20',
            'status' => 'draft',
        ]);
        $trip = BusinessTrip::create([
            'memo_id' => $memo->id,
            'activity_name' => 'Visit',
            'destination' => 'Jakarta',
            'start_date' => '2026-11-25',
            'end_date' => '2026-11-26',
        ]);

        $sppd = Sppd::create([
            'business_trip_id' => $trip->id,
            'employee_id' => $staff->id,
            'destination_location' => 'Jakarta',
            'departure_date' => '2026-11-25',
            'return_date' => '2026-11-26',
            'status' => 'pending',
        ]);

        $reqSppd = $sppd->approvalRequests()->firstOrFail();
        $this->assertSame(\App\Services\Approval\Resolvers\SupervisorAuthorityResolver::class, $reqSppd->currentStep()->policyStep->resolver_class);
        $this->assertFalse((new ApprovalResolver())->canApprove($reqSppd, $mgr));

        // Step 1: ONLY direct SPV (Ikhsan) receives SPPD email; other SPV in same area (Didiet) must NOT receive it
        Notification::assertSentTo($spvUser, ApprovalStatusNotification::class, fn ($n) => $n->event === 'submitted' && $n->documentTypeLabel === 'SPPD');
        Notification::assertNotSentTo($otherSpvUser, ApprovalStatusNotification::class, fn ($n) => $n->documentTypeLabel === 'SPPD');
        Notification::assertNotSentTo($mgrUser, ApprovalStatusNotification::class, fn ($n) => $n->documentTypeLabel === 'SPPD');

        // Creating a second SPPD in the same Memo for the same direct SPV must keep only 1 email per Memo
        Sppd::create([
            'business_trip_id' => $trip->id,
            'employee_id' => $staff->id,
            'destination_location' => 'Jakarta',
            'departure_date' => '2026-11-25',
            'return_date' => '2026-11-26',
            'status' => 'pending',
        ]);
        Notification::assertSentToTimes($spvUser, ApprovalStatusNotification::class, 1);
        Notification::assertNotSentTo($bdhUser, ApprovalStatusNotification::class);

        (new ApprovalResolver())->approve($reqSppd, $spv, 'OK SPV');

        // Step 2: Manager receives email after SPV approves
        Notification::assertSentTo($mgrUser, ApprovalStatusNotification::class, fn ($n) => $n->event === 'submitted');

        (new ApprovalResolver())->approve($reqSppd->fresh(), $mgr, 'OK Manager');

        // Final: Assigned employee / requester receives approved notification
        Notification::assertSentTo($staffUser, ApprovalStatusNotification::class, fn ($n) => $n->event === 'approved');

        // Recap flow: Manager -> BDH (skipped email) -> Division Head (receives email)
        $recap = \App\Models\OfficialTravelRecap::create([
            'department_id' => $dept->id,
            'creator_id' => $staffUser->id,
            'period_month' => 11,
            'period_year' => 2026,
            'work_name' => 'Rekap November',
            'total_amount' => 1000000,
            'status' => 'pending',
        ]);
        $recapReq = BusinessTrip::createRecapApprovalFor($recap, $staff, $dept->id);

        (new ApprovalResolver())->approve($recapReq->fresh(), $mgr, 'OK Mgr Recap');
        // Step next is BDH -> BDH must NOT receive email
        Notification::assertNotSentTo($bdhUser, ApprovalStatusNotification::class);

        (new ApprovalResolver())->approve($recapReq->fresh(), $bdh, 'OK BDH');
        // Step next is Division Head -> Division Head MUST receive email
        Notification::assertSentTo($divUser, ApprovalStatusNotification::class, fn ($n) => $n->event === 'submitted');

        (new ApprovalResolver())->approve($recapReq->fresh(), $divHead, 'OK DivHead');
        Notification::assertSentTo($staffUser, ApprovalStatusNotification::class, fn ($n) => $n->event === 'approved' && $n->documentTypeLabel === 'Rekap Perjalanan Dinas');
    }
}
