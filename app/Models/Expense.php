<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Expense extends Model
{
    protected $fillable = ['reimbursement_id', 'expense_type', 'amount', 'is_company_paid', 'receipt_path'];

    protected $casts = [
        'amount' => 'decimal:2',
        'is_company_paid' => 'boolean',
    ];

    public function reimbursement()
    {
        return $this->belongsTo(Reimbursement::class);
    }
}
