<?php

namespace App\Filament\Admin\Widgets;

use App\Filament\Admin\Resources\MemoApprovalResource;
use App\Filament\Admin\Resources\OfficialTravelApprovalResource;
use App\Filament\Admin\Resources\OfficialTravelRecapResource;
use App\Filament\Admin\Resources\SppdApprovalResource;
use App\Filament\Admin\Resources\SppdCostApprovalResource;
use App\Filament\Admin\Resources\SppdCostResource;
use App\Filament\Admin\Resources\SppdResource;
use App\Filament\Admin\Resources\TransportAccommodationApprovalResource;
use App\Filament\Admin\Resources\TransportAccommodationRecapResource;
use App\Models\ApprovalRequest;
use App\Models\Memo;
use App\Models\OfficialTravelRecap;
use App\Models\Sppd;
use App\Models\SppdCost;
use App\Models\TransportAccommodationRecap;
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
        return $user->hasAnyRole(['Division Head', 'Manager', 'Supervisor', 'Superadmin', 'Admin', 'HR']);
    }

    protected function getStats(): array
    {
        $user = \Filament\Facades\Filament::auth()->user() ?? auth()->user();
        $employee = $user?->employee;
        $isAllAccess = ! $user || $user->hasRole(['Superadmin', 'Admin', 'HR', 'Division Head']);
        $isHrOrAdmin = $user && $user->hasAnyRole(['Superadmin', 'Admin', 'HR']);
        $isDivisionHeadOnly = $user?->hasRole('Division Head') && ! $user->hasAnyRole(['Manager', 'Superadmin', 'Admin', 'HR']);
        $isSupervisorOnly = $user?->hasRole('Supervisor') && ! $user->hasAnyRole(['Manager', 'Division Head', 'Superadmin', 'Admin', 'HR']);

        // 1. Pending Approvals
        $pendingMemo = ApprovalRequest::where('document_type', Memo::class)->where('status', 'pending');
        $pendingSppd = SppdApprovalResource::applyActionablePendingScope(
            ApprovalRequest::where('document_type', Sppd::class),
            $user
        );
        $pendingSppdCost = ApprovalRequest::where('document_type', SppdCost::class)->where('status', 'pending');
        $pendingTransport = ApprovalRequest::where('document_type', TransportAccommodationRecap::class)->where('status', 'pending');
        $pendingTravel = ApprovalRequest::where('document_type', OfficialTravelRecap::class)->where('status', 'pending');

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

            $pendingSppdCost->whereHasMorph('document', [SppdCost::class], function (Builder $q) use ($employee) {
                $q->whereHas('sppd.employee', function (Builder $eq) use ($employee) {
                    $eq->where('department_id', $employee->department_id)
                        ->orWhere('manager_id', $employee->id)
                        ->orWhere('id', $employee->id);
                });
            });

            if ($employee->department_id) {
                $pendingTransport->whereHasMorph('document', [TransportAccommodationRecap::class], fn (Builder $q) => $q->where('department_id', $employee->department_id));
                $pendingTravel->whereHasMorph('document', [OfficialTravelRecap::class], fn (Builder $q) => $q->where('department_id', $employee->department_id));
            }
        }

        $cntPendingMemo = $pendingMemo->count();
        $cntPendingSppd = $pendingSppd->count();
        $cntPendingSppdCost = $pendingSppdCost->count();
        $cntPendingTransport = $pendingTransport->count();
        $cntPendingTravel = $pendingTravel->count();
        $cntPendingRecap = $cntPendingTransport + $cntPendingTravel;

        $totalPending = match (true) {
            $isSupervisorOnly => $cntPendingSppd,
            $isDivisionHeadOnly => $cntPendingRecap,
            default => $cntPendingMemo + $cntPendingSppd + $cntPendingSppdCost + $cntPendingRecap,
        };

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

        // 4. Rekap Tiket & Akomodasi vs Rekap Perjalanan Dinas (terpisah)
        $transportQuery = TransportAccommodationRecap::query();
        $travelQuery = OfficialTravelRecap::query();
        if (! $isAllAccess && $employee?->department_id) {
            $transportQuery->where('department_id', $employee->department_id);
            $travelQuery->where('department_id', $employee->department_id);
        }
        $totalTransportAmount = (float) $transportQuery->sum('grand_total');
        $totalTransportCount = $transportQuery->count();
        $totalTravelAmount = (float) $travelQuery->sum('grand_total');
        $totalTravelCount = $travelQuery->count();

        // Target URLs berdasarkan peran
        $approvalUrl = match (true) {
            $isSupervisorOnly => SppdApprovalResource::getUrl('index'),
            $isDivisionHeadOnly => $cntPendingTransport > 0
                ? TransportAccommodationApprovalResource::getUrl('index')
                : OfficialTravelApprovalResource::getUrl('index'),
            $cntPendingMemo > 0 => MemoApprovalResource::getUrl('index'),
            $cntPendingSppd > 0 => SppdApprovalResource::getUrl('index'),
            $cntPendingSppdCost > 0 => SppdCostApprovalResource::getUrl('index'),
            $cntPendingTransport > 0 => TransportAccommodationApprovalResource::getUrl('index'),
            default => OfficialTravelApprovalResource::getUrl('index'),
        };

        $sppdUrl = $isHrOrAdmin
            ? SppdResource::getUrl('index')
            : SppdApprovalResource::getUrl('index');

        $costUrl = $isHrOrAdmin
            ? SppdCostResource::getUrl('index')
            : ($isSupervisorOnly || $isDivisionHeadOnly ? $sppdUrl : SppdCostApprovalResource::getUrl('index'));

        $transportUrl = $isSupervisorOnly
            ? null
            : ($isHrOrAdmin ? TransportAccommodationRecapResource::getUrl('index') : TransportAccommodationApprovalResource::getUrl('index'));

        $travelUrl = $isSupervisorOnly
            ? null
            : ($isHrOrAdmin ? OfficialTravelRecapResource::getUrl('index') : OfficialTravelApprovalResource::getUrl('index'));

        $approvalDesc = match (true) {
            $isSupervisorOnly => $totalPending > 0 ? "{$cntPendingSppd} SPPD menunggu verifikasi Anda" : 'Seluruh SPPD telah diverifikasi',
            $isDivisionHeadOnly => $totalPending > 0 ? "{$cntPendingTransport} Rekap Tiket • {$cntPendingTravel} Rekap Dinas" : 'Seluruh rekap telah disetujui',
            default => $totalPending > 0 ? "{$cntPendingMemo} Memo • {$cntPendingSppd} SPPD • {$cntPendingSppdCost} Biaya SPPD • {$cntPendingRecap} Rekap" : 'Seluruh permohonan telah ditinjau',
        };

        return [
            Stat::make('Antrian Persetujuan', $totalPending)
                ->description($approvalDesc)
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
                ->color($uncalculatedSppdCount > 0 && $isHrOrAdmin ? 'warning' : 'primary')
                ->chart([1, 3, 2, 5, 4, $approvedSppdCount])
                ->url($costUrl),

            Stat::make('Rekap Tiket & Akomodasi', 'Rp ' . number_format($totalTransportAmount, 0, ',', '.'))
                ->description("{$totalTransportCount} Dokumen • {$cntPendingTransport} Menunggu Persetujuan")
                ->descriptionIcon('heroicon-m-ticket')
                ->color($cntPendingTransport > 0 ? 'warning' : 'info')
                ->chart([1, 2, 3, 2, 4, $totalTransportCount])
                ->url($transportUrl),

            Stat::make('Rekap Perjalanan Dinas', 'Rp ' . number_format($totalTravelAmount, 0, ',', '.'))
                ->description("{$totalTravelCount} Dokumen • {$cntPendingTravel} Menunggu Persetujuan")
                ->descriptionIcon('heroicon-m-document-chart-bar')
                ->color($cntPendingTravel > 0 ? 'warning' : 'success')
                ->chart([1, 2, 4, 3, 5, $totalTravelCount])
                ->url($travelUrl),
        ];
    }
}
