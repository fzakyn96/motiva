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
     * @param  array{start_time: string, end_time: string, actual_result: string, report_date: string}  $data
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

        return DB::transaction(function () use ($order, $employee, $user, $data): ApprovalRequest {
            $order->update([
                'start_time' => $data['start_time'],
                'end_time' => $data['end_time'],
                'actual_result' => $data['actual_result'],
                'report_date' => $data['report_date'],
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

            $order->update([
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
