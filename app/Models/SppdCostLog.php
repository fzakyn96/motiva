<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SppdCostLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'memo_log_id',
        'sppd_log_id',
        'sppd_cost_id',
        'sppd_id',
        'sppd_number',
        'nomor_memo',
        'employee_name',
        'department_name',
        'base_type',
        'official_days',
        'subtotal_transport',
        'subtotal_per_diem',
        'subtotal_other',
        'total_cost',
        'action',
        'deletion_reason',
        'deleted_by_user_id',
        'deleted_by_name',
        'deleted_by_role',
        'cost_snapshot',
    ];

    protected $casts = [
        'official_days' => 'integer',
        'subtotal_transport' => 'decimal:2',
        'subtotal_per_diem' => 'decimal:2',
        'subtotal_other' => 'decimal:2',
        'total_cost' => 'decimal:2',
        'cost_snapshot' => 'array',
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

