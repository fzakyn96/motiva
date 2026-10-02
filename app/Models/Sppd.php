<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Sppd extends Model
{
    protected $fillable = ['business_trip_id', 'employee_id', 'sppd_number', 'status'];

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
}
