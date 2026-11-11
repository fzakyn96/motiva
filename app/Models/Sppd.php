<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Sppd extends Model
{
    protected $fillable = [
        'business_trip_id',
        'employee_id',
        'sppd_number',
        'status',
        'travel_grade',
        'departure_date',
        'departure_time',
        'return_date',
        'return_time',
        'total_days',
        'origin_location',
        'destination_location',
        'assignment_purpose',
        'travel_category',
        'general_travel_type',
        'special_travel_type',
        'transport_departure',
        'transport_arrival',
        'transportation_mode',
        'destination_transportation',
    ];

    protected function casts(): array
    {
        return [
            'departure_date' => 'date:Y-m-d',
            'return_date' => 'date:Y-m-d',
            'total_days' => 'integer',
            'destination_transportation' => 'array',
        ];
    }

    public function businessTrip()
    {
        return $this->belongsTo(BusinessTrip::class);
    }

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    public function approvalRequests()
    {
        return $this->morphMany(ApprovalRequest::class, 'document');
    }

    public function approvalRequest()
    {
        return $this->morphOne(ApprovalRequest::class, 'document')->latestOfMany();
    }

    public function cost()
    {
        return $this->hasOne(SppdCost::class);
    }

    public function getApprovalStageLabelAttribute(): string
    {
        if ($this->status === 'draft') {
            return 'Draf';
        }

        if ($this->status === 'approved') {
            return 'Disetujui';
        }

        if ($this->status === 'rejected') {
            return 'Ditolak';
        }

        $request = $this->approvalRequest;
        if (! $request) {
            return 'Menunggu Persetujuan';
        }

        $step = $request->currentStep();
        if (! $step) {
            return 'Menunggu Persetujuan';
        }

        if ($step->policyStep?->step_order === 1) {
            return 'Menunggu Supervisor';
        }

        if ($step->policyStep?->step_order === 2) {
            return 'Menunggu Manajer (Disetujui SPV)';
        }

        return 'Menunggu Persetujuan';
    }
}
