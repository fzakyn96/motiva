<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Facades\Schema;

class OvertimeOrder extends Model
{
    protected $fillable = [
        'overtime_number',
        'employee_id',
        'creator_employee_id',
        'creator_user_id',
        'satuan_kerja',
        'location_city',
        'overtime_date',
        'command_date',
        'overtime_type',
        'work_description',
        'command_status',
        'command_approved_by_employee_id',
        'command_approved_at',
        'command_notes',
        'start_time',
        'end_time',
        'actual_result',
        'report_date',
        'report_status',
        'report_submitted_at',
        'report_approved_by_employee_id',
        'report_approved_at',
        'report_notes',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'overtime_date' => 'date',
            'command_date' => 'date',
            'command_approved_at' => 'datetime',
            'report_date' => 'date',
            'report_submitted_at' => 'datetime',
            'report_approved_at' => 'datetime',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'employee_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'creator_employee_id');
    }

    public function creatorUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creator_user_id');
    }

    public function commandApprover(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'command_approved_by_employee_id');
    }

    public function reportApprover(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'report_approved_by_employee_id');
    }

    public function approvalRequests(): MorphMany
    {
        return $this->morphMany(ApprovalRequest::class, 'document');
    }

    public function documentSignatures(): MorphMany
    {
        return $this->morphMany(DocumentSignature::class, 'document');
    }

    public static function generateOvertimeNumber(self $order): string
    {
        $year = date('Y');
        $month = date('m');

        $order->loadMissing(['employee.department', 'employee.workArea']);
        $deptCode = $order->employee?->department?->code
            ?? Department::where('code', 'MESI')->value('code')
            ?? 'MESI';

        $areaCode = 'JKT';
        if (Schema::hasTable('work_areas')) {
            $areaCode = $order->employee?->workArea?->code ?: 'JKT';
        }

        $count = static::whereYear('created_at', $year)->whereNotNull('overtime_number')->count();
        $seq = $count + 1;

        do {
            $nextSeq = str_pad((string) $seq, 4, '0', STR_PAD_LEFT);
            $candidate = "{$nextSeq}/SPKL/{$deptCode}/{$areaCode}/{$month}/{$year}";
            $seq++;
        } while (static::where('overtime_number', $candidate)->exists());

        return $candidate;
    }

    /**
     * Resolve the authorizing Manager for this overtime order (never hardcoded).
     */
    public function resolveCommandingManager(): ?Employee
    {
        if ($this->commandApprover) {
            return $this->commandApprover;
        }

        if ($this->reportApprover) {
            return $this->reportApprover;
        }

        return \App\Services\Approval\Resolvers\ManagerAuthorityResolver::resolveManagerForEmployee(
            $this->employee,
            $this->employee?->department_id
        );
    }

    public function resolveCityLabel(): string
    {
        if (filled($this->location_city)) {
            return $this->location_city;
        }

        $areaName = $this->employee?->workArea?->name;

        return filled($areaName) ? $areaName : 'Jakarta';
    }
}
