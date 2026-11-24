<?php

namespace App\Services\Overtime;

use App\Models\Employee;
use App\Models\NationalHoliday;
use App\Models\OvertimeOrder;
use Carbon\Carbon;

class OvertimeCalculatorService
{
    public function resolveDayType(
        Carbon|string $date,
        string $workMode = 'non_shift',
        bool $isShiftDayOff = false
    ): string {
        $carbon = $date instanceof Carbon ? $date->copy() : Carbon::parse($date);

        if (NationalHoliday::isHoliday($carbon)) {
            return 'L';
        }

        if ($workMode === 'shift') {
            return $isShiftDayOff ? 'L' : 'N';
        }

        return $carbon->isWeekend() ? 'L' : 'N';
    }

    /**
     * Rumus perusahaan sesuai PP 35/2021:
     * MAX(75% * (UD + TP) / 173, UD / 173)
     */
    public function calculateHourlyTariff(float $baseSalary, float $positionAllowance): float
    {
        $tariffOne = 0.75 * ($baseSalary + $positionAllowance) / 173;
        $tariffTwo = $baseSalary / 173;

        return max($tariffOne, $tariffTwo);
    }

    public function calculateDurationHours(string $startTime, string $endTime): float
    {
        $start = Carbon::parse('2000-01-01 ' . $startTime);
        $end = Carbon::parse('2000-01-01 ' . $endTime);

        if ($end->lessThanOrEqualTo($start)) {
            $end->addDay();
        }

        return round($start->diffInMinutes($end) / 60, 2);
    }

    /**
     * Koefisien 5 hari kerja (PP No. 35 Tahun 2021):
     * - N (Hari Kerja): jam 1 = 1.5x, jam 2+ = 2x
     * - L (Hari Libur/Akhir Pekan): jam 1..8 = 2x, jam 9 = 3x, jam 10+ = 4x
     */
    public function calculateMultiplier(float $hours, string $dayType): float
    {
        if ($hours <= 0) {
            return 0.0;
        }

        if ($dayType === 'L') {
            $firstEight = min($hours, 8.0) * 2.0;
            $ninthHour = min(max(0.0, $hours - 8.0), 1.0) * 3.0;
            $tenthPlus = max(0.0, $hours - 9.0) * 4.0;

            return round($firstEight + $ninthHour + $tenthPlus, 2);
        }

        $firstHour = min($hours, 1.0) * 1.5;
        $remaining = max(0.0, $hours - 1.0) * 2.0;

        return round($firstHour + $remaining, 2);
    }

    /**
     * @return array{
     *     day_type: string,
     *     actual_hours: float,
     *     hourly_tariff: float,
     *     multiplier: float,
     *     overtime_pay: float
     * }
     */
    public function calculateOrder(OvertimeOrder $order, ?Employee $employee = null): array
    {
        $emp = $employee ?? $order->employee;
        $workMode = $order->work_mode ?: ($emp?->overtime_work_mode ?: 'non_shift');
        $dayType = $this->resolveDayType(
            $order->overtime_date,
            $workMode,
            (bool) $order->is_shift_day_off
        );

        $hours = 0.0;
        if ($order->start_time && $order->end_time) {
            $hours = $this->calculateDurationHours($order->start_time, $order->end_time);
        } elseif ($order->actual_hours !== null) {
            $hours = (float) $order->actual_hours;
        }

        $ud = (float) ($emp?->base_salary ?? 0);
        $tp = (float) ($emp?->position_allowance ?? 0);
        $tariff = $this->calculateHourlyTariff($ud, $tp);
        $multiplier = $this->calculateMultiplier($hours, $dayType);
        $pay = round($tariff * $multiplier, 2);

        return [
            'day_type' => $dayType,
            'actual_hours' => $hours,
            'hourly_tariff' => round($tariff, 4),
            'multiplier' => $multiplier,
            'overtime_pay' => $pay,
        ];
    }
}
