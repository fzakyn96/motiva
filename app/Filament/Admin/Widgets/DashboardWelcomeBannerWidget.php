<?php

namespace App\Filament\Admin\Widgets;

use App\Models\ApprovalRequest;
use App\Models\Memo;
use App\Models\OfficialTravelRecap;
use App\Models\Sppd;
use App\Models\SppdCost;
use App\Models\TransportAccommodationRecap;
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
        $isBusinessDivisionHead = (bool) $employee?->isBusinessDivisionHead();
        $isExecutive = $user && ($user->hasAnyRole(['Superadmin', 'Admin', 'HR', 'Division Head', 'Manager', 'Supervisor']) || $isBusinessDivisionHead);
        $isAllAccess = $user && ($user->hasAnyRole(['Superadmin', 'Admin', 'Division Head']) || $isBusinessDivisionHead || ($user->hasRole('HR') && ! $employee?->work_area_id));
        $isHrOrAdmin = $user && $user->hasAnyRole(['Superadmin', 'Admin', 'HR']);
        $isManager = ($user?->hasRole('Manager') ?? false) && ! $isBusinessDivisionHead;

        $pendingMemo = 0;
        $pendingSppd = 0;
        $pendingSppdCost = 0;
        $pendingTransportRecap = 0;
        $pendingTravelRecap = 0;
        $uncalculatedSppdCost = 0;
        $draftSppdForStaff = 0;

        if ($isExecutive) {
            $qMemo = ApprovalRequest::where('document_type', Memo::class)->where('status', 'pending');
            $qSppd = \App\Filament\Admin\Resources\SppdApprovalResource::applyActionablePendingScope(
                ApprovalRequest::where('document_type', Sppd::class),
                $user
            );
            $qSppdCost = ApprovalRequest::where('document_type', SppdCost::class)->where('status', 'pending');
            $qTransport = ApprovalRequest::where('document_type', TransportAccommodationRecap::class)->where('status', 'pending');
            $qTravel = ApprovalRequest::where('document_type', OfficialTravelRecap::class)->where('status', 'pending');

            if (! $isAllAccess && $employee) {
                if ($employee->work_area_id) {
                    $qMemo->where(function (Builder $q) use ($employee) {
                        $q->whereHas('requester', fn (Builder $rq) => $rq->where('work_area_id', $employee->work_area_id))
                            ->orWhereHasMorph('document', [Memo::class], fn (Builder $mq) => $mq->whereHas('creator', fn (Builder $cq) => $cq->where('work_area_id', $employee->work_area_id)));
                    });

                    $qSppd->whereHasMorph('document', [Sppd::class], function (Builder $q) use ($employee) {
                        $q->whereHas('employee', fn (Builder $eq) => $eq->where('work_area_id', $employee->work_area_id));
                    });

                    $qSppdCost->whereHasMorph('document', [SppdCost::class], function (Builder $q) use ($employee) {
                        $q->whereHas('sppd.employee', fn (Builder $eq) => $eq->where('work_area_id', $employee->work_area_id));
                    });

                    if (! $isManager) {
                        $qTransport->where(function (Builder $q) use ($employee) {
                            $q->whereHas('requester', fn (Builder $rq) => $rq->where('work_area_id', $employee->work_area_id))
                                ->orWhereHasMorph('document', [TransportAccommodationRecap::class], fn (Builder $dq) => $dq->whereHas('businessTrip.sppds.employee', fn (Builder $eq) => $eq->where('work_area_id', $employee->work_area_id)));
                        });

                        $qTravel->where(function (Builder $q) use ($employee) {
                            $q->whereHas('requester', fn (Builder $rq) => $rq->where('work_area_id', $employee->work_area_id))
                                ->orWhereHasMorph('document', [OfficialTravelRecap::class], fn (Builder $dq) => $dq->whereHas('businessTrip.sppds.employee', fn (Builder $eq) => $eq->where('work_area_id', $employee->work_area_id)));
                        });
                    }
                } else {
                    $qMemo->whereHasMorph('document', [Memo::class], function (Builder $q) use ($employee) {
                        $q->where('department_id', $employee->department_id)
                            ->orWhere('creator_employee_id', $employee->id)
                            ->orWhereHas('creator', fn (Builder $cq) => $cq->where('manager_id', $employee->id));
                    });

                    $qSppd->whereHasMorph('document', [Sppd::class], function (Builder $q) use ($employee) {
                        $q->whereHas('employee', function (Builder $eq) use ($employee) {
                            $eq->where('department_id', $employee->department_id)
                                ->orWhere('manager_id', $employee->id)
                                ->orWhere('id', $employee->id);
                        });
                    });

                    $qSppdCost->whereHasMorph('document', [SppdCost::class], function (Builder $q) use ($employee) {
                        $q->whereHas('sppd.employee', function (Builder $eq) use ($employee) {
                            $eq->where('department_id', $employee->department_id)
                                ->orWhere('manager_id', $employee->id)
                                ->orWhere('id', $employee->id);
                        });
                    });

                    if ($employee->department_id) {
                        $qTransport->whereHasMorph('document', [TransportAccommodationRecap::class], fn (Builder $q) => $q->where('department_id', $employee->department_id));
                        $qTravel->whereHasMorph('document', [OfficialTravelRecap::class], fn (Builder $q) => $q->where('department_id', $employee->department_id));
                    }
                }
            }

            $pendingMemo = $qMemo->count();
            $pendingSppd = $qSppd->count();
            $pendingSppdCost = $qSppdCost->count();
            $pendingTransportRecap = $qTransport->count();
            $pendingTravelRecap = $qTravel->count();

            if ($isHrOrAdmin) {
                $uncalculatedQuery = Sppd::where('status', 'approved')->whereDoesntHave('cost');
                if (! $isAllAccess && $employee?->work_area_id) {
                    $uncalculatedQuery->whereHas('employee', fn (Builder $eq) => $eq->where('work_area_id', $employee->work_area_id));
                }
                $uncalculatedSppdCost = $uncalculatedQuery->count();
            }
        } elseif ($employee) {
            $draftSppdForStaff = Sppd::where('employee_id', $employee->id)->where('status', 'draft')->count();
        }

        return [
            'user' => $user,
            'employee' => $employee,
            'isExecutive' => $isExecutive,
            'isAllAccess' => $isAllAccess,
            'isBusinessDivisionHead' => $isBusinessDivisionHead,
            'isDivisionHead' => (($user?->hasRole('Division Head') ?? false) || $isBusinessDivisionHead) && ! $isHrOrAdmin,
            'isManager' => ($user?->hasRole('Manager') ?? false) && ! $isBusinessDivisionHead,
            'isSupervisor' => $user?->hasRole('Supervisor') ?? false,
            'isHrOrAdmin' => $isHrOrAdmin,
            'isStaff' => $user && $user->hasRole('Staff') && ! $isExecutive,
            'hasSignature' => $user?->hasConfiguredSignature() ?? false,
            'pendingMemo' => $pendingMemo,
            'pendingSppd' => $pendingSppd,
            'pendingSppdCost' => $pendingSppdCost,
            'pendingTransportRecap' => $pendingTransportRecap,
            'pendingTravelRecap' => $pendingTravelRecap,
            'pendingRecap' => $pendingTransportRecap + $pendingTravelRecap,
            'uncalculatedSppdCost' => $uncalculatedSppdCost,
            'draftSppdForStaff' => $draftSppdForStaff,
        ];
    }
}

