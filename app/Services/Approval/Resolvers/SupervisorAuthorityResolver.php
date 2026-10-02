<?php

namespace App\Services\Approval\Resolvers;

use App\Models\ApprovalRequest;
use App\Models\Employee;
use App\Services\Approval\AuthorityResolver;

class SupervisorAuthorityResolver implements AuthorityResolver
{
    public function hasAuthority(ApprovalRequest $request, Employee $employee): bool
    {
        $requester = $request->requester;
        // In a real application, there would be logic to determine the supervisor
        // For example, finding an employee with 'Supervisor' position in the same department
        
        if (!$requester || !$requester->department_id) {
            return false;
        }

        // Simplification for the example: assume level 2 is Supervisor in the position table
        return (int)$employee->department_id === (int)$requester->department_id 
            && $employee->position && $employee->position->level === 2;
    }

    public function resolveApprovers(ApprovalRequest $request): mixed
    {
        $requester = $request->requester;
        if (!$requester || !$requester->department_id) {
            return null;
        }

        return Employee::where('department_id', $requester->department_id)
            ->whereHas('position', function ($query) {
                $query->where('level', 2); // Level 2 = Supervisor
            })->pluck('id')->toArray();
    }
}
