<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Reimbursement extends Model
{
    use HasFactory;

    protected $fillable = [
        'reimbursement_number',
        'sppd_id',
        'employee_id',
        'title',
        'notes',
        'total_amount',
        'total_claim_amount',
        'status',
        'submitted_at',
        'approved_at',
        'rejected_at',
        'rejection_note',
    ];

    protected function casts(): array
    {
        return [
            'total_amount' => 'decimal:2',
            'total_claim_amount' => 'decimal:2',
            'submitted_at' => 'datetime',
            'approved_at' => 'datetime',
            'rejected_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Reimbursement $reimbursement) {
            if (empty($reimbursement->reimbursement_number)) {
                $reimbursement->reimbursement_number = static::generateNumber();
            }
        });

        static::deleting(function (Reimbursement $reimbursement) {
            $reimbursement->expenses()->delete();
            $reimbursement->approvalRequests()->each(function ($req) {
                $req->steps()->delete();
                $req->delete();
            });
        });
    }

    public static function generateNumber(): string
    {
        $prefix = 'RMB/' . date('Y/m') . '/';
        $latest = static::where('reimbursement_number', 'like', $prefix . '%')
            ->orderBy('id', 'desc')
            ->first();

        $seq = 1;
        if ($latest && preg_match('/\/(\d{4})$/', $latest->reimbursement_number, $matches)) {
            $seq = ((int) $matches[1]) + 1;
        }

        return $prefix . str_pad((string) $seq, 4, '0', STR_PAD_LEFT);
    }

    /**
     * Hitung ulang total biaya keseluruhan dan total yang diklaim oleh pegawai
     */
    public function recalculateTotals(): void
    {
        $expenses = $this->expenses()->get();
        $this->total_amount = $expenses->sum('amount');
        $this->total_claim_amount = $expenses->where('is_company_paid', false)->sum('amount');
        $this->saveQuietly();
    }

    public function sppd()
    {
        return $this->belongsTo(Sppd::class);
    }

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    public function expenses()
    {
        return $this->hasMany(Expense::class);
    }

    public function approvalRequests()
    {
        return $this->morphMany(ApprovalRequest::class, 'document');
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isApproved(): bool
    {
        return $this->status === 'approved';
    }

    public function isDraft(): bool
    {
        return $this->status === 'draft';
    }

    public function isRejected(): bool
    {
        return $this->status === 'rejected';
    }
}
