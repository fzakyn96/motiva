<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReimbursementSetting extends Model
{
    protected $fillable = [
        'transport_service_fee_percent',
        'transport_manfee_percent',
        'travel_manfee_percent',
    ];

    protected function casts(): array
    {
        return [
            'transport_service_fee_percent' => 'decimal:2',
            'transport_manfee_percent' => 'decimal:2',
            'travel_manfee_percent' => 'decimal:2',
        ];
    }

    public static function current(): self
    {
        return static::firstOrCreate([], [
            'transport_service_fee_percent' => 0,
            'transport_manfee_percent' => 5.00,
            'travel_manfee_percent' => 6.00,
        ]);
    }
}

