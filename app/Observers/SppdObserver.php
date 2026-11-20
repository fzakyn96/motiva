<?php

namespace App\Observers;

use App\Models\ApprovalPolicy;
use App\Models\ApprovalPolicyStep;
use App\Models\ApprovalRequest;
use App\Models\ApprovalStep;
use App\Models\Sppd;
use App\Services\Approval\Resolvers\ManagerAuthorityResolver;
use App\Services\Approval\Resolvers\SupervisorAuthorityResolver;

class SppdObserver
{
    /**
     * Handle the Sppd "creating" event.
     */
    public function creating(Sppd $sppd): void
    {
        if ($sppd->status === 'approved' && empty($sppd->sppd_number)) {
            $sppd->sppd_number = $this->generateSppdNumber($sppd);
        }
    }

    /**
     * Handle the Sppd "created" event.
     */
    public function created(Sppd $sppd): void
    {
        if ($sppd->status === 'pending') {
            $this->ensureApprovalRequestExists($sppd);
        }
    }

    /**
     * Handle the Sppd "updating" event.
     */
    public function updating(Sppd $sppd): void
    {
        if ($sppd->status === 'approved' && empty($sppd->sppd_number)) {
            $sppd->sppd_number = $this->generateSppdNumber($sppd);
        }
    }

    /**
     * Handle the Sppd "updated" event.
     */
    public function updated(Sppd $sppd): void
    {
        if ($sppd->isDirty('status') && $sppd->status === 'pending') {
            $this->ensureApprovalRequestExists($sppd);
        }
    }

    protected function generateSppdNumber(Sppd $sppd): string
    {
        $year = date('Y');
        $month = date('m');

        $employee = $sppd->employee ?? \App\Models\Employee::with(['department', 'workArea'])->find($sppd->employee_id);
        $deptCode = $employee?->department?->code
            ?? $sppd->businessTrip?->memo?->department?->code
            ?? 'MESI';

        $areaCode = 'JKT';
        if (\Illuminate\Support\Facades\Schema::hasTable('work_areas')) {
            $employee?->loadMissing('workArea');
            $areaCode = $employee?->workArea?->code
                ?? $sppd->businessTrip?->memo?->creator?->workArea?->code
                ?? 'JKT';
        }

        $count = Sppd::whereYear('created_at', $year)->whereNotNull('sppd_number')->count();
        $seq = $count + 1;

        do {
            $nextSeq = str_pad((string) $seq, 4, '0', STR_PAD_LEFT);
            $candidate = "{$nextSeq}/SPPD/{$deptCode}/{$areaCode}/{$month}/{$year}";
            $seq++;
        } while (Sppd::where('sppd_number', $candidate)->exists());

        return $candidate;
    }

    /**
     * Memastikan ApprovalRequest dan ApprovalStep dibuat sesuai ApprovalPolicy SPPD
     */
    public function ensureApprovalRequestExists(Sppd $sppd): ?ApprovalRequest
    {
        $existing = ApprovalRequest::where('document_type', Sppd::class)
            ->where('document_id', $sppd->id)
            ->first();

        if ($existing) {
            return $existing;
        }

        $sppd->loadMissing(['employee.manager.position', 'employee.manager.user']);
        $hasSupervisor = $sppd->hasAreaSupervisor();

        if ($hasSupervisor) {
            // Policy SPPD 2 tahap (Step 1: Supervisor -> Step 2: Manager)
            $policy = ApprovalPolicy::firstOrCreate(
                ['model_type' => Sppd::class],
                ['name' => 'SPPD Default Policy']
            );

            ApprovalPolicyStep::firstOrCreate(
                ['approval_policy_id' => $policy->id, 'step_order' => 1],
                ['resolver_class' => SupervisorAuthorityResolver::class]
            );

            ApprovalPolicyStep::firstOrCreate(
                ['approval_policy_id' => $policy->id, 'step_order' => 2],
                ['resolver_class' => ManagerAuthorityResolver::class]
            );
        } else {
            // Policy SPPD 1 tahap langsung ke Manajer (untuk area kerja yang tidak memiliki Supervisor)
            $policy = ApprovalPolicy::firstOrCreate(
                ['model_type' => Sppd::class . ':direct_manager'],
                ['name' => 'SPPD Direct Manager Policy']
            );

            ApprovalPolicyStep::firstOrCreate(
                ['approval_policy_id' => $policy->id, 'step_order' => 1],
                ['resolver_class' => ManagerAuthorityResolver::class]
            );
        }

        $policy->refresh();

        $request = ApprovalRequest::create([
            'document_type' => Sppd::class,
            'document_id' => $sppd->id,
            'approval_policy_id' => $policy->id,
            'requester_employee_id' => $sppd->employee_id,
            'status' => 'pending',
        ]);

        foreach ($policy->steps as $step) {
            ApprovalStep::create([
                'approval_request_id' => $request->id,
                'approval_policy_step_id' => $step->id,
                'status' => 'pending',
            ]);
        }

        app(\App\Services\Approval\ApprovalNotificationService::class)->notifyPendingApprovers($request);

        return $request;
    }
}
