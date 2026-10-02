<?php

namespace App\Services\Approval;

use App\Models\ApprovalRequest;
use App\Models\Employee;

interface AuthorityResolver
{
    /**
     * Determine if the given employee has the authority to approve the request based on this resolver's rule.
     */
    public function hasAuthority(ApprovalRequest $request, Employee $employee): bool;

    /**
     * Get the designated approver(s) for the request based on this resolver's rule, if deterministic.
     * Returns an array of Employee IDs or a single Employee ID, or null if it's purely role-based and not specific to a user.
     */
    public function resolveApprovers(ApprovalRequest $request): mixed;
}
