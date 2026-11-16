<?php

namespace App\Filament\Admin\Widgets;

use App\Filament\Admin\Pages\SignatureSettingsPage;
use App\Filament\Admin\Resources\SppdResource;
use App\Models\Sppd;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class StaffOverviewWidget extends BaseWidget
{
    protected static ?string $pollingInterval = '30s';
    protected static ?int $sort = 1;

    protected int | string | array $columnSpan = 'full';

    public static function canView(): bool
    {
        $user = \Filament\Facades\Filament::auth()->user() ?? auth()->user();
        if (! $user) {
            return false;
        }

        return ! $user->hasAnyRole(['Manager', 'Supervisor', 'Superadmin', 'Admin', 'HR']);
    }

    protected function getStats(): array
    {
        $user = \Filament\Facades\Filament::auth()->user() ?? auth()->user();
        $employee = $user?->employee;

        if (! $employee) {
            return [];
        }

        // 1. SPPD Saya
        $sppdQuery = Sppd::where('employee_id', $employee->id);
        $totalSppd = (clone $sppdQuery)->count();
        $approvedSppd = (clone $sppdQuery)->where('status', 'approved')->count();
        $draftSppd = (clone $sppdQuery)->where('status', 'draft')->count();
        $pendingSppd = (clone $sppdQuery)->where('status', 'pending')->count();

        // 2. Status Tanda Tangan
        $hasSignature = $user->hasConfiguredSignature();

        return [
            Stat::make('Total SPPD Saya', $totalSppd)
                ->description($draftSppd > 0
                    ? "{$draftSppd} Draf perlu dilengkapi • {$pendingSppd} Proses"
                    : "{$approvedSppd} SPPD telah disetujui")
                ->descriptionIcon($draftSppd > 0 ? 'heroicon-m-exclamation-circle' : 'heroicon-m-paper-airplane')
                ->color($draftSppd > 0 ? 'warning' : 'info')
                ->chart([1, 2, 3, 4, $totalSppd])
                ->url(SppdResource::getUrl('index')),

            Stat::make('SPPD Disetujui', $approvedSppd)
                ->description($pendingSppd > 0
                    ? "{$pendingSppd} Menunggu persetujuan atasan"
                    : 'Siap dicetak & digunakan dinas')
                ->descriptionIcon('heroicon-m-check-badge')
                ->color('success')
                ->chart([1, 2, 2, 3, $approvedSppd])
                ->url(SppdResource::getUrl('index')),

            Stat::make('Tanda Tangan Digital', $hasSignature ? 'Siap Digunakan' : 'Belum Diatur')
                ->description($hasSignature
                    ? 'Metode: ' . $user->getSignatureMethodLabel()
                    : 'Klik untuk mengatur tanda tangan digital')
                ->descriptionIcon($hasSignature ? 'heroicon-m-check-badge' : 'heroicon-m-identification')
                ->color($hasSignature ? 'success' : 'danger')
                ->url(SignatureSettingsPage::getUrl()),
        ];
    }
}
