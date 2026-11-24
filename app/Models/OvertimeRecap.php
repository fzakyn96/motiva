<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OvertimeRecap extends Model
{
    protected $fillable = [
        'recap_number',
        'period_month',
        'period_year',
        'title',
        'department_id',
        'creator_id',
        'total_shift_amount',
        'total_non_shift_amount',
        'grand_total',
        'status',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'period_month' => 'integer',
            'period_year' => 'integer',
            'total_shift_amount' => 'float',
            'total_non_shift_amount' => 'float',
            'grand_total' => 'float',
        ];
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creator_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(OvertimeRecapItem::class)->orderBy('id');
    }

    public static function generateRecapNumber(int $month, int $year): string
    {
        $mm = str_pad((string) $month, 2, '0', STR_PAD_LEFT);
        $seq = static::where('period_year', $year)->count() + 1;

        do {
            $next = str_pad((string) $seq, 4, '0', STR_PAD_LEFT);
            $candidate = "{$next}/REKAP-LEMBUR/MESI/{$mm}/{$year}";
            $seq++;
        } while (static::where('recap_number', $candidate)->exists());

        return $candidate;
    }

    public function recalculateFromOrders(): self
    {
        $query = OvertimeOrder::query()
            ->whereYear('overtime_date', $this->period_year)
            ->whereMonth('overtime_date', $this->period_month)
            ->where('report_status', 'approved')
            ->with('employee');

        if ($this->department_id) {
            $deptId = $this->department_id;
            $query->whereHas('employee', fn ($q) => $q->where('department_id', $deptId));
        }

        $orders = $query->get();

        $grouped = [];
        foreach ($orders as $order) {
            $empId = (int) $order->employee_id;
            if (! isset($grouped[$empId])) {
                $grouped[$empId] = [
                    'shift_hours' => 0.0,
                    'shift_amount' => 0.0,
                    'non_shift_hours' => 0.0,
                    'non_shift_amount' => 0.0,
                ];
            }

            $hours = (float) ($order->actual_hours ?? 0);
            $pay = (float) ($order->overtime_pay ?? 0);

            if (($order->work_mode ?? 'non_shift') === 'shift') {
                $grouped[$empId]['shift_hours'] += $hours;
                $grouped[$empId]['shift_amount'] += $pay;
            } else {
                $grouped[$empId]['non_shift_hours'] += $hours;
                $grouped[$empId]['non_shift_amount'] += $pay;
            }
        }

        $this->items()->delete();

        $totalShift = 0.0;
        $totalNonShift = 0.0;

        foreach ($grouped as $empId => $totals) {
            // Per Excel Sheet 1 & 2/3: per-employee monthly amount is rounded to nearest integer Rupiah
            $shiftAmt = round($totals['shift_amount'], 0);
            $nonShiftAmt = round($totals['non_shift_amount'], 0);
            $lineTotal = $shiftAmt + $nonShiftAmt;

            $this->items()->create([
                'employee_id' => $empId,
                'shift_hours' => round($totals['shift_hours'], 2),
                'shift_amount' => $shiftAmt,
                'non_shift_hours' => round($totals['non_shift_hours'], 2),
                'non_shift_amount' => $nonShiftAmt,
                'total_amount' => $lineTotal,
            ]);

            $totalShift += $shiftAmt;
            $totalNonShift += $nonShiftAmt;
        }

        $this->update([
            'total_shift_amount' => $totalShift,
            'total_non_shift_amount' => $totalNonShift,
            'grand_total' => $totalShift + $totalNonShift,
        ]);

        return $this->refresh();
    }
}
