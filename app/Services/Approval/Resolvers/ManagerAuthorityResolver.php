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
        if (! $requester) {
            return false;
        }

        // An employee cannot approve their own request
        if ((int) $requester->id === (int) $employee->id) {
            return false;
        }

        // 1. Superadmin & Admin bypass
        if ($employee->user && ($employee->user->hasRole('Superadmin') || $employee->user->hasRole('Admin'))) {
            return true;
        }

        // 2. User has Manager role
        if ($employee->user && $employee->user->hasRole('Manager')) {
            return true;
        }

        // 3. Employee position is Manager (Level 1 or code/name)
        if ($employee->position && ((int) $employee->position->level === 1 || str_contains(strtoupper($employee->position->name), 'MANAJER') || str_contains(strtoupper($employee->position->name), 'MANAGER') || strtoupper($employee->position->code) === 'MGR')) {
            return true;
        }

        // 4. Check hierarchy
        $requester = $request->requester;
        if (! $requester) {
            return false;
        }

        // Grand-manager (Manager of the Supervisor)
        if ($requester->manager && (int) $requester->manager->manager_id === (int) $employee->id) {
            return true;
        }

        // Direct manager is allowed ONLY IF they are not a Supervisor (level 2 or Supervisor role without Manager role)
        $isSupervisor = ($employee->position && (int) $employee->position->level === 2)
            || ($employee->user && $employee->user->hasRole('Supervisor') && ! $employee->user->hasRole('Manager'));

        if (! $isSupervisor && (int) $requester->manager_id === (int) $employee->id) {
            return true;
        }

        return false;
    }

    public function resolveApprovers(ApprovalRequest $request): mixed
    {
        $requester = $request->requester;
        if ($requester?->manager) {
            if ($requester->manager->position?->level === 1) {
                return $requester->manager_id;
            }
            if ($requester->manager->manager_id) {
                return $requester->manager->manager_id;
            }
        }

        return $request->requester?->manager_id;
    }
}
