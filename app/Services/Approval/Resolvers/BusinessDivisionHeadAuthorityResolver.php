<?php

namespace App\Services\Approval\Resolvers;

use App\Models\ApprovalRequest;
use App\Models\Employee;
use App\Services\Approval\AuthorityResolver;

class BusinessDivisionHeadAuthorityResolver implements AuthorityResolver
{
    public function hasAuthority(ApprovalRequest $request, Employee $employee): bool
    {
        if ($employee->user && ($employee->user->hasRole('Superadmin') || $employee->user->hasRole('Admin'))) {
            return true;
        }

        if ($employee->isBusinessDivisionHead()) {
            return true;
        }

        $employee->loadMissing(['organization', 'user.roles']);
        $orgCode = strtoupper((string) ($employee->organization?->code ?? ''));
        if (in_array($orgCode, ['PKJ', 'PERKASA'], true) && $employee->user?->hasRole('Division Head')) {
            return true;
        }

        return false;
    }

    public function resolveApprovers(ApprovalRequest $request): mixed
    {
        return Employee::whereHas('position', function ($q) {
            $q->whereIn('code', ['BDH', 'BDH-PKJ'])
                ->orWhere('name', 'like', '%Business Division Head%');
        })->pluck('id')->toArray();
    }
}
