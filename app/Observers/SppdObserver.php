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
     * Handle the Sppd "created" event.
     */
    public function created(Sppd $sppd): void
    {
        if ($sppd->status === 'pending') {
            $this->ensureApprovalRequestExists($sppd);
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

        // Ambil policy SPPD atau buat default policy bila belum ada (Supervisor -> Manager)
        $policy = ApprovalPolicy::firstOrCreate(
            ['model_type' => Sppd::class],
            ['name' => 'SPPD Default Policy']
        );

        // Pastikan policy steps tersedia (Step 1: Supervisor, Step 2: Manager)
        ApprovalPolicyStep::firstOrCreate(
            ['approval_policy_id' => $policy->id, 'step_order' => 1],
            ['resolver_class' => SupervisorAuthorityResolver::class]
        );

        ApprovalPolicyStep::firstOrCreate(
            ['approval_policy_id' => $policy->id, 'step_order' => 2],
            ['resolver_class' => ManagerAuthorityResolver::class]
        );

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

        return $request;
    }
}
