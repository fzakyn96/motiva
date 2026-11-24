<?php

namespace App\Filament\Admin\Pages;

use App\Filament\Admin\Resources\MemoApprovalResource;
use App\Filament\Admin\Resources\MemoResource;
use App\Filament\Admin\Resources\NationalHolidayResource;
use App\Filament\Admin\Resources\OfficialTravelRecapResource;
use App\Filament\Admin\Resources\OvertimeApprovalResource;
use App\Filament\Admin\Resources\OvertimeOrderResource;
use App\Filament\Admin\Resources\OvertimeRecapResource;
use App\Filament\Admin\Resources\SppdApprovalResource;
use App\Filament\Admin\Resources\SppdCostApprovalResource;
use App\Filament\Admin\Resources\SppdCostResource;
use App\Filament\Admin\Resources\SppdResource;
use App\Filament\Admin\Resources\TransportAccommodationRecapResource;
use Filament\Pages\Page;

class WorkflowGuidePage extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-map';

    protected static ?string $navigationLabel = 'Alur Perjalanan Dinas & Lembur';

    protected static ?string $title = 'Alur Perjalanan Dinas & Kerja Lembur';

    protected static ?string $slug = 'workflow-guide';

    protected static ?string $navigationGroup = 'Panduan';

    protected static ?int $navigationSort = 1;

    protected static string $view = 'filament.pages.workflow-guide';

    public static function canAccess(): bool
    {
        $user = (\Filament\Facades\Filament::getCurrentPanel() ? \Filament\Facades\Filament::auth()->user() : null) ?? auth()->user();

        return $user !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function getViewData(): array
    {
        $memoPending = MemoResource::canViewAny()
            ? MemoResource::getEloquentQuery()->where('status', 'pending')->count()
            : 0;
        $memoApprovalPending = MemoApprovalResource::canViewAny()
            ? MemoApprovalResource::getEloquentQuery()->where('status', 'pending')->count()
            : 0;

        $sppdPending = SppdResource::canViewAny()
            ? SppdResource::getEloquentQuery()->where('status', 'pending')->count()
            : 0;
        $sppdApprovalPending = SppdApprovalResource::canViewAny()
            ? SppdApprovalResource::getEloquentQuery()->where('status', 'pending')->count()
            : 0;

        $costCount = SppdCostResource::canViewAny()
            ? SppdCostResource::getEloquentQuery()->count()
            : 0;
        $costApprovalPending = SppdCostApprovalResource::canViewAny()
            ? SppdCostApprovalResource::getEloquentQuery()->where('status', 'pending')->count()
            : 0;

        $transportRecapCount = TransportAccommodationRecapResource::canViewAny()
            ? TransportAccommodationRecapResource::getEloquentQuery()->count()
            : 0;
        $travelRecapCount = OfficialTravelRecapResource::canViewAny()
            ? OfficialTravelRecapResource::getEloquentQuery()->count()
            : 0;

        $otCommandPending = OvertimeOrderResource::canViewAny()
            ? OvertimeOrderResource::getEloquentQuery()->where('status', 'command_pending')->count()
            : 0;
        $otReportAwaitWorker = OvertimeOrderResource::canViewAny()
            ? OvertimeOrderResource::getEloquentQuery()->where('status', 'command_approved')->count()
            : 0;
        $otReportPendingMgr = OvertimeOrderResource::canViewAny()
            ? OvertimeOrderResource::getEloquentQuery()->where('status', 'report_pending')->count()
            : 0;
        $otApprovedCount = OvertimeOrderResource::canViewAny()
            ? OvertimeOrderResource::getEloquentQuery()->where('status', 'approved')->count()
            : 0;
        $otApprovalPending = OvertimeApprovalResource::canViewAny()
            ? OvertimeApprovalResource::getEloquentQuery()->where('status', 'pending')->count()
            : 0;
        $otRecapCount = OvertimeRecapResource::canViewAny()
            ? OvertimeRecapResource::getEloquentQuery()->count()
            : 0;

        return [
            'counts' => [
                'memo_pending' => $memoPending,
                'memo_approval_pending' => $memoApprovalPending,
                'sppd_pending' => $sppdPending,
                'sppd_approval_pending' => $sppdApprovalPending,
                'cost_total' => $costCount,
                'cost_approval_pending' => $costApprovalPending,
                'transport_recap_total' => $transportRecapCount,
                'travel_recap_total' => $travelRecapCount,
                'ot_command_pending' => $otCommandPending,
                'ot_report_await_worker' => $otReportAwaitWorker,
                'ot_report_pending_mgr' => $otReportPendingMgr,
                'ot_approved' => $otApprovedCount,
                'ot_approval_pending' => $otApprovalPending,
                'ot_recap_total' => $otRecapCount,
            ],
            'links' => [
                'memos' => MemoResource::canViewAny() ? MemoResource::getUrl('index') : null,
                'memo_approvals' => MemoApprovalResource::canViewAny() ? MemoApprovalResource::getUrl('index') : null,
                'sppds' => SppdResource::canViewAny() ? SppdResource::getUrl('index') : null,
                'sppd_approvals' => SppdApprovalResource::canViewAny() ? SppdApprovalResource::getUrl('index') : null,
                'sppd_costs' => SppdCostResource::canViewAny() ? SppdCostResource::getUrl('index') : null,
                'sppd_cost_approvals' => SppdCostApprovalResource::canViewAny() ? SppdCostApprovalResource::getUrl('index') : null,
                'transport_recaps' => TransportAccommodationRecapResource::canViewAny() ? TransportAccommodationRecapResource::getUrl('index') : null,
                'travel_recaps' => OfficialTravelRecapResource::canViewAny() ? OfficialTravelRecapResource::getUrl('index') : null,
                'overtimes' => OvertimeOrderResource::canViewAny() ? OvertimeOrderResource::getUrl('index') : null,
                'overtime_approvals' => OvertimeApprovalResource::canViewAny() ? OvertimeApprovalResource::getUrl('index') : null,
                'overtime_recaps' => OvertimeRecapResource::canViewAny() ? OvertimeRecapResource::getUrl('index') : null,
                'overtime_settings' => OvertimeSettingsPage::canAccess() ? OvertimeSettingsPage::getUrl() : null,
                'national_holidays' => NationalHolidayResource::canViewAny() ? NationalHolidayResource::getUrl('index') : null,
            ],
        ];
    }
}
