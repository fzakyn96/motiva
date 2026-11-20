<?php

namespace App\Filament\Admin\Widgets;

use App\Filament\Admin\Resources\MemoApprovalResource;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class MemoOverviewWidget extends BaseWidget
{
    protected static ?string $pollingInterval = '30s';
    protected static ?int $sort = 1;

    public static function canView(): bool
    {
        $user = \Filament\Facades\Filament::auth()->user() ?? auth()->user();
        if (! $user) {
            return false;
        }

        return $user->hasRole(['Manager', 'Superadmin', 'Admin', 'HR']);
    }

    protected function getStats(): array
    {
        $baseQuery = MemoApprovalResource::getEloquentQuery();

        $pendingCount = (clone $baseQuery)->where('status', 'pending')->count();
        $approvedCount = (clone $baseQuery)->where('status', 'approved')->count();
        $rejectedCount = (clone $baseQuery)->where('status', 'rejected')->count();
        $totalCount = (clone $baseQuery)->count();

        return [
            Stat::make('Menunggu Persetujuan', $pendingCount)
                ->extraAttributes(['class' => 'motiva-hide-mobile-stat-card hidden sm:block'])
                ->description($pendingCount > 0 ? 'Review segera' : 'Semua telah ditinjau')
                ->descriptionIcon($pendingCount > 0 ? 'heroicon-m-clock' : 'heroicon-m-check-circle')
                ->color($pendingCount > 0 ? 'warning' : 'gray')
                ->url(MemoApprovalResource::getUrl('index', ['activeTab' => 'pending'])),

            Stat::make('Disetujui', $approvedCount)
                ->extraAttributes(['class' => 'motiva-hide-mobile-stat-card hidden sm:block'])
                ->description('Pengajuan disetujui')
                ->descriptionIcon('heroicon-m-check-badge')
                ->color('success')
                ->url(MemoApprovalResource::getUrl('index', ['activeTab' => 'approved'])),

            Stat::make('Ditolak', $rejectedCount)
                ->extraAttributes(['class' => 'motiva-hide-mobile-stat-card hidden sm:block'])
                ->description('Pengajuan ditolak')
                ->descriptionIcon('heroicon-m-x-circle')
                ->color($rejectedCount > 0 ? 'danger' : 'gray')
                ->url(MemoApprovalResource::getUrl('index', ['activeTab' => 'rejected'])),

            Stat::make('Total Pengajuan', $totalCount)
                ->extraAttributes(['class' => 'motiva-hide-mobile-stat-card hidden sm:block'])
                ->description('Total pengajuan')
                ->descriptionIcon('heroicon-m-document-duplicate')
                ->color('primary')
                ->url(MemoApprovalResource::getUrl('index', ['activeTab' => 'all'])),
        ];
    }
}
