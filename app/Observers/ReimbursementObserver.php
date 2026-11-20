<?php

namespace App\Observers;

use App\Models\ApprovalPolicy;
use App\Models\ApprovalPolicyStep;
use App\Models\ApprovalRequest;
use App\Models\ApprovalStep;
use App\Models\Reimbursement;
use App\Services\Approval\Resolvers\ManagerAuthorityResolver;

class ReimbursementObserver
{
    /**
     * Handle the Reimbursement "updating" event.
     */
    public function updating(Reimbursement $reimbursement): void
    {
        if ($reimbursement->isDirty('status')) {
            if ($reimbursement->status === 'pending' && ! $reimbursement->submitted_at) {
                $reimbursement->submitted_at = now();
            } elseif ($reimbursement->status === 'approved' && ! $reimbursement->approved_at) {
                $reimbursement->approved_at = now();
            } elseif ($reimbursement->status === 'rejected' && ! $reimbursement->rejected_at) {
                $reimbursement->rejected_at = now();
            }
        }
    }

    /**
     * Handle the Reimbursement "created" event.
     */
    public function created(Reimbursement $reimbursement): void
    {
        if ($reimbursement->status === 'pending') {
            $this->ensureApprovalRequestExists($reimbursement);
        }
    }

    /**
     * Handle the Reimbursement "updated" event.
     */
    public function updated(Reimbursement $reimbursement): void
    {
        if ($reimbursement->isDirty('status') && $reimbursement->status === 'pending') {
            $this->ensureApprovalRequestExists($reimbursement);
        }
    }

    /**
     * Memastikan ApprovalRequest dan ApprovalStep dibuat sesuai ApprovalPolicy Reimbursement (Persetujuan Manajer)
     */
    public function ensureApprovalRequestExists(Reimbursement $reimbursement): ?ApprovalRequest
    {
        $existing = ApprovalRequest::where('document_type', Reimbursement::class)
            ->where('document_id', $reimbursement->id)
            ->first();

        if ($existing) {
            return $existing;
        }

        // Ambil policy Reimbursement atau buat default policy bila belum ada (1 tahap: Persetujuan Manajer)
        $policy = ApprovalPolicy::firstOrCreate(
            ['model_type' => Reimbursement::class],
            ['name' => 'Reimbursement Default Policy']
        );

        // Pastikan policy step 1 (Manajer) tersedia
        ApprovalPolicyStep::firstOrCreate(
            ['approval_policy_id' => $policy->id, 'step_order' => 1],
            ['resolver_class' => ManagerAuthorityResolver::class]
        );

        $policy->refresh();

        $request = ApprovalRequest::create([
            'document_type' => Reimbursement::class,
            'document_id' => $reimbursement->id,
            'approval_policy_id' => $policy->id,
            'requester_employee_id' => $reimbursement->employee_id,
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
