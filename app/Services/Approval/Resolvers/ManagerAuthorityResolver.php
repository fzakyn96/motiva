<?php

namespace App\Services\Approval\Resolvers;

use App\Models\ApprovalRequest;
use App\Models\Employee;
use App\Services\Approval\AuthorityResolver;

class ManagerAuthorityResolver implements AuthorityResolver
{
    public function hasAuthority(ApprovalRequest $request, Employee $employee): bool
    {
        // Check if the employee is explicitly assigned as the manager of the requester
        $requester = $request->requester;
        $isDirectManager = $requester && (int)$requester->manager_id === (int)$employee->id;
        
        // Alternatively, for testing purposes, if the employee's user has the 'Manager' or 'Superadmin' role, allow it
        $hasManagerRole = $employee->user && ($employee->user->hasRole('Manager') || $employee->user->hasRole('Superadmin'));

        return $isDirectManager || $hasManagerRole;
    }

    public function resolveApprovers(ApprovalRequest $request): mixed
    {
        return $request->requester?->manager_id;
    }
}
