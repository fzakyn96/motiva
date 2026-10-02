<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ApprovalRequest extends Model
{
    protected $fillable = ['document_type', 'document_id', 'approval_policy_id', 'requester_employee_id', 'status'];

    public function document()
    {
        return $this->morphTo();
    }

    public function policy()
    {
        return $this->belongsTo(ApprovalPolicy::class, 'approval_policy_id');
    }

    public function requester()
    {
        return $this->belongsTo(Employee::class, 'requester_employee_id');
    }

    public function steps()
    {
        return $this->hasMany(ApprovalStep::class);
    }

    public function currentStep()
    {
        return $this->steps()->where('status', 'pending')
            ->join('approval_policy_steps', 'approval_steps.approval_policy_step_id', '=', 'approval_policy_steps.id')
            ->orderBy('approval_policy_steps.step_order')
            ->select('approval_steps.*')
            ->first();
    }
}
