<?php

namespace App\Filament\Admin\Widgets;

use App\Models\ApprovalRequest;
use App\Models\Memo;
use App\Models\Reimbursement;
use App\Models\Sppd;
use Filament\Widgets\Widget;
use Illuminate\Database\Eloquent\Builder;

class DashboardWelcomeBannerWidget extends Widget
{
    protected static string $view = 'filament.widgets.dashboard-welcome-banner';

    protected static ?int $sort = 0;

    protected int | string | array $columnSpan = 'full';

    public function getBannerData(): array
    {
        /** @var \App\Models\User|null $user */
        $user = \Filament\Facades\Filament::auth()->user() ?? auth()->user();
        $employee = $user?->employee;
        $isExecutive = $user && $user->hasAnyRole(['Superadmin', 'Admin', 'HR', 'Manager', 'Supervisor']);
        $isAllAccess = $user && $user->hasAnyRole(['Superadmin', 'Admin', 'HR']);

        $pendingMemo = 0;
        $pendingSppd = 0;
        $pendingReimb = 0;
        $uncalculatedSppdCost = 0;
        $draftSppdForStaff = 0;

        if ($isExecutive) {
            $qMemo = ApprovalRequest::where('document_type', Memo::class)->where('status', 'pending');
            $qSppd = ApprovalRequest::where('document_type', Sppd::class)->where('status', 'pending');
            $qReimb = ApprovalRequest::where('document_type', Reimbursement::class)->where('status', 'pending');

            if (! $isAllAccess && $employee) {
                $qMemo->whereHasMorph('document', [Memo::class], function (Builder $q) use ($employee) {
                    $q->where('department_id', $employee->department_id)
                        ->orWhere('creator_employee_id', $employee->id)
                        ->orWhereHas('creator', fn (Builder $cq) => $cq->where('manager_id', $employee->id));
                });

                $filterEmp = function (Builder $query, string $docClass) use ($employee) {
                    $query->whereHasMorph('document', [$docClass], function (Builder $q) use ($employee) {
                        $q->whereHas('employee', function (Builder $eq) use ($employee) {
                            $eq->where('department_id', $employee->department_id)
                                ->orWhere('manager_id', $employee->id)
                                ->orWhere('id', $employee->id);
                        });
                    });
                };

                $filterEmp($qSppd, Sppd::class);
                $filterEmp($qReimb, Reimbursement::class);
            }

            $pendingMemo = $qMemo->count();
            $pendingSppd = $qSppd->count();
            $pendingReimb = $qReimb->count();

            if ($isAllAccess) {
                $uncalculatedSppdCost = Sppd::where('status', 'approved')->whereDoesntHave('cost')->count();
            }
        } elseif ($employee) {
            $draftSppdForStaff = Sppd::where('employee_id', $employee->id)->where('status', 'draft')->count();
        }

        return [
            'user' => $user,
            'employee' => $employee,
            'isExecutive' => $isExecutive,
            'isAllAccess' => $isAllAccess,
            'isManager' => $user?->hasRole('Manager') ?? false,
            'isSupervisor' => $user?->hasRole('Supervisor') ?? false,
            'isHrOrAdmin' => $isAllAccess,
            'isStaff' => $user && $user->hasRole('Staff') && ! $isExecutive,
            'hasSignature' => $user?->hasConfiguredSignature() ?? false,
            'pendingMemo' => $pendingMemo,
            'pendingSppd' => $pendingSppd,
            'pendingReimb' => $pendingReimb,
            'uncalculatedSppdCost' => $uncalculatedSppdCost,
            'draftSppdForStaff' => $draftSppdForStaff,
        ];
    }
}

