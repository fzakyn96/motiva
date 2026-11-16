<?php

namespace App\Services\Approval\Resolvers;

use App\Models\ApprovalRequest;
use App\Models\Employee;
use App\Services\Approval\AuthorityResolver;

class DivisionHeadAuthorityResolver implements AuthorityResolver
{
    public function hasAuthority(ApprovalRequest $request, Employee $employee): bool
    {
        $requester = $request->requester;
        if ($requester && (int) $requester->id === (int) $employee->id) {
            return false;
        }

        if ($employee->user && ($employee->user->hasRole('Superadmin') || $employee->user->hasRole('Admin'))) {
            return true;
        }

        if ($employee->user && $employee->user->hasRole('Division Head')) {
            return true;
        }

        if ($employee->position && (
            (int) $employee->position->level === 0
            || str_contains(strtoupper($employee->position->name), 'DIVISION HEAD')
            || str_contains(strtoupper($employee->position->code), 'DIV')
        )) {
            return true;
        }

        return false;
    }

    public function resolveApprovers(ApprovalRequest $request): mixed
    {
        $step1Approver = $request->steps()
            ->where('status', 'approved')
            ->whereNotNull('approver_employee_id')
            ->with('approver')
            ->first()
            ?->approver;

        if ($step1Approver?->manager_id) {
            return $step1Approver->manager_id;
        }

        return Employee::where(function ($query) {
            $query->whereHas('position', fn ($q) => $q->where('level', 0)
                ->orWhere('name', 'like', '%Division Head%'))
                ->orWhereHas('user.roles', fn ($q) => $q->where('name', 'Division Head'));
        })->pluck('id')->toArray();
    }
}

