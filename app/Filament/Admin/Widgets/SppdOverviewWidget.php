<?php

namespace App\Filament\Admin\Widgets;

use App\Filament\Admin\Resources\SppdApprovalResource;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class SppdOverviewWidget extends BaseWidget
{
    protected static ?string $pollingInterval = '30s';
    protected static ?int $sort = 2;

    public static function canView(): bool
    {
        $user = \Filament\Facades\Filament::auth()->user() ?? auth()->user();
        if (! $user) {
            return false;
        }

        return $user->hasRole(['Supervisor', 'Manager', 'Superadmin', 'Admin', 'HR']);
    }

    protected function getStats(): array
    {
        $baseQuery = SppdApprovalResource::getEloquentQuery();

        $pendingCount = SppdApprovalResource::applyActionablePendingScope(clone $baseQuery)->count();
        $approvedCount = SppdApprovalResource::applySupervisorApprovedScope(clone $baseQuery)->count();
        $rejectedCount = (clone $baseQuery)->where('status', 'rejected')->count();
        $totalCount = (clone $baseQuery)->count();

        return [
            Stat::make('Menunggu Persetujuan', $pendingCount)
                ->description($pendingCount > 0 ? 'Perlu tindakan review segera' : 'Semua SPPD telah ditinjau')
                ->descriptionIcon($pendingCount > 0 ? 'heroicon-m-clock' : 'heroicon-m-check-circle')
                ->color($pendingCount > 0 ? 'warning' : 'gray')
                ->url(SppdApprovalResource::getUrl('index', ['activeTab' => 'pending'])),

            Stat::make('Disetujui', $approvedCount)
                ->description('Pengajuan SPPD disetujui')
                ->descriptionIcon('heroicon-m-check-badge')
                ->color('success')
                ->url(SppdApprovalResource::getUrl('index', ['activeTab' => 'approved'])),

            Stat::make('Ditolak', $rejectedCount)
                ->description('Pengajuan SPPD ditolak')
                ->descriptionIcon('heroicon-m-x-circle')
                ->color($rejectedCount > 0 ? 'danger' : 'gray')
                ->url(SppdApprovalResource::getUrl('index', ['activeTab' => 'rejected'])),

            Stat::make('Total Pengajuan', $totalCount)
                ->description('Total persetujuan SPPD')
                ->descriptionIcon('heroicon-m-document-duplicate')
                ->color('primary')
                ->url(SppdApprovalResource::getUrl('index', ['activeTab' => 'all'])),
        ];
    }
}
