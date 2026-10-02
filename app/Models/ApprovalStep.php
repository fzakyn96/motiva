<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ApprovalStep extends Model
{
    protected $fillable = ['approval_request_id', 'approval_policy_step_id', 'status', 'approver_employee_id', 'notes'];

    public function request()
    {
        return $this->belongsTo(ApprovalRequest::class, 'approval_request_id');
    }

    public function policyStep()
    {
        return $this->belongsTo(ApprovalPolicyStep::class, 'approval_policy_step_id');
    }

    public function approver()
    {
        return $this->belongsTo(Employee::class, 'approver_employee_id');
    }
}
