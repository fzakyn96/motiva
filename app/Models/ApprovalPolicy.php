<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ApprovalPolicy extends Model
{
    protected $fillable = ['name', 'model_type'];

    public function steps()
    {
        return $this->hasMany(ApprovalPolicyStep::class)->orderBy('step_order');
    }
}
