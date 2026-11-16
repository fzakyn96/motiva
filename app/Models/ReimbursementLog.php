<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReimbursementLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'memo_log_id',
        'sppd_log_id',
        'reimbursement_id',
        'sppd_id',
        'reimbursement_number',
        'sppd_number',
        'nomor_memo',
        'employee_name',
        'department_name',
        'title',
        'total_amount',
        'total_claim_amount',
        'expenses_count',
        'reimbursement_status',
        'action',
        'deletion_reason',
        'deleted_by_user_id',
        'deleted_by_name',
        'deleted_by_role',
        'reimbursement_snapshot',
    ];

    protected $casts = [
        'total_amount' => 'decimal:2',
        'total_claim_amount' => 'decimal:2',
        'expenses_count' => 'integer',
        'reimbursement_snapshot' => 'array',
    ];

    public function memoLog(): BelongsTo
    {
        return $this->belongsTo(MemoLog::class, 'memo_log_id');
    }

    public function sppdLog(): BelongsTo
    {
        return $this->belongsTo(SppdLog::class, 'sppd_log_id');
    }

    public function deletedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'deleted_by_user_id');
    }
}

