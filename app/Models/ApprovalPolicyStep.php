<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ApprovalPolicyStep extends Model
{
    protected $fillable = ['approval_policy_id', 'step_order', 'resolver_class'];

    public function policy()
    {
        return $this->belongsTo(ApprovalPolicy::class, 'approval_policy_id');
    }
}
