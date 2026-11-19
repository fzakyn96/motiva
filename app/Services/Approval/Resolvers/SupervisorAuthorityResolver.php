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

        // 2. Direct supervisor / manager of the requester
        if ($requester->manager_id && (int) $requester->manager_id === (int) $employee->id) {
            return true;
        }

        // 3. Supervisor in the same department (Position level 2 or name/code Supervisor)
        if ($requester->department_id && (int) $employee->department_id === (int) $requester->department_id) {
            if ($employee->position && ((int) $employee->position->level === 2 || str_contains(strtoupper($employee->position->name), 'SUPERVISOR') || strtoupper($employee->position->code) === 'SPV')) {
                return true;
            }

            if ($employee->user && $employee->user->hasRole('Supervisor')) {
                return true;
            }
        }

        // 4. User role Supervisor in general
        if ($employee->user && $employee->user->hasRole('Supervisor')) {
            return true;
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
