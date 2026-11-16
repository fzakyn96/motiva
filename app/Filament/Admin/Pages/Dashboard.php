<?php

namespace App\Filament\Admin\Pages;

use App\Filament\Admin\Widgets\DashboardWelcomeBannerWidget;
use App\Filament\Admin\Widgets\ExecutiveCostSummaryWidget;
use App\Filament\Admin\Widgets\ExecutiveOverviewWidget;
use App\Filament\Admin\Widgets\ExecutivePendingApprovalsWidget;
use App\Filament\Admin\Widgets\ExecutivePipelineChartWidget;
use App\Filament\Admin\Widgets\ExecutiveRecentTripsWidget;
use App\Filament\Admin\Widgets\StaffOverviewWidget;
use App\Filament\Admin\Widgets\StaffRecentTripsWidget;
use Filament\Pages\Dashboard as BaseDashboard;

class Dashboard extends BaseDashboard
{
    protected static ?string $title = 'Dashboard';
    protected static ?string $navigationLabel = 'Dashboard';
    protected static ?string $navigationIcon = 'heroicon-o-home';

    public function getSubheading(): ?string
    {
        $user = \Filament\Facades\Filament::auth()->user() ?? auth()->user();

        // if ($user && $user->hasAnyRole(['Manager', 'Supervisor', 'Superadmin', 'Admin', 'HR'])) {
        //     return 'Executive Command Center • Monitoring Persetujuan, Penugasan SPPD & Realisasi Anggaran Dinas';
        // }

        // return 'Workspace Perjalanan Dinas • Pantau Memo Penugasan, SPPD, dan Klaim Reimbursement Anda';
        return '';
    }

    public function getColumns(): int | string | array
    {
        return [
            'default' => 1,
            'sm' => 1,
            'md' => 2,
            'lg' => 2,
            'xl' => 2,
        ];
    }

    public function getWidgets(): array
    {
        $user = \Filament\Facades\Filament::auth()->user() ?? auth()->user();

        if ($user && $user->hasAnyRole(['Manager', 'Supervisor', 'Superadmin', 'Admin', 'HR'])) {
            return [
                DashboardWelcomeBannerWidget::class,
                ExecutiveOverviewWidget::class,
                ExecutiveCostSummaryWidget::class,
                ExecutivePipelineChartWidget::class,
                ExecutivePendingApprovalsWidget::class,
                ExecutiveRecentTripsWidget::class,
            ];
        }

        return [
            DashboardWelcomeBannerWidget::class,
            StaffOverviewWidget::class,
            StaffRecentTripsWidget::class,
        ];
    }
}
