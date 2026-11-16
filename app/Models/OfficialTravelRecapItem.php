<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OfficialTravelRecapItem extends Model
{
    protected $fillable = [
        'official_travel_recap_id',
        'sppd_id',
        'sppd_cost_id',
        'description',
        'amount',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'sort_order' => 'integer',
        ];
    }

    public function recap(): BelongsTo
    {
        return $this->belongsTo(OfficialTravelRecap::class, 'official_travel_recap_id');
    }

    public function sppd(): BelongsTo
    {
        return $this->belongsTo(Sppd::class);
    }

    public function sppdCost(): BelongsTo
    {
        return $this->belongsTo(SppdCost::class);
    }
}

