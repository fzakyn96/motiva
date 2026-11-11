<?php

namespace App\Filament\Admin\Widgets;

use App\Models\Memo;
use App\Models\Reimbursement;
use App\Models\Sppd;
use Filament\Widgets\ChartWidget;
use Illuminate\Database\Eloquent\Builder;

class ExecutivePipelineChartWidget extends ChartWidget
{
    protected static ?string $heading = 'Pipeline & Distribusi Status Dokumen Perjalanan Dinas';
    protected static ?string $description = 'Rekapitulasi progres penyelesaian Memo Dinas, SPPD, Kalkulasi Biaya, dan Reimbursement';
    protected static ?int $sort = 3;

    protected int | string | array $columnSpan = [
        'default' => 'full',
        'md' => 1,
    ];

    public static function canView(): bool
    {
        $user = \Filament\Facades\Filament::auth()->user() ?? auth()->user();
        if (! $user) {
            return false;
        }

        return $user->hasAnyRole(['Manager', 'Supervisor', 'Superadmin', 'Admin', 'HR']);
    }

    protected function getData(): array
    {
        $user = \Filament\Facades\Filament::auth()->user() ?? auth()->user();
        $employee = $user?->employee;
        $isAllAccess = ! $user || $user->hasRole(['Superadmin', 'Admin', 'HR']);

        // 1. Memo Query
        $memoQuery = Memo::query();
        if (! $isAllAccess && $employee) {
            $memoQuery->where(function (Builder $q) use ($employee) {
                $q->where('department_id', $employee->department_id)
                    ->orWhere('creator_employee_id', $employee->id)
                    ->orWhereHas('creator', fn (Builder $cq) => $cq->where('manager_id', $employee->id));
            });
        }

        $memoApproved = (clone $memoQuery)->where('status', 'approved')->count();
        $memoPending = (clone $memoQuery)->where('status', 'pending')->count();
        $memoOther = (clone $memoQuery)->whereIn('status', ['draft', 'rejected'])->count();

        // 2. SPPD Query
        $sppdQuery = Sppd::query();
        if (! $isAllAccess && $employee) {
            $sppdQuery->whereHas('employee', function (Builder $q) use ($employee) {
                $q->where('department_id', $employee->department_id)
                    ->orWhere('manager_id', $employee->id)
                    ->orWhere('id', $employee->id);
            });
        }

        $sppdApproved = (clone $sppdQuery)->where('status', 'approved')->count();
        $sppdPending = (clone $sppdQuery)->where('status', 'pending')->count();
        $sppdOther = (clone $sppdQuery)->whereIn('status', ['draft', 'rejected'])->count();

        // 3. Biaya SPPD (dari SPPD Approved: Sudah Dihitung vs Belum Dihitung)
        $costCalculated = (clone $sppdQuery)->where('status', 'approved')->has('cost')->count();
        $costUncalculated = (clone $sppdQuery)->where('status', 'approved')->doesntHave('cost')->count();

        // 4. Reimbursement Query
        $reimbQuery = Reimbursement::query();
        if (! $isAllAccess && $employee) {
            $reimbQuery->whereHas('employee', function (Builder $q) use ($employee) {
                $q->where('department_id', $employee->department_id)
                    ->orWhere('manager_id', $employee->id)
                    ->orWhere('id', $employee->id);
            });
        }

        $reimbApproved = (clone $reimbQuery)->where('status', 'approved')->count();
        $reimbPending = (clone $reimbQuery)->where('status', 'pending')->count();
        $reimbOther = (clone $reimbQuery)->whereIn('status', ['draft', 'rejected'])->count();

        return [
            'datasets' => [
                [
                    'label' => 'Disetujui / Terhitung',
                    'data' => [$memoApproved, $sppdApproved, $costCalculated, $reimbApproved],
                    'backgroundColor' => '#10b981',
                    'borderColor' => '#059669',
                    'borderWidth' => 1,
                    'borderRadius' => 6,
                ],
                [
                    'label' => 'Menunggu Proses / Hitung',
                    'data' => [$memoPending, $sppdPending, $costUncalculated, $reimbPending],
                    'backgroundColor' => '#f59e0b',
                    'borderColor' => '#d97706',
                    'borderWidth' => 1,
                    'borderRadius' => 6,
                ],
                [
                    'label' => 'Draf / Ditolak',
                    'data' => [$memoOther, $sppdOther, 0, $reimbOther],
                    'backgroundColor' => '#94a3b8',
                    'borderColor' => '#64748b',
                    'borderWidth' => 1,
                    'borderRadius' => 6,
                ],
            ],
            'labels' => ['Memo Dinas', 'SPPD', 'Kalkulasi Biaya', 'Reimbursement'],
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }
}

