<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OvertimeRecapItem extends Model
{
    protected $fillable = [
        'overtime_recap_id',
        'employee_id',
        'shift_hours',
        'shift_amount',
        'non_shift_hours',
        'non_shift_amount',
        'total_amount',
    ];

    protected function casts(): array
    {
        return [
            'shift_hours' => 'float',
            'shift_amount' => 'float',
            'non_shift_hours' => 'float',
            'non_shift_amount' => 'float',
            'total_amount' => 'float',
        ];
    }

    public function recap(): BelongsTo
    {
        return $this->belongsTo(OvertimeRecap::class, 'overtime_recap_id');
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
