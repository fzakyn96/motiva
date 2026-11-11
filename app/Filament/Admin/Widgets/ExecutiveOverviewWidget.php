<?php

namespace App\Filament\Admin\Widgets;

use App\Filament\Admin\Resources\MemoApprovalResource;
use App\Filament\Admin\Resources\ReimbursementApprovalResource;
use App\Filament\Admin\Resources\ReimbursementResource;
use App\Filament\Admin\Resources\SppdApprovalResource;
use App\Filament\Admin\Resources\SppdCostResource;
use App\Filament\Admin\Resources\SppdResource;
use App\Models\ApprovalRequest;
use App\Models\Memo;
use App\Models\Reimbursement;
use App\Models\Sppd;
use App\Models\SppdCost;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Database\Eloquent\Builder;

class ExecutiveOverviewWidget extends BaseWidget
{
    protected static ?string $pollingInterval = '30s';
    protected static ?int $sort = 1;

    protected int | string | array $columnSpan = 'full';

    public static function canView(): bool
    {
        $user = \Filament\Facades\Filament::auth()->user() ?? auth()->user();
        if (! $user) return false;
        return $user->hasAnyRole(['Manager', 'Supervisor', 'Superadmin', 'Admin', 'HR']);
    }

    protected function getStats(): array
    {
        $user = \Filament\Facades\Filament::auth()->user() ?? auth()->user();
        $employee = $user?->employee;
        $isAllAccess = ! $user || $user->hasRole(['Superadmin', 'Admin', 'HR']);

        // 1. Pending Approvals
        $pendingMemo = ApprovalRequest::where('document_type', Memo::class)->where('status', 'pending');
        $pendingSppd = ApprovalRequest::where('document_type', Sppd::class)->where('status', 'pending');
        $pendingReimb = ApprovalRequest::where('document_type', Reimbursement::class)->where('status', 'pending');

        if (! $isAllAccess && $employee) {
            $filterByEmp = function (Builder $query, string $docClass) use ($employee) {
                $query->whereHasMorph('document', [$docClass], function (Builder $q) use ($employee) {
                    $q->whereHas('employee', function (Builder $eq) use ($employee) {
                        $eq->where('department_id', $employee->department_id)
                            ->orWhere('manager_id', $employee->id)
                            ->orWhere('id', $employee->id);
                    });
                });
            };

            $filterMemoByEmp = function (Builder $query) use ($employee) {
                $query->whereHasMorph('document', [Memo::class], function (Builder $q) use ($employee) {
                    $q->where('department_id', $employee->department_id)
                        ->orWhere('creator_employee_id', $employee->id)
                        ->orWhereHas('creator', function (Builder $cq) use ($employee) {
                            $cq->where('manager_id', $employee->id);
                        });
                });
            };

            $filterMemoByEmp($pendingMemo);
            $filterByEmp($pendingSppd, Sppd::class);
            $filterByEmp($pendingReimb, Reimbursement::class);
        }

        $cntPendingMemo = $pendingMemo->count();
        $cntPendingSppd = $pendingSppd->count();
        $cntPendingReimb = $pendingReimb->count();

        $isSupervisorOnly = $user?->hasRole('Supervisor') && ! $user->hasAnyRole(['Manager', 'Superadmin', 'Admin', 'HR']);
        $totalPending = $isSupervisorOnly ? $cntPendingSppd : ($cntPendingMemo + $cntPendingSppd + $cntPendingReimb);

        // 2. SPPD Stats
        $sppdQuery = Sppd::query();
        if (! $isAllAccess && $employee) {
            $sppdQuery->whereHas('employee', function (Builder $q) use ($employee) {
                $q->where('department_id', $employee->department_id)
                    ->orWhere('manager_id', $employee->id)
                    ->orWhere('id', $employee->id);
            });
        }
        $approvedSppdCount = (clone $sppdQuery)->where('status', 'approved')->count();
        $pendingSppdDocCount = (clone $sppdQuery)->where('status', 'pending')->count();
        $uncalculatedSppdCount = (clone $sppdQuery)->where('status', 'approved')->doesntHave('cost')->count();
        $totalSppdCount = (clone $sppdQuery)->count();

        // 3. Biaya SPPD
        $sppdCostQuery = SppdCost::whereHas('sppd', function (Builder $q) use ($isAllAccess, $employee) {
            $q->where('status', 'approved');
            if (! $isAllAccess && $employee) {
                $q->whereHas('employee', function (Builder $eq) use ($employee) {
                    $eq->where('department_id', $employee->department_id)
                        ->orWhere('manager_id', $employee->id)
                        ->orWhere('id', $employee->id);
                });
            }
        });
        $totalSppdCost = (float) $sppdCostQuery->sum('total_cost');

        // 4. Reimbursement Stats
        $reimbQuery = Reimbursement::query();
        if (! $isAllAccess && $employee) {
            $reimbQuery->whereHas('employee', function (Builder $q) use ($employee) {
                $q->where('department_id', $employee->department_id)
                    ->orWhere('manager_id', $employee->id)
                    ->orWhere('id', $employee->id);
            });
        }
        $totalClaimApproved = (float) (clone $reimbQuery)->where('status', 'approved')->sum('total_claim_amount');
        $totalClaimPending = (float) (clone $reimbQuery)->where('status', 'pending')->sum('total_claim_amount');
        $cntReimbPending = (clone $reimbQuery)->where('status', 'pending')->count();
        $cntReimbApproved = (clone $reimbQuery)->where('status', 'approved')->count();

        // Target URLs berdasarkan peran
        $approvalUrl = $isSupervisorOnly
            ? SppdApprovalResource::getUrl('index')
            : ($cntPendingMemo > 0
                ? MemoApprovalResource::getUrl('index')
                : ($cntPendingSppd > 0 ? SppdApprovalResource::getUrl('index') : ReimbursementApprovalResource::getUrl('index')));

        $sppdUrl = $isAllAccess
            ? SppdResource::getUrl('index')
            : SppdApprovalResource::getUrl('index');

        $costUrl = $isAllAccess
            ? SppdCostResource::getUrl('index')
            : $sppdUrl;

        $reimbUrl = $isSupervisorOnly
            ? null
            : ($user?->hasRole('Manager') ? ReimbursementApprovalResource::getUrl('index') : ReimbursementResource::getUrl('index'));

        return [
            Stat::make('Antrian Persetujuan', $totalPending)
                ->description($isSupervisorOnly
                    ? ($totalPending > 0 ? "{$cntPendingSppd} SPPD menunggu verifikasi Anda" : 'Seluruh SPPD telah diverifikasi')
                    : ($totalPending > 0 ? "{$cntPendingMemo} Memo • {$cntPendingSppd} SPPD • {$cntPendingReimb} Reimb" : 'Seluruh permohonan telah ditinjau'))
                ->descriptionIcon($totalPending > 0 ? 'heroicon-m-clock' : 'heroicon-m-check-circle')
                ->color($totalPending > 0 ? 'warning' : 'success')
                ->chart($totalPending > 0 ? [3, 5, 2, 7, 4, $totalPending] : [1, 2, 0, 0, 0])
                ->url($approvalUrl),

            Stat::make('Perjalanan Dinas (SPPD)', $approvedSppdCount)
                ->description("Dari total {$totalSppdCount} penugasan dinas" . ($pendingSppdDocCount > 0 ? " ({$pendingSppdDocCount} proses)" : ''))
                ->descriptionIcon('heroicon-m-paper-airplane')
                ->color('info')
                ->chart([2, 4, 3, 6, 8, $approvedSppdCount])
                ->url($sppdUrl),

            Stat::make('Realisasi Biaya SPPD', 'Rp ' . number_format($totalSppdCost, 0, ',', '.'))
                ->description($uncalculatedSppdCount > 0
                    ? "{$uncalculatedSppdCount} SPPD belum dihitung • Klik kelola"
                    : 'Total kalkulasi biaya dinas disetujui')
                ->descriptionIcon($uncalculatedSppdCount > 0 ? 'heroicon-m-exclamation-circle' : 'heroicon-m-calculator')
                ->color($uncalculatedSppdCount > 0 && $isAllAccess ? 'warning' : 'primary')
                ->chart([1, 3, 2, 5, 4, $approvedSppdCount])
                ->url($costUrl),

            Stat::make('Klaim Reimbursement', 'Rp ' . number_format($totalClaimApproved, 0, ',', '.'))
                ->description("{$cntReimbApproved} Disetujui • Rp " . number_format($totalClaimPending, 0, ',', '.') . " Pending")
                ->descriptionIcon('heroicon-m-banknotes')
                ->color($cntReimbPending > 0 ? 'warning' : 'success')
                ->chart([1, 2, 4, 3, 5, $cntReimbApproved])
                ->url($reimbUrl),
        ];
    }
}
