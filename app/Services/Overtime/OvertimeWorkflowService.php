<?php

namespace App\Services\Overtime;

use App\Models\ApprovalPolicy;
use App\Models\ApprovalPolicyStep;
use App\Models\ApprovalRequest;
use App\Models\ApprovalStep;
use App\Models\Employee;
use App\Models\OvertimeOrder;
use App\Notifications\ApprovalStatusNotification;
use App\Services\Approval\ApprovalNotificationService;
use App\Services\Approval\ApprovalResolver;
use App\Services\Approval\Resolvers\ManagerAuthorityResolver;
use App\Services\Signature\SignatureService;
use Exception;
use Illuminate\Support\Facades\DB;

class OvertimeWorkflowService
{
    public function submitCommand(OvertimeOrder $order): ApprovalRequest
    {
        $order->loadMissing('employee');
        $calculator = app(OvertimeCalculatorService::class);
        $complianceService = app(OvertimeComplianceService::class);

        $workMode = $order->work_mode ?: ($order->employee?->overtime_work_mode ?: 'non_shift');
        $dayType = $calculator->resolveDayType($order->overtime_date, $workMode, (bool) $order->is_shift_day_off);

        $complianceStatus = 'ok';
        $complianceWarnings = [];
        if ($order->employee && (float) ($order->planned_hours ?? 0) > 0) {
            $compliance = $complianceService->evaluate(
                $order->employee,
                $order->overtime_date,
                (float) $order->planned_hours,
                $dayType,
                $order->id
            );
            $complianceStatus = $compliance['status'];
            $complianceWarnings = $compliance['warnings'];

            if ($complianceStatus === 'blocked') {
                throw new Exception(implode(' ', $complianceWarnings));
            }
            if ($complianceStatus === 'warning' && trim((string) $order->compliance_override_reason) === '') {
                throw new Exception('Jam lembur melebihi batas aturan ketenagakerjaan. Wajib isi Catatan Khusus: ' . implode(' ', $complianceWarnings));
            }
        }

        $policy = ApprovalPolicy::firstOrCreate(
            ['model_type' => OvertimeOrder::class . ':command'],
            ['name' => 'Persetujuan Perintah Kerja Lembur (Bagian A)']
        );

        $step = ApprovalPolicyStep::firstOrCreate(
            ['approval_policy_id' => $policy->id, 'step_order' => 1],
            [
                'step_name' => 'Persetujuan Pejabat yang Memerintahkan (Manajer)',
                'resolver_class' => ManagerAuthorityResolver::class,
            ]
        );

        $existing = ApprovalRequest::where('document_type', OvertimeOrder::class)
            ->where('document_id', $order->id)
            ->where('approval_policy_id', $policy->id)
            ->where('status', 'pending')
            ->latest('id')
            ->first();

        if ($existing) {
            return $existing;
        }

        $order->update([
            'work_mode' => $workMode,
            'day_type' => $dayType,
            'compliance_status' => $complianceStatus,
            'compliance_warnings' => ! empty($complianceWarnings) ? $complianceWarnings : null,
            'command_status' => 'pending',
            'status' => 'command_pending',
        ]);

        $request = ApprovalRequest::create([
            'document_type' => OvertimeOrder::class,
            'document_id' => $order->id,
            'approval_policy_id' => $policy->id,
            'requester_employee_id' => $order->employee_id,
            'status' => 'pending',
        ]);

        ApprovalStep::create([
            'approval_request_id' => $request->id,
            'approval_policy_step_id' => $step->id,
            'status' => 'pending',
        ]);

        app(ApprovalNotificationService::class)->notifyPendingApprovers($request);

        return $request;
    }

    public function getPendingCommandRequest(OvertimeOrder $order): ?ApprovalRequest
    {
        $policyId = ApprovalPolicy::where('model_type', OvertimeOrder::class . ':command')->value('id');
        if (! $policyId) {
            return null;
        }

        return ApprovalRequest::where('document_type', OvertimeOrder::class)
            ->where('document_id', $order->id)
            ->where('approval_policy_id', $policyId)
            ->where('status', 'pending')
            ->latest('id')
            ->first();
    }

    public function getPendingReportRequest(OvertimeOrder $order): ?ApprovalRequest
    {
        $policyId = ApprovalPolicy::where('model_type', OvertimeOrder::class . ':report')->value('id');
        if (! $policyId) {
            return null;
        }

        return ApprovalRequest::where('document_type', OvertimeOrder::class)
            ->where('document_id', $order->id)
            ->where('approval_policy_id', $policyId)
            ->where('status', 'pending')
            ->latest('id')
            ->first();
    }

    public function approveCommand(OvertimeOrder $order, Employee $manager, ?string $notes = null): void
    {
        $request = $this->getPendingCommandRequest($order) ?? $this->submitCommand($order);
        $resolver = new ApprovalResolver();

        if (! $resolver->canApprove($request, $manager)) {
            throw new Exception('Anda tidak berwenang menyetujui Perintah Kerja Lembur ini.');
        }

        DB::transaction(function () use ($order, $request, $manager, $notes): void {
            $currentStep = $request->currentStep();
            if ($currentStep) {
                $currentStep->update([
                    'status' => 'approved',
                    'approver_employee_id' => $manager->id,
                    'notes' => $notes,
                ]);
            }
            $request->update(['status' => 'approved']);

            $order->update([
                'overtime_number' => $order->overtime_number ?: OvertimeOrder::generateOvertimeNumber($order),
                'command_status' => 'approved',
                'command_approved_by_employee_id' => $manager->id,
                'command_approved_at' => now(),
                'command_notes' => $notes,
                'status' => 'command_approved',
            ]);

            if ($manager->user && $manager->user->hasConfiguredSignature()) {
                app(SignatureService::class)->signDocument(
                    $order,
                    $manager,
                    'command_manager',
                    $notes ?: 'Menyetujui Surat Perintah Kerja Lembur (Bagian A)',
                    $manager->user
                );
            }
        });

        $order->refresh();
        $this->notifyEmployeeCommandApproved($order);
    }

    public function rejectCommand(OvertimeOrder $order, Employee $manager, string $notes): void
    {
        $request = $this->getPendingCommandRequest($order);
        if ($request) {
            (new ApprovalResolver())->reject($request, $manager, $notes);
        }

        $order->update([
            'command_status' => 'rejected',
            'command_notes' => $notes,
            'status' => 'rejected',
        ]);
    }

    /**
     * @param  array{start_time: string, end_time: string, actual_result: string, report_date: string, compliance_override_reason?: string|null}  $data
     */
    public function submitReport(OvertimeOrder $order, Employee $employee, array $data): ApprovalRequest
    {
        if ($order->command_status !== 'approved') {
            throw new Exception('Perintah Kerja Lembur (Bagian A) belum disetujui Manajer.');
        }

        if ((int) $order->employee_id !== (int) $employee->id) {
            throw new Exception('Hanya pegawai yang ditugaskan yang dapat mengisi pertanggungjawaban lembur.');
        }

        $user = $employee->user;
        if (! app(SignatureService::class)->ensureUserCanSign($user, false)) {
            throw new Exception('Pegawai belum mengatur tanda tangan digital.');
        }

        $calculator = app(OvertimeCalculatorService::class);
        $complianceService = app(OvertimeComplianceService::class);

        $order->start_time = $data['start_time'];
        $order->end_time = $data['end_time'];
        $calc = $calculator->calculateOrder($order, $employee);
        $compliance = $complianceService->evaluate(
            $employee,
            $order->overtime_date,
            $calc['actual_hours'],
            $calc['day_type'],
            $order->id
        );

        $overrideReason = trim((string) ($data['compliance_override_reason'] ?? $order->compliance_override_reason ?? ''));

        if ($compliance['status'] === 'blocked') {
            throw new Exception(implode(' ', $compliance['warnings']));
        }
        if ($compliance['status'] === 'warning' && $overrideReason === '') {
            throw new Exception('Jam lembur melebihi batas aturan ketenagakerjaan. Wajib isi Catatan Khusus: ' . implode(' ', $compliance['warnings']));
        }

        return DB::transaction(function () use ($order, $employee, $user, $data, $calc, $compliance, $overrideReason): ApprovalRequest {
            $order->update([
                'start_time' => $data['start_time'],
                'end_time' => $data['end_time'],
                'actual_result' => $data['actual_result'],
                'report_date' => $data['report_date'],
                'day_type' => $calc['day_type'],
                'actual_hours' => $calc['actual_hours'],
                'hourly_tariff' => $calc['hourly_tariff'],
                'multiplier' => $calc['multiplier'],
                'overtime_pay' => $calc['overtime_pay'],
                'compliance_status' => $compliance['status'],
                'compliance_warnings' => ! empty($compliance['warnings']) ? $compliance['warnings'] : null,
                'compliance_override_reason' => $overrideReason !== '' ? $overrideReason : null,
                'report_status' => 'pending',
                'report_submitted_at' => now(),
                'status' => 'report_pending',
            ]);

            app(SignatureService::class)->signDocument(
                $order,
                $employee,
                'requester',
                'Pertanggungjawaban pelaksanaan kerja lembur (Bagian B)',
                $user
            );

            $policy = ApprovalPolicy::firstOrCreate(
                ['model_type' => OvertimeOrder::class . ':report'],
                ['name' => 'Pengesahan Pertanggungjawaban Kerja Lembur (Bagian B)']
            );

            $step = ApprovalPolicyStep::firstOrCreate(
                ['approval_policy_id' => $policy->id, 'step_order' => 1],
                [
                    'step_name' => 'Pengesahan Pejabat yang Memerintahkan (Manajer)',
                    'resolver_class' => ManagerAuthorityResolver::class,
                ]
            );

            $request = ApprovalRequest::create([
                'document_type' => OvertimeOrder::class,
                'document_id' => $order->id,
                'approval_policy_id' => $policy->id,
                'requester_employee_id' => $order->employee_id,
                'status' => 'pending',
            ]);

            ApprovalStep::create([
                'approval_request_id' => $request->id,
                'approval_policy_step_id' => $step->id,
                'status' => 'pending',
            ]);

            app(ApprovalNotificationService::class)->notifyPendingApprovers($request);

            return $request;
        });
    }

    public function approveReport(OvertimeOrder $order, Employee $manager, ?string $notes = null): void
    {
        $request = $this->getPendingReportRequest($order);
        if (! $request) {
            throw new Exception('Tidak ada pengajuan pertanggungjawaban lembur yang menunggu pengesahan.');
        }

        $resolver = new ApprovalResolver();
        if (! $resolver->canApprove($request, $manager)) {
            throw new Exception('Anda tidak berwenang mengesahkan Pertanggungjawaban Lembur ini.');
        }

        DB::transaction(function () use ($order, $request, $manager, $notes): void {
            $currentStep = $request->currentStep();
            if ($currentStep) {
                $currentStep->update([
                    'status' => 'approved',
                    'approver_employee_id' => $manager->id,
                    'notes' => $notes,
                ]);
            }
            $request->update(['status' => 'approved']);

            $calc = app(OvertimeCalculatorService::class)->calculateOrder($order, $order->employee);

            $order->update([
                'day_type' => $calc['day_type'],
                'actual_hours' => $calc['actual_hours'],
                'hourly_tariff' => $calc['hourly_tariff'],
                'multiplier' => $calc['multiplier'],
                'overtime_pay' => $calc['overtime_pay'],
                'report_status' => 'approved',
                'report_approved_by_employee_id' => $manager->id,
                'report_approved_at' => now(),
                'report_notes' => $notes,
                'status' => 'approved',
            ]);

            if ($manager->user && $manager->user->hasConfiguredSignature()) {
                app(SignatureService::class)->signDocument(
                    $order,
                    $manager,
                    'report_manager',
                    $notes ?: 'Mengesahkan Pertanggungjawaban Kerja Lembur (Bagian B)',
                    $manager->user
                );
            }
        });

        app(ApprovalNotificationService::class)->notifyRequesterResult($request, 'approved', $notes);
    }

    public function rejectReport(OvertimeOrder $order, Employee $manager, string $notes): void
    {
        $request = $this->getPendingReportRequest($order);
        if ($request) {
            (new ApprovalResolver())->reject($request, $manager, $notes);
        }

        $order->update([
            'report_status' => 'rejected',
            'report_notes' => $notes,
            'status' => 'command_approved',
        ]);
    }

    protected function notifyEmployeeCommandApproved(OvertimeOrder $order): void
    {
        $user = $order->employee?->user;
        if (! $user || ! filter_var($user->email, FILTER_VALIDATE_EMAIL)) {
            return;
        }

        if ($order->employee?->isBusinessDivisionHead() || $order->employee?->isContractManager()) {
            return;
        }

        $url = null;
        try {
            $url = \App\Filament\Admin\Resources\OvertimeOrderResource::getUrl('view', ['record' => $order], panel: 'admin');
        } catch (\Throwable) {
            $url = rtrim((string) config('app.url'), '/');
        }

        $user->notify(new ApprovalStatusNotification(
            event: 'approved',
            documentTypeLabel: 'Surat Perintah Kerja Lembur',
            documentTitle: (string) ($order->overtime_number ?? ''),
            requesterName: $order->creator?->name ?? ($order->commandApprover?->name ?? 'Manajer'),
            notes: 'Perintah Kerja Lembur (Bagian A) telah disetujui. Silakan lengkapi Bagian B (Pertanggungjawaban) setelah lembur dilaksanakan.',
            actionUrl: $url,
            details: array_filter([
                'Satuan Kerja' => $order->satuan_kerja,
                'Tanggal Lembur' => $order->overtime_date?->format('d/m/Y'),
                'Jenis Kerja Lembur' => $order->overtime_type,
                'Uraian Kerja Lembur' => $order->work_description,
            ]),
        ));
    }
}
