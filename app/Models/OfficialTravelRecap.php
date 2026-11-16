<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OfficialTravelRecap extends Model
{
    protected $fillable = [
        'recap_number',
        'department_id',
        'business_trip_id',
        'business_trip_ids',
        'work_name',
        'period_label',
        'period_month',
        'total_work_cost',
        'manfee_percent',
        'manfee_amount',
        'grand_total',
        'prepared_by_name',
        'prepared_by_position',
        'checked_by_list',
        'approved_by_name',
        'approved_by_position',
        'creator_id',
    ];

    protected function casts(): array
    {
        return [
            'total_work_cost' => 'decimal:2',
            'manfee_percent' => 'decimal:2',
            'manfee_amount' => 'decimal:2',
            'grand_total' => 'decimal:2',
            'business_trip_ids' => 'array',
            'checked_by_list' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (OfficialTravelRecap $recap): void {
            $sig = $recap->resolveSignatories();

            if (empty($recap->prepared_by_name) && ! empty($sig['prepared_by'])) {
                $recap->prepared_by_name = $sig['prepared_by']->name;
                $recap->prepared_by_position = $sig['prepared_by']->position?->name ?? 'Business Division Head';
            }

            if (empty($recap->checked_by_list) && ! empty($sig['checked_by']) && $sig['checked_by']->isNotEmpty()) {
                $recap->checked_by_list = $sig['checked_by']
                    ->map(fn ($emp) => [
                        'name' => $emp->name,
                        'position' => $emp->position?->name ?? 'Manajer',
                    ])
                    ->values()
                    ->all();
            }

            if (empty($recap->approved_by_name) && ! empty($sig['approved_by'])) {
                $recap->approved_by_name = $sig['approved_by']->name;
                $recap->approved_by_position = $sig['approved_by']->position?->name ?? 'Division Head';
            }
        });
    }

    public function resolveSignatories(): array
    {
        $ids = is_array($this->business_trip_ids) ? $this->business_trip_ids : [];
        if ($this->business_trip_id && ! in_array($this->business_trip_id, $ids)) {
            $ids[] = $this->business_trip_id;
        }

        return BusinessTrip::resolveSignatoriesForTrips($ids, $this->department_id);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function businessTrip(): BelongsTo
    {
        return $this->belongsTo(BusinessTrip::class);
    }

    public function approvalRequests()
    {
        return $this->morphMany(ApprovalRequest::class, 'document');
    }

    public function ensureApprovalRequestExists(?Employee $requester = null): ApprovalRequest
    {
        return BusinessTrip::createRecapApprovalFor($this, $requester, $this->department_id);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creator_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(OfficialTravelRecapItem::class)->orderBy('id');
    }

    public function recalculateTotals(): void
    {
        $totalWorkCost = (float) $this->items()->sum('amount');
        $percent = (float) ($this->manfee_percent ?? ReimbursementSetting::current()->travel_manfee_percent);
        $manfeeAmount = round($totalWorkCost * ($percent / 100), 0);

        $this->total_work_cost = $totalWorkCost;
        $this->manfee_percent = $percent;
        $this->manfee_amount = $manfeeAmount;
        $this->grand_total = $totalWorkCost + $manfeeAmount;
        $this->saveQuietly();
    }
}
