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
        return static::resolveManagerForEmployee(
            $request->requester,
            $request->requester?->department_id
        )?->id;
    }

    public static function isManagerEmployee(?Employee $candidate): bool
    {
        if (! $candidate) {
            return false;
        }

        $candidate->loadMissing(['position', 'user.roles']);

        if ($candidate->user && $candidate->user->hasRole('Manager')) {
            return true;
        }

        $isSupervisorOnly = ($candidate->position && (int) $candidate->position->level === 2)
            || ($candidate->user && $candidate->user->hasRole('Supervisor') && ! $candidate->user->hasRole('Manager'));
        if ($isSupervisorOnly) {
            return false;
        }

        if ($candidate->position) {
            $posName = strtoupper((string) $candidate->position->name);
            $posCode = strtoupper((string) $candidate->position->code);
            if ((int) $candidate->position->level === 1 || str_contains($posName, 'MANAGER') || str_contains($posName, 'MANAJER') || $posCode === 'MGR') {
                return true;
            }
        }

        return false;
    }

    public static function resolveManagerForEmployee(?Employee $employee, ?int $departmentId = null): ?Employee
    {
        $visited = [];
        $current = $employee?->manager;
        $depth = 0;

        while ($current && $depth < 5 && ! in_array($current->id, $visited, true)) {
            $visited[] = $current->id;
            if (static::isManagerEmployee($current)) {
                return $current;
            }
            $current = $current->manager;
            $depth++;
        }

        $deptId = $departmentId ?? $employee?->department_id;
        $managerQuery = fn () => Employee::with(['position', 'user.roles'])
            ->where(function ($q) {
                $q->whereHas('user.roles', fn ($rq) => $rq->where('name', 'Manager'))
                    ->orWhereHas('position', fn ($pq) => $pq->where('level', 1)
                        ->orWhere('name', 'like', '%Manager%')
                        ->orWhere('name', 'like', '%Manajer%')
                        ->orWhere('code', 'MGR'));
            });

        if ($deptId) {
            $deptManager = $managerQuery()->where('department_id', $deptId)->first();
            if ($deptManager) {
                return $deptManager;
            }
        }

        $globalManager = $managerQuery()->first();
        if ($globalManager) {
            return $globalManager;
        }

        return $employee?->manager;
    }
}
