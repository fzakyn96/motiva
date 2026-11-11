<?php

namespace App\Filament\Admin\Widgets;

use App\Filament\Admin\Pages\SignatureSettingsPage;
use App\Filament\Admin\Resources\MemoResource;
use App\Filament\Admin\Resources\ReimbursementResource;
use App\Filament\Admin\Resources\SppdResource;
use App\Models\Memo;
use App\Models\Reimbursement;
use App\Models\Sppd;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Database\Eloquent\Builder;

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

        // 1. Memo Saya
        $memoQuery = Memo::where(function (Builder $q) use ($employee) {
            $q->where('creator_employee_id', $employee->id)
                ->orWhereHas('businessTrips.sppds', fn (Builder $sq) => $sq->where('employee_id', $employee->id));
        });
        $totalMemo = (clone $memoQuery)->count();
        $pendingMemo = (clone $memoQuery)->where('status', 'pending')->count();
        $draftMemo = (clone $memoQuery)->where('status', 'draft')->count();

        // 2. SPPD Saya
        $sppdQuery = Sppd::where('employee_id', $employee->id);
        $totalSppd = (clone $sppdQuery)->count();
        $approvedSppd = (clone $sppdQuery)->where('status', 'approved')->count();
        $draftSppd = (clone $sppdQuery)->where('status', 'draft')->count();
        $pendingSppd = (clone $sppdQuery)->where('status', 'pending')->count();

        // 3. Reimbursement Saya
        $reimbQuery = Reimbursement::where('employee_id', $employee->id);
        $approvedReimbAmount = (float) (clone $reimbQuery)->where('status', 'approved')->sum('total_claim_amount');
        $pendingReimbCount = (clone $reimbQuery)->where('status', 'pending')->count();
        $draftReimbCount = (clone $reimbQuery)->where('status', 'draft')->count();

        // 4. Status Tanda Tangan
        $hasSignature = $user->hasConfiguredSignature();

        return [
            Stat::make('Memo Penugasan Saya', $totalMemo)
                ->description($pendingMemo > 0 || $draftMemo > 0
                    ? "{$pendingMemo} Menunggu • {$draftMemo} Draf"
                    : 'Seluruh memo penugasan')
                ->descriptionIcon('heroicon-m-document-text')
                ->color($pendingMemo > 0 ? 'warning' : 'primary')
                ->chart([1, 2, 2, 3, $totalMemo])
                ->url(MemoResource::getUrl('index')),

            Stat::make('SPPD Saya', $approvedSppd)
                ->description($draftSppd > 0
                    ? "{$draftSppd} Draf perlu dilengkapi • {$pendingSppd} Proses"
                    : "Dari total {$totalSppd} dokumen SPPD")
                ->descriptionIcon($draftSppd > 0 ? 'heroicon-m-exclamation-circle' : 'heroicon-m-paper-airplane')
                ->color($draftSppd > 0 ? 'warning' : 'info')
                ->chart([1, 2, 3, 4, $approvedSppd])
                ->url(SppdResource::getUrl('index')),

            Stat::make('Klaim Reimbursement Saya', 'Rp ' . number_format($approvedReimbAmount, 0, ',', '.'))
                ->description($pendingReimbCount > 0 || $draftReimbCount > 0
                    ? "{$pendingReimbCount} Diproses • {$draftReimbCount} Draf"
                    : 'Total klaim disetujui')
                ->descriptionIcon('heroicon-m-banknotes')
                ->color($pendingReimbCount > 0 ? 'warning' : 'success')
                ->url(ReimbursementResource::getUrl('index')),

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
