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

        // Supervisor hanya berwenang mengambil keputusan pada area kerja yang sama
        if (! $employee->isSameWorkAreaAsRequest($request)) {
            return false;
        }

        // 2. If requester has a direct Supervisor assigned as manager_id, ONLY that direct supervisor has authority
        $directManager = $requester->manager;
        if ($directManager && $this->isSupervisorEmployee($directManager)) {
            return (int) $directManager->id === (int) $employee->id;
        }

        // Direct supervisor / manager of the requester
        if ($requester->manager_id && (int) $requester->manager_id === (int) $employee->id) {
            return true;
        }

        // 3. Fallback: Supervisor in the same department (Position level 2 or name/code Supervisor)
        if ($requester->department_id && (int) $employee->department_id === (int) $requester->department_id) {
            if ($this->isSupervisorEmployee($employee)) {
                return true;
            }
        }

        // 4. Fallback: User role Supervisor in the same work area
        if ($employee->user && $employee->user->hasRole('Supervisor')) {
            return true;
        }

        return false;
    }

    protected function isSupervisorEmployee(Employee $employee): bool
    {
        $employee->loadMissing(['position', 'user.roles']);

        if ($employee->user && $employee->user->hasRole('Supervisor')) {
            return true;
        }

        if ($employee->position) {
            $code = strtoupper((string) $employee->position->code);
            $name = strtoupper((string) $employee->position->name);

            if ((int) $employee->position->level === 2 || $code === 'SPV' || str_contains($name, 'SUPERVISOR')) {
                return true;
            }
        }

        return false;
    }

    public function resolveApprovers(ApprovalRequest $request): mixed
    {
        $requester = $request->requester;
        if (! $requester) {
            return null;
        }

        if ($requester->manager_id) {
            return $requester->manager_id;
        }

        if ($requester->department_id) {
            return Employee::where('department_id', $requester->department_id)
                ->when($requester->work_area_id, fn ($q) => $q->where('work_area_id', $requester->work_area_id))
                ->where(function ($query) {
                    $query->whereHas('position', fn ($q) => $q->where('level', 2))
                        ->orWhereHas('user.roles', fn ($q) => $q->where('name', 'Supervisor'));
                })->pluck('id')->toArray();
        }

        return null;
    }
}
