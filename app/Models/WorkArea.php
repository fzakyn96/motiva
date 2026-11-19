<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WorkArea extends Model
{
    protected $fillable = [
        'department_id',
        'code',
        'name',
        'coordinator_employee_id',
        'description',
    ];

    protected function casts(): array
    {
        return [
            'department_id' => 'integer',
            'coordinator_employee_id' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::deleting(function (WorkArea $workArea): void {
            Employee::where('work_area_id', $workArea->id)->update(['work_area_id' => null]);
        });
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function coordinator(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'coordinator_employee_id');
    }

    public function employees(): HasMany
    {
        return $this->hasMany(Employee::class, 'work_area_id');
    }
}
