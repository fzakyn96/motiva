<?php

namespace App\Services\Approval;

use App\Models\ApprovalRequest;
use App\Models\ApprovalStep;
use App\Models\Employee;
use Exception;
use Illuminate\Support\Facades\DB;

class ApprovalResolver
{
    /**
     * Determine if an employee can approve the current step of the given request.
     */
    public function canApprove(ApprovalRequest $request, Employee $employee): bool
    {
        if ($request->status !== 'pending') {
            return false;
        }

        $currentStep = $request->currentStep();
        if (!$currentStep) {
            return false;
        }

        $resolverClass = $currentStep->policyStep->resolver_class;
        if (!class_exists($resolverClass)) {
            return false;
        }

        /** @var AuthorityResolver $resolver */
        $resolver = app($resolverClass);

        return $resolver->hasAuthority($request, $employee);
    }

    /**
     * Approve the current step.
     */
    public function approve(ApprovalRequest $request, Employee $approver, ?string $notes = null): bool
    {
        if (!$this->canApprove($request, $approver)) {
            throw new Exception("Unauthorized to approve this request.");
        }

        return DB::transaction(function () use ($request, $approver, $notes) {
            $currentStep = $request->currentStep();
            
            $currentStep->update([
                'status' => 'approved',
                'approver_employee_id' => $approver->id,
                'notes' => $notes
            ]);

            // If this was the last step, mark request as approved and update the underlying document
            $nextStep = $request->currentStep();
            if (!$nextStep) {
                $request->update(['status' => 'approved']);
                if ($request->document && method_exists($request->document, 'update')) {
                    $request->document->update(['status' => 'approved']);
                }
            }

            if ($request->document && $approver->user && $approver->user->hasConfiguredSignature()) {
                $resolverClass = $currentStep->policyStep?->resolver_class;
                $roleType = match ($resolverClass) {
                    \App\Services\Approval\Resolvers\BusinessDivisionHeadAuthorityResolver::class => 'requester',
                    \App\Services\Approval\Resolvers\DivisionHeadAuthorityResolver::class => 'division_head',
                    \App\Services\Approval\Resolvers\ManagerAuthorityResolver::class => 'manager',
                    \App\Services\Approval\Resolvers\SupervisorAuthorityResolver::class => 'supervisor',
                    default => $nextStep ? 'supervisor' : 'manager',
                };
                app(\App\Services\Signature\SignatureService::class)->signDocument(
                    $request->document,
                    $approver,
                    $roleType,
                    $notes,
                    $approver->user
                );
            }

            return true;
        });
    }

    /**
     * Reject the request.
     */
    public function reject(ApprovalRequest $request, Employee $approver, string $notes): bool
    {
        if (!$this->canApprove($request, $approver)) {
            throw new Exception("Unauthorized to reject this request.");
        }

        return DB::transaction(function () use ($request, $approver, $notes) {
            $currentStep = $request->currentStep();
            
            $currentStep->update([
                'status' => 'rejected',
                'approver_employee_id' => $approver->id,
                'notes' => $notes
            ]);

            $request->update(['status' => 'rejected']);
            if ($request->document && method_exists($request->document, 'update')) {
                $request->document->update(['status' => 'rejected']);
            }

            return true;
        });
    }
}
