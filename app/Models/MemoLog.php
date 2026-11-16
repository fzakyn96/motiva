<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MemoLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'memo_id',
        'nomor_memo',
        'title',
        'agenda',
        'to_recipient',
        'department_name',
        'creator_name',
        'memo_date',
        'memo_status',
        'action',
        'deletion_reason',
        'deleted_by_user_id',
        'deleted_by_name',
        'deleted_by_role',
        'memo_snapshot',
    ];

    protected $casts = [
        'memo_date' => 'date',
        'memo_snapshot' => 'array',
    ];

    public function deletedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'deleted_by_user_id');
    }
}

