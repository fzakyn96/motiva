<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BusinessTrip extends Model
{
    protected $fillable = ['memo_id', 'activity_name', 'destination', 'start_date', 'end_date'];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
    ];

    public function memo()
    {
        return $this->belongsTo(Memo::class);
    }

    public function sppds()
    {
        return $this->hasMany(Sppd::class);
    }

    public function approvalRequests()
    {
        return $this->morphMany(ApprovalRequest::class, 'document');
    }

    /**
     * Ensure 2-step recap approval request exists (Step 1: Manager -> Step 2: Division Head).
     */
    public function ensureRecapApprovalRequestExists(?Employee $requester = null): ApprovalRequest
    {
        return static::createRecapApprovalFor($this, $requester, $this->memo?->department_id);
    }

    public static function createRecapApprovalFor(
        \Illuminate\Database\Eloquent\Model $document,
        ?Employee $requester = null,
        ?int $departmentId = null
    ): ApprovalRequest {
        $existing = ApprovalRequest::where('document_type', get_class($document))
            ->where('document_id', $document->getKey())
            ->first();

        if ($existing) {
            return $existing;
        }

        $hasBdhApprover = Employee::whereNotNull('user_id')
            ->whereHas('position', fn ($q) => $q->whereIn('code', ['BDH', 'BDH-PKJ'])->orWhere('name', 'like', '%Business Division Head%'))
            ->exists();

        if ($hasBdhApprover) {
            $policy = ApprovalPolicy::firstOrCreate(
                ['model_type' => get_class($document) . ':3step'],
                ['name' => 'Recap Reimbursement 3-Step Policy']
            );

            ApprovalPolicyStep::updateOrCreate(
                ['approval_policy_id' => $policy->id, 'step_order' => 1],
                ['resolver_class' => \App\Services\Approval\Resolvers\ManagerAuthorityResolver::class]
            );

            ApprovalPolicyStep::updateOrCreate(
                ['approval_policy_id' => $policy->id, 'step_order' => 2],
                ['resolver_class' => \App\Services\Approval\Resolvers\BusinessDivisionHeadAuthorityResolver::class]
            );

            ApprovalPolicyStep::updateOrCreate(
                ['approval_policy_id' => $policy->id, 'step_order' => 3],
                ['resolver_class' => \App\Services\Approval\Resolvers\DivisionHeadAuthorityResolver::class]
            );
        } else {
            $policy = ApprovalPolicy::firstOrCreate(
                ['model_type' => get_class($document)],
                ['name' => 'Recap Reimbursement Policy']
            );

            ApprovalPolicyStep::firstOrCreate(
                ['approval_policy_id' => $policy->id, 'step_order' => 1],
                ['resolver_class' => \App\Services\Approval\Resolvers\ManagerAuthorityResolver::class]
            );

            ApprovalPolicyStep::firstOrCreate(
                ['approval_policy_id' => $policy->id, 'step_order' => 2],
                ['resolver_class' => \App\Services\Approval\Resolvers\DivisionHeadAuthorityResolver::class]
            );
        }

        $policy->refresh();

        $requesterId = $requester?->id
            ?? Employee::whereHas('position', fn ($q) => $q->whereIn('code', ['BDH', 'BDH-PKJ'])->orWhere('name', 'like', '%Business Division Head%'))->value('id')
            ?? ($departmentId ? Employee::where('department_id', $departmentId)->value('id') : null)
            ?? Employee::value('id');

        $request = ApprovalRequest::create([
            'document_type' => get_class($document),
            'document_id' => $document->getKey(),
            'approval_policy_id' => $policy->id,
            'requester_employee_id' => $requesterId,
            'status' => 'pending',
        ]);

        foreach ($policy->steps as $step) {
            ApprovalStep::create([
                'approval_request_id' => $request->id,
                'approval_policy_step_id' => $step->id,
                'status' => 'pending',
            ]);
        }

        return $request;
    }

    /**
     * Resolve signatories for PKJ (Disusun oleh) and PGN (Diperiksa oleh & Disetujui oleh).
     *
     * @return array{prepared_by: ?Employee, checked_by: \Illuminate\Support\Collection<int, Employee>, approved_by: ?Employee}
     */
    public function resolveRecapSignatories(): array
    {
        $preparedBy = Employee::with(['position', 'department', 'organization', 'user'])
            ->whereHas('position', fn ($q) => $q->whereIn('code', ['BDH', 'BDH-PKJ'])->orWhere('name', 'like', '%Business Division Head%'))
            ->first()
            ?? $this->memo?->creator;

        $checkedBy = $this->resolveDepartmentManagers();

        $approvedBy = null;
        foreach ($checkedBy as $checker) {
            if ($checker->manager) {
                $approvedBy = $checker->manager->loadMissing(['position', 'department', 'organization', 'user']);
                break;
            }
        }

        if (! $approvedBy) {
            $approvedBy = Employee::with(['position', 'department', 'organization', 'user'])
                ->whereDoesntHave('position', fn ($pq) => $pq->whereIn('code', ['BDH', 'BDH-PKJ'])->orWhere('name', 'like', '%Business Division Head%'))
                ->where(function ($q) {
                    $q->whereHas('position', fn ($pq) => $pq->where('level', 0)->orWhere('name', 'like', '%Division Head%'))
                        ->orWhereHas('user.roles', fn ($rq) => $rq->where('name', 'Division Head'));
                })
                ->first();
        }

        return [
            'prepared_by' => $preparedBy,
            'checked_by' => $checkedBy,
            'approved_by' => $approvedBy,
        ];
    }

    /**
     * Resolve signatories aggregated across multiple BusinessTrips in a monthly recap.
     *
     * @param  array<int, int|string>  $tripIds
     * @return array{prepared_by: ?Employee, checked_by: \Illuminate\Support\Collection<int, Employee>, approved_by: ?Employee}
     */
    public static function resolveSignatoriesForTrips(array $tripIds, ?int $fallbackDeptId = null): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $tripIds))));
        $trips = ! empty($ids)
            ? static::with(['memo.creator', 'sppds.employee.department', 'sppds.employee.position', 'sppds.employee.manager.position'])->whereIn('id', $ids)->get()
            : collect();

        $preparedBy = Employee::with(['position', 'department', 'organization', 'user'])
            ->whereHas('position', fn ($q) => $q->whereIn('code', ['BDH', 'BDH-PKJ'])->orWhere('name', 'like', '%Business Division Head%'))
            ->first()
            ?? $trips->first()?->memo?->creator;

        $checkedBy = collect();
        foreach ($trips as $trip) {
            foreach ($trip->resolveDepartmentManagers() as $manager) {
                if (! $checkedBy->contains('id', $manager->id)) {
                    $checkedBy->push($manager);
                }
            }
        }

        if ($checkedBy->isEmpty() && $fallbackDeptId) {
            $deptManager = Employee::with(['position', 'department', 'user'])
                ->where('department_id', $fallbackDeptId)
                ->whereHas('position', fn ($pq) => $pq->where('level', 1)->where('code', '!=', 'BDH'))
                ->first();
            if ($deptManager) {
                $checkedBy->push($deptManager);
            }
        }

        $approvedBy = null;
        foreach ($checkedBy as $checker) {
            if ($checker->manager) {
                $approvedBy = $checker->manager->loadMissing(['position', 'department', 'organization', 'user']);
                break;
            }
        }

        if (! $approvedBy) {
            $approvedBy = Employee::with(['position', 'department', 'organization', 'user'])
                ->whereDoesntHave('position', fn ($pq) => $pq->whereIn('code', ['BDH', 'BDH-PKJ'])->orWhere('name', 'like', '%Business Division Head%'))
                ->where(function ($q) {
                    $q->whereHas('position', fn ($pq) => $pq->where('level', 0)->orWhere('name', 'like', '%Division Head%'))
                        ->orWhereHas('user.roles', fn ($rq) => $rq->where('name', 'Division Head'));
                })
                ->first();
        }

        return [
            'prepared_by' => $preparedBy,
            'checked_by' => $checkedBy->values(),
            'approved_by' => $approvedBy,
        ];
    }

    /**
     * Resolve unique authorizing managers for every unit/department represented in this trip's SPPDs.
     *
     * @return \Illuminate\Support\Collection<int, Employee>
     */
    public function resolveDepartmentManagers(): \Illuminate\Support\Collection
    {
        $hasWorkAreas = \Illuminate\Support\Facades\Schema::hasTable('work_areas');
        $relations = ['employee.department', 'employee.position', 'employee.user'];
        if ($hasWorkAreas) {
            $relations[] = 'employee.workArea.coordinator.position';
            $relations[] = 'employee.workArea.coordinator.department';
            $relations[] = 'employee.workArea.coordinator.user';
        }

        $sppds = $this->relationLoaded('sppds')
            ? $this->sppds
            : $this->sppds()->with($relations)->get();

        $managers = collect();

        foreach ($sppds as $sppd) {
            $deptId = $sppd->employee?->department_id;
            if (! $deptId) {
                continue;
            }

            $coordinator = $hasWorkAreas ? $sppd->employee?->workArea?->coordinator : null;
            if ($coordinator && ! $coordinator->isBusinessDivisionHead()) {
                $manager = $coordinator->loadMissing(['position', 'department', 'user']);
            } else {
                $directManager = $sppd->employee?->manager;
                if (
                    $directManager
                    && (int) ($directManager->position?->level ?? -1) === 1
                    && ! $directManager->isBusinessDivisionHead()
                ) {
                    $manager = $directManager->loadMissing(['position', 'department', 'user']);
                } else {
                    $manager = Employee::with(['position', 'department', 'user'])
                        ->where('department_id', $deptId)
                        ->whereHas('position', function ($pq) {
                            $pq->where('level', 1)
                                ->whereNotIn('code', ['BDH', 'BDH-PKJ'])
                                ->where('name', 'not like', '%Business Division Head%');
                        })
                        ->first();
                }
            }

            if (! $manager) {
                $manager = Employee::with(['position', 'department', 'user'])
                    ->where('department_id', $deptId)
                    ->whereHas('user.roles', fn ($rq) => $rq->where('name', 'Manager'))
                    ->whereDoesntHave('user.roles', fn ($rq) => $rq->where('name', 'Division Head'))
                    ->whereDoesntHave('position', fn ($pq) => $pq->where('level', 0)->orWhere('code', 'BDH'))
                    ->first();
            }

            if (! $manager) {
                $manager = $sppd->resolveAuthorizingManager();
            }

            if ($manager && ! $managers->contains('id', $manager->id)) {
                $managers->push($manager);
            }
        }

        return $managers->values();
    }

    /**
     * Build Transport & Accommodation recap rows & totals from SPPD costs in this trip.
     *
     * @return array{rows: array<int, array<string, mixed>>, subtotal_amount: float, total_service_fee: float, total_cost: float, manfee_percent: float, manfee_amount: float, grand_total: float}
     */
    public function buildTransportAccommodationRecap(): array
    {
        $setting = ReimbursementSetting::current();
        $serviceFeePercent = (float) $setting->transport_service_fee_percent;
        $manfeePercent = (float) $setting->transport_manfee_percent;

        $sppds = $this->sppds()->with(['employee.department', 'cost'])->get();
        $rows = [];
        $subtotalAmount = 0.0;
        $totalServiceFee = 0.0;
        $totalCost = 0.0;

        foreach ($sppds as $sppd) {
            $cost = $sppd->cost;
            if (! $cost) {
                continue;
            }

            $empName = $sppd->employee?->name ?? '-';
            $origin = $sppd->origin_location ?: 'Jakarta';
            $dest = $sppd->destination_location ?: ($this->destination ?? '-');
            $dateText = $this->formatSppdDateRange($sppd);

            $transportAmount = (float) $cost->transport_terminal_total
                + (float) $cost->transport_ticket_total
                + (float) $cost->transport_local_total
                + (float) $cost->overseas_transport;

            $hotelAmount = (float) $cost->hotel_total + (float) $cost->overseas_hotel_meal;

            if ($transportAmount > 0) {
                $fee = round($transportAmount * ($serviceFeePercent / 100), 0);
                $rowTotal = $transportAmount + $fee;
                $rows[] = [
                    'description' => "Transportasi {$empName} ({$origin} - {$dest}) tanggal {$dateText}",
                    'amount' => $transportAmount,
                    'service_fee' => $fee,
                    'total_amount' => $rowTotal,
                ];
                $subtotalAmount += $transportAmount;
                $totalServiceFee += $fee;
                $totalCost += $rowTotal;
            }

            if ($hotelAmount > 0) {
                $fee = round($hotelAmount * ($serviceFeePercent / 100), 0);
                $rowTotal = $hotelAmount + $fee;
                $rows[] = [
                    'description' => "Akomodasi / Hotel {$empName} di {$dest} tanggal {$dateText}",
                    'amount' => $hotelAmount,
                    'service_fee' => $fee,
                    'total_amount' => $rowTotal,
                ];
                $subtotalAmount += $hotelAmount;
                $totalServiceFee += $fee;
                $totalCost += $rowTotal;
            }
        }

        $manfeeAmount = round($totalCost * ($manfeePercent / 100), 0);

        return [
            'rows' => $rows,
            'subtotal_amount' => $subtotalAmount,
            'total_service_fee' => $totalServiceFee,
            'total_cost' => $totalCost,
            'manfee_percent' => $manfeePercent,
            'manfee_amount' => $manfeeAmount,
            'grand_total' => $totalCost + $manfeeAmount,
        ];
    }

    /**
     * Build Official Travel (Perjalanan Dinas) recap rows & totals from SPPD costs in this trip.
     *
     * @return array{rows: array<int, array<string, mixed>>, total_work_cost: float, manfee_percent: float, manfee_amount: float, grand_total: float}
     */
    public function buildOfficialTravelRecap(): array
    {
        $setting = ReimbursementSetting::current();
        $manfeePercent = (float) $setting->travel_manfee_percent;

        $sppds = $this->sppds()->with(['employee.department', 'cost'])->get();
        $rows = [];
        $totalWorkCost = 0.0;

        foreach ($sppds as $sppd) {
            $cost = $sppd->cost;
            if (! $cost) {
                continue;
            }

            $empName = $sppd->employee?->name ?? '-';
            $origin = $sppd->origin_location ?: 'Jakarta';
            $dest = $sppd->destination_location ?: ($this->destination ?? '-');
            $dateText = $this->formatSppdDateRange($sppd);
            $amount = (float) $cost->total_cost;

            $rows[] = [
                'description' => "SPPD {$empName} dari {$origin} ke {$dest} tanggal {$dateText}",
                'amount' => $amount,
            ];
            $totalWorkCost += $amount;
        }

        $manfeeAmount = round($totalWorkCost * ($manfeePercent / 100), 0);

        return [
            'rows' => $rows,
            'total_work_cost' => $totalWorkCost,
            'manfee_percent' => $manfeePercent,
            'manfee_amount' => $manfeeAmount,
            'grand_total' => $totalWorkCost + $manfeeAmount,
        ];
    }

    protected function formatSppdDateRange(Sppd $sppd): string
    {
        $dep = $sppd->departure_date
            ? \Carbon\Carbon::parse($sppd->departure_date)->locale('id')
            : ($this->start_date ? \Carbon\Carbon::parse($this->start_date)->locale('id') : null);
        $ret = $sppd->return_date
            ? \Carbon\Carbon::parse($sppd->return_date)->locale('id')
            : ($this->end_date ? \Carbon\Carbon::parse($this->end_date)->locale('id') : null);

        if ($dep && $ret && ! $dep->isSameDay($ret)) {
            return $dep->isSameMonth($ret) && $dep->isSameYear($ret)
                ? $dep->format('d') . '-' . $ret->translatedFormat('d F Y')
                : $dep->translatedFormat('d F Y') . ' - ' . $ret->translatedFormat('d F Y');
        }

        return $dep ? $dep->translatedFormat('d F Y') : '-';
    }
}
