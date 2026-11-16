<?php

namespace App\Filament\Admin\Widgets;

use App\Models\OfficialTravelRecap;
use App\Models\SppdCost;
use App\Models\TransportAccommodationRecap;
use Carbon\Carbon;
use Filament\Widgets\ChartWidget;
use Illuminate\Database\Eloquent\Builder;

class ExecutiveCostSummaryWidget extends ChartWidget
{
    protected static ?string $heading = 'Tren Realisasi Biaya SPPD vs Rekap Tagihan';
    protected static ?string $description = 'Komparasi realisasi biaya SPPD & rekap tiket/perjalanan dinas selama 6 bulan terakhir';
    protected static ?int $sort = 2;

    protected int | string | array $columnSpan = [
        'default' => 'full',
        'md' => 1,
    ];

    public static function canView(): bool
    {
        $user = \Filament\Facades\Filament::auth()->user() ?? auth()->user();
        if (! $user) return false;
        return $user->hasAnyRole(['Division Head', 'Manager', 'Supervisor', 'Superadmin', 'Admin', 'HR']);
    }

    protected function getData(): array
    {
        $user = \Filament\Facades\Filament::auth()->user() ?? auth()->user();
        $employee = $user?->employee;
        $isAllAccess = ! $user || $user->hasRole(['Superadmin', 'Admin', 'HR', 'Division Head']);

        // Generate 6 months labels
        $months = [];
        $monthKeys = [];
        for ($i = 5; $i >= 0; $i--) {
            $date = Carbon::now()->subMonths($i);
            $key = $date->format('Y-m');
            $monthKeys[$key] = [
                'label' => $date->translatedFormat('M Y'),
                'sppd' => 0,
                'transport' => 0,
                'travel' => 0,
            ];
            $months[] = $date->translatedFormat('M Y');
        }

        $startDate = Carbon::now()->subMonths(5)->startOfMonth();

        // 1. SPPD Costs
        $sppdCostQuery = SppdCost::whereHas('sppd', function (Builder $q) use ($isAllAccess, $employee) {
            $q->where('status', 'approved');
            if (! $isAllAccess && $employee) {
                $q->whereHas('employee', function (Builder $eq) use ($employee) {
                    $eq->where('department_id', $employee->department_id)
                        ->orWhere('manager_id', $employee->id)
                        ->orWhere('id', $employee->id);
                });
            }
        })->where('created_at', '>=', $startDate)->get();

        foreach ($sppdCostQuery as $cost) {
            $k = $cost->created_at->format('Y-m');
            if (isset($monthKeys[$k])) {
                $monthKeys[$k]['sppd'] += (float) $cost->total_cost;
            }
        }

        // 2. Rekap Tiket & Perjalanan Dinas
        $transportQuery = TransportAccommodationRecap::where('created_at', '>=', $startDate);
        $travelQuery = OfficialTravelRecap::where('created_at', '>=', $startDate);

        if (! $isAllAccess && $employee?->department_id) {
            $transportQuery->where('department_id', $employee->department_id);
            $travelQuery->where('department_id', $employee->department_id);
        }

        foreach ($transportQuery->get() as $recap) {
            $k = $recap->created_at->format('Y-m');
            if (isset($monthKeys[$k])) {
                $monthKeys[$k]['transport'] += (float) $recap->grand_total;
            }
        }

        foreach ($travelQuery->get() as $recap) {
            $k = $recap->created_at->format('Y-m');
            if (isset($monthKeys[$k])) {
                $monthKeys[$k]['travel'] += (float) $recap->grand_total;
            }
        }

        $sppdData = array_map(fn ($m) => $m['sppd'], array_values($monthKeys));
        $transportData = array_map(fn ($m) => $m['transport'], array_values($monthKeys));
        $travelData = array_map(fn ($m) => $m['travel'], array_values($monthKeys));

        return [
            'datasets' => [
                [
                    'label' => 'Biaya SPPD (Rp)',
                    'data' => $sppdData,
                    'backgroundColor' => '#f59e0b',
                    'borderColor' => '#d97706',
                    'borderWidth' => 1,
                    'borderRadius' => 6,
                ],
                [
                    'label' => 'Rekap Tiket & Akomodasi (Rp)',
                    'data' => $transportData,
                    'backgroundColor' => '#0ea5e9',
                    'borderColor' => '#0284c7',
                    'borderWidth' => 1,
                    'borderRadius' => 6,
                ],
                [
                    'label' => 'Rekap Perjalanan Dinas (Rp)',
                    'data' => $travelData,
                    'backgroundColor' => '#10b981',
                    'borderColor' => '#059669',
                    'borderWidth' => 1,
                    'borderRadius' => 6,
                ],
            ],
            'labels' => $months,
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }
}
