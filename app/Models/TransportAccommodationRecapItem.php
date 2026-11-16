<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TransportAccommodationRecapItem extends Model
{
    protected $fillable = [
        'transport_accommodation_recap_id',
        'sppd_id',
        'sppd_ids',
        'description',
        'amount',
        'service_fee',
        'total_amount',
        'evidence_files',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'service_fee' => 'decimal:2',
            'total_amount' => 'decimal:2',
            'sppd_ids' => 'array',
            'evidence_files' => 'array',
            'sort_order' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (self $item): void {
            $item->total_amount = (float) ($item->amount ?? 0) + (float) ($item->service_fee ?? 0);
        });
    }

    public function recap(): BelongsTo
    {
        return $this->belongsTo(TransportAccommodationRecap::class, 'transport_accommodation_recap_id');
    }

    public function sppd(): BelongsTo
    {
        return $this->belongsTo(Sppd::class);
    }
}

