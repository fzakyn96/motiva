<?php

namespace App\Services\Overtime;

use App\Models\Employee;
use App\Models\OvertimeOrder;
use App\Models\OvertimeSetting;
use Carbon\Carbon;

class OvertimeComplianceService
{
    /**
     * Evaluates labor compliance against PP No. 35 Tahun 2021 limits:
     * - Max daily weekday overtime hours (default 4h)
     * - Max weekly overtime hours (default 18h)
     *
     * @return array{
     *     status: string,
     *     warnings: array<int, string>,
     *     daily_total: float,
     *     weekly_total: float,
     *     max_daily: float,
     *     max_weekly: float
     * }
     */
    public function evaluate(
        Employee $employee,
        Carbon|string $date,
        float $hours,
        string $dayType,
        ?int $excludeOrderId = null
    ): array {
        $setting = OvertimeSetting::current();
        $maxDaily = (float) ($setting->max_daily_hours_weekday ?: 4.0);
        $maxWeekly = (float) ($setting->max_weekly_hours ?: 18.0);
        $mode = $setting->compliance_mode ?: 'warning';

        $carbon = $date instanceof Carbon ? $date->copy() : Carbon::parse($date);
        $dateString = $carbon->toDateString();
        $weekStart = $carbon->copy()->startOfWeek(Carbon::MONDAY)->toDateString();
        $weekEnd = $carbon->copy()->endOfWeek(Carbon::SUNDAY)->toDateString();

        $baseQuery = OvertimeOrder::query()
            ->where('employee_id', $employee->id)
            ->whereNotIn('status', ['command_rejected', 'report_rejected']);

        if ($excludeOrderId) {
            $baseQuery->where('id', '!=', $excludeOrderId);
        }

        $sameDayOrders = (clone $baseQuery)
            ->whereDate('overtime_date', $dateString)
            ->get();

        $existingDaily = (float) $sameDayOrders->sum(
            fn (OvertimeOrder $o): float => (float) ($o->actual_hours ?? $o->planned_hours ?? 0)
        );
        $dailyTotal = round($existingDaily + $hours, 2);

        $sameWeekOrders = (clone $baseQuery)
            ->whereBetween('overtime_date', [$weekStart, $weekEnd])
            ->get();

        $existingWeekly = (float) $sameWeekOrders->sum(
            fn (OvertimeOrder $o): float => (float) ($o->actual_hours ?? $o->planned_hours ?? 0)
        );
        $weeklyTotal = round($existingWeekly + $hours, 2);

        $warnings = [];

        if ($dayType === 'N' && $dailyTotal > $maxDaily) {
            $warnings[] = "Total lembur harian pada hari kerja ({$dailyTotal} jam) melebihi batas PP 35/2021 ({$maxDaily} jam/hari).";
        }

        if ($weeklyTotal > $maxWeekly) {
            $warnings[] = "Akumulasi lembur mingguan ({$weeklyTotal} jam) melebihi batas PP 35/2021 ({$maxWeekly} jam/minggu).";
        }

        $status = 'ok';
        if (! empty($warnings)) {
            $status = $mode === 'block' ? 'blocked' : 'warning';
        }

        return [
            'status' => $status,
            'warnings' => $warnings,
            'daily_total' => $dailyTotal,
            'weekly_total' => $weeklyTotal,
            'max_daily' => $maxDaily,
            'max_weekly' => $maxWeekly,
        ];
    }
}
