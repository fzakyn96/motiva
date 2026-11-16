<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Sppd extends Model
{
    protected $fillable = [
        'business_trip_id',
        'employee_id',
        'sppd_number',
        'status',
        'travel_grade',
        'departure_date',
        'departure_time',
        'return_date',
        'return_time',
        'total_days',
        'origin_location',
        'destination_location',
        'assignment_purpose',
        'travel_category',
        'general_travel_type',
        'special_travel_type',
        'transport_departure',
        'transport_arrival',
        'transportation_mode',
        'destination_transportation',
    ];

    protected function casts(): array
    {
        return [
            'departure_date' => 'date:Y-m-d',
            'return_date' => 'date:Y-m-d',
            'total_days' => 'integer',
            'destination_transportation' => 'array',
        ];
    }

    public function businessTrip()
    {
        return $this->belongsTo(BusinessTrip::class);
    }

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    public function approvalRequests()
    {
        return $this->morphMany(ApprovalRequest::class, 'document');
    }

    public function approvalRequest()
    {
        return $this->morphOne(ApprovalRequest::class, 'document')->latestOfMany();
    }

    public function cost()
    {
        return $this->hasOne(SppdCost::class);
    }

    public function getApprovalStageLabelAttribute(): string
    {
        if ($this->status === 'draft') {
            return 'Draf';
        }

        if ($this->status === 'approved') {
            return 'Disetujui';
        }

        if ($this->status === 'rejected') {
            return 'Ditolak';
        }

        $request = $this->approvalRequest;
        if (! $request) {
            return 'Menunggu Persetujuan';
        }

        $step = $request->currentStep();
        if (! $step) {
            return 'Menunggu Persetujuan';
        }

        if ($step->policyStep?->step_order === 1) {
            return 'Menunggu Supervisor';
        }

        if ($step->policyStep?->step_order === 2) {
            return 'Menunggu Manajer (Disetujui SPV)';
        }

        return 'Menunggu Persetujuan';
    }

    /**
     * Resolve pejabat yang memberi perintah (selalu setingkat Manajer).
     */
    public function resolveAuthorizingManager(): ?Employee
    {
        $isManagerEmployee = static function (?Employee $emp): bool {
            if (! $emp) {
                return false;
            }

            if ($emp->user?->hasRole('Manager')) {
                return true;
            }

            $posName = strtolower((string) ($emp->position?->name ?? ''));

            return str_contains($posName, 'manager') || str_contains($posName, 'manajer');
        };

        // 1. Dari approval request SPPD yang sudah disetujui oleh Manajer
        $sppdApproval = ApprovalRequest::where('document_type', self::class)
            ->where('document_id', $this->id)
            ->first();

        if ($sppdApproval) {
            $approvedSteps = $sppdApproval->steps()
                ->where('status', 'approved')
                ->with(['approver.position', 'approver.user', 'policyStep'])
                ->get()
                ->sortByDesc(fn ($s) => (int) ($s->policyStep?->step_order ?? 0));

            foreach ($approvedSteps as $step) {
                if ($isManagerEmployee($step->approver)) {
                    return $step->approver;
                }
            }
        }

        // 2. Dari approval request Memo terkait yang disetujui oleh Manajer
        $memo = $this->businessTrip?->memo;
        if ($memo) {
            $memoApproval = ApprovalRequest::where('document_type', Memo::class)
                ->where('document_id', $memo->id)
                ->first();

            if ($memoApproval) {
                $memoApproved = $memoApproval->steps()
                    ->where('status', 'approved')
                    ->with(['approver.position', 'approver.user'])
                    ->latest('updated_at')
                    ->first();

                if ($isManagerEmployee($memoApproved?->approver)) {
                    return $memoApproved->approver;
                }
            }
        }

        // 3. Telusuri hierarki atasan dari pegawai SPPD hingga level Manajer (lewati Supervisor/HR)
        $cursor = $this->employee?->manager;
        for ($i = 0; $i < 4 && $cursor; $i++) {
            $cursor->loadMissing(['position', 'user', 'manager']);
            if ($isManagerEmployee($cursor)) {
                return $cursor;
            }
            $cursor = $cursor->manager;
        }

        // 4. Telusuri hierarki dari pembuat Memo hingga level Manajer
        $memoCursor = $memo?->creator;
        for ($i = 0; $i < 4 && $memoCursor; $i++) {
            $memoCursor->loadMissing(['position', 'user', 'manager']);
            if ($isManagerEmployee($memoCursor)) {
                return $memoCursor;
            }
            $memoCursor = $memoCursor->manager;
        }

        // 5. Fallback berdasarkan unit kerja pegawai / memo
        $deptId = $this->employee?->department_id ?? $memo?->department_id;
        if ($deptId) {
            $deptManager = Employee::with(['position', 'user'])
                ->where('department_id', $deptId)
                ->where(function ($q) {
                    $q->whereHas('user.roles', fn ($rq) => $rq->where('name', 'Manager'))
                        ->orWhereHas('position', fn ($pq) => $pq->where('name', 'like', '%Manager%')->orWhere('name', 'like', '%Manajer%'));
                })
                ->first();

            if ($deptManager) {
                return $deptManager;
            }
        }

        // 6. Fallback global role Manager
        return Employee::with(['position', 'user'])
            ->where(function ($q) {
                $q->whereHas('user.roles', fn ($rq) => $rq->where('name', 'Manager'))
                    ->orWhereHas('position', fn ($pq) => $pq->where('name', 'like', '%Manager%')->orWhere('name', 'like', '%Manajer%'));
            })
            ->first();
    }
}
