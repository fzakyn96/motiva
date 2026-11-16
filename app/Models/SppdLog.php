<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SppdLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'memo_log_id',
        'sppd_id',
        'sppd_number',
        'nomor_memo',
        'employee_name',
        'department_name',
        'destination',
        'departure_date',
        'return_date',
        'total_days',
        'sppd_status',
        'action',
        'deletion_reason',
        'deleted_by_user_id',
        'deleted_by_name',
        'deleted_by_role',
        'sppd_snapshot',
    ];

    protected $casts = [
        'departure_date' => 'date',
        'return_date' => 'date',
        'total_days' => 'integer',
        'sppd_snapshot' => 'array',
    ];

    public function memoLog(): BelongsTo
    {
        return $this->belongsTo(MemoLog::class, 'memo_log_id');
    }

    public function deletedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'deleted_by_user_id');
    }
}

