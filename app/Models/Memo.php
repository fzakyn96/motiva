<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Memo extends Model
{
    protected $fillable = ['nomor_memo', 'department_id', 'creator_employee_id', 'to_recipient', 'title', 'content', 'date', 'status'];

    public function department()
    {
        return $this->belongsTo(Department::class);
    }

    public function creator()
    {
        return $this->belongsTo(Employee::class, 'creator_employee_id');
    }

    public function businessTrips()
    {
        return $this->hasMany(BusinessTrip::class);
    }

    public function approvalRequests()
    {
        return $this->morphMany(ApprovalRequest::class, 'document');
    }
}
