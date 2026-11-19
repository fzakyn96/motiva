<?php

namespace App\Filament\Admin\Widgets;

use App\Models\Memo;
use App\Models\OfficialTravelRecap;
use App\Models\Sppd;
use App\Models\TransportAccommodationRecap;
use Filament\Widgets\ChartWidget;
use Illuminate\Database\Eloquent\Builder;

class ExecutivePipelineChartWidget extends ChartWidget
{
    protected static ?string $heading = 'Pipeline & Distribusi Status Dokumen Perjalanan Dinas';
    protected static ?string $description = 'Rekapitulasi progres penyelesaian Memo Dinas, SPPD, Kalkulasi Biaya, dan Rekap Tagihan';
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

        return $user->hasAnyRole(['Division Head', 'Manager', 'Supervisor', 'Superadmin', 'Admin', 'HR']);
    }

    protected function getData(): array
    {
        $user = \Filament\Facades\Filament::auth()->user() ?? auth()->user();
        $employee = $user?->employee;
        $isAllAccess = ! $user || $user->hasRole(['Superadmin', 'Admin', 'HR', 'Division Head']) || (bool) $employee?->isBusinessDivisionHead();

        // 1. Memo Query
        $memoQuery = Memo::query();
        if (! $isAllAccess && $employee) {
            $memoQuery->where(function (Builder $q) use ($employee) {
                if ($employee->work_area_id) {
                    $q->whereHas('creator', fn (Builder $cq) => $cq->where('work_area_id', $employee->work_area_id));
                } else {
                    $q->where('department_id', $employee->department_id)
                        ->orWhere('creator_employee_id', $employee->id)
                        ->orWhereHas('creator', fn (Builder $cq) => $cq->where('manager_id', $employee->id));
                }
            });
        }

        $memoApproved = (clone $memoQuery)->where('status', 'approved')->count();
        $memoPending = (clone $memoQuery)->where('status', 'pending')->count();
        $memoOther = (clone $memoQuery)->whereIn('status', ['draft', 'rejected'])->count();

        // 2. SPPD Query
        $sppdQuery = Sppd::query();
        if (! $isAllAccess && $employee) {
            $sppdQuery->whereHas('employee', function (Builder $q) use ($employee) {
                if ($employee->work_area_id) {
                    $q->where('work_area_id', $employee->work_area_id);
                } else {
                    $q->where('department_id', $employee->department_id)
                        ->orWhere('manager_id', $employee->id)
                        ->orWhere('id', $employee->id);
                }
            });
        }

        $sppdApproved = (clone $sppdQuery)->where('status', 'approved')->count();
        $sppdPending = (clone $sppdQuery)->where('status', 'pending')->count();
        $sppdOther = (clone $sppdQuery)->whereIn('status', ['draft', 'rejected'])->count();

        // 3. Biaya SPPD (dari SPPD Approved: Sudah Dihitung vs Belum Dihitung)
        $costCalculated = (clone $sppdQuery)->where('status', 'approved')->has('cost')->count();
        $costUncalculated = (clone $sppdQuery)->where('status', 'approved')->doesntHave('cost')->count();

        // 4. Rekap Tiket & Akomodasi vs Rekap Perjalanan Dinas (terpisah)
        $transportRecaps = TransportAccommodationRecap::with('approvalRequests');
        $travelRecaps = OfficialTravelRecap::with('approvalRequests');
        if (! $isAllAccess && $employee?->department_id) {
            $transportRecaps->where('department_id', $employee->department_id);
            $travelRecaps->where('department_id', $employee->department_id);
        }

        $countRecapByStatus = function ($recaps): array {
            $approved = 0;
            $pending = 0;
            $other = 0;
            foreach ($recaps as $recap) {
                $status = $recap->approvalRequests->sortByDesc('id')->first()?->status;
                if ($status === 'approved') {
                    $approved++;
                } elseif ($status === 'pending') {
                    $pending++;
                } else {
                    $other++;
                }
            }

            return [$approved, $pending, $other];
        };

        [$transportApproved, $transportPending, $transportOther] = $countRecapByStatus($transportRecaps->get());
        [$travelApproved, $travelPending, $travelOther] = $countRecapByStatus($travelRecaps->get());

        return [
            'datasets' => [
                [
                    'label' => 'Disetujui / Terhitung',
                    'data' => [$memoApproved, $sppdApproved, $costCalculated, $transportApproved, $travelApproved],
                    'backgroundColor' => '#10b981',
                    'borderColor' => '#059669',
                    'borderWidth' => 1,
                    'borderRadius' => 6,
                ],
                [
                    'label' => 'Menunggu Proses / Hitung',
                    'data' => [$memoPending, $sppdPending, $costUncalculated, $transportPending, $travelPending],
                    'backgroundColor' => '#f59e0b',
                    'borderColor' => '#d97706',
                    'borderWidth' => 1,
                    'borderRadius' => 6,
                ],
                [
                    'label' => 'Draf / Ditolak',
                    'data' => [$memoOther, $sppdOther, 0, $transportOther, $travelOther],
                    'backgroundColor' => '#94a3b8',
                    'borderColor' => '#64748b',
                    'borderWidth' => 1,
                    'borderRadius' => 6,
                ],
            ],
            'labels' => ['Memo Dinas', 'SPPD', 'Kalkulasi Biaya', 'Rekap Tiket & Akomodasi', 'Rekap Perjalanan Dinas'],
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }
}

