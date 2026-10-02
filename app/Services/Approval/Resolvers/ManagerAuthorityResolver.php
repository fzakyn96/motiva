<?php

namespace App\Services\Approval\Resolvers;

use App\Models\ApprovalRequest;
use App\Models\Employee;
use App\Services\Approval\AuthorityResolver;

class ManagerAuthorityResolver implements AuthorityResolver
{
    public function hasAuthority(ApprovalRequest $request, Employee $employee): bool
    {
        $requester = $request->requester;
        // In a real app, this might find the manager of the requester up the chain.
        // For simplicity, we just check if the employee is the manager of the requester.
        return $requester && (int)$requester->manager_id === (int)$employee->id;
    }

    public function resolveApprovers(ApprovalRequest $request): mixed
    {
        return $request->requester?->manager_id;
    }
}
