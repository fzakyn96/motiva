<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class Expense extends Model
{
    use HasFactory;

    protected $fillable = [
        'reimbursement_id',
        'expense_type',
        'transaction_date',
        'description',
        'amount',
        'is_company_paid',
        'receipt_path',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'is_company_paid' => 'boolean',
            'transaction_date' => 'date',
        ];
    }

    public function reimbursement()
    {
        return $this->belongsTo(Reimbursement::class);
    }

    public function getReceiptUrlAttribute(): ?string
    {
        if (! $this->receipt_path) {
            return null;
        }

        return Storage::disk('public')->url($this->receipt_path);
    }

    public function isPdfReceipt(): bool
    {
        if (! $this->receipt_path) {
            return false;
        }

        return str_ends_with(strtolower($this->receipt_path), '.pdf');
    }
}
