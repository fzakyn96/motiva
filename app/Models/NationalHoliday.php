<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

class NationalHoliday extends Model
{
    protected $fillable = [
        'holiday_date',
        'name',
        'is_active',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'holiday_date' => 'date',
            'is_active' => 'boolean',
        ];
    }

    public static function isHoliday(Carbon|string $date): bool
    {
        $dateString = $date instanceof Carbon ? $date->toDateString() : Carbon::parse($date)->toDateString();

        return static::where('holiday_date', $dateString)
            ->where('is_active', true)
            ->exists();
    }
}
