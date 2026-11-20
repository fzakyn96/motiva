<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OfficialTravelRecap extends Model
{
    protected $fillable = [
        'recap_number',
        'department_id',
        'business_trip_id',
        'business_trip_ids',
        'work_name',
        'period_label',
        'period_month',
        'total_work_cost',
        'manfee_percent',
        'manfee_amount',
        'grand_total',
        'prepared_by_name',
        'prepared_by_position',
        'checked_by_list',
        'approved_by_name',
        'approved_by_position',
        'creator_id',
    ];

    protected function casts(): array
    {
        return [
            'total_work_cost' => 'decimal:2',
            'manfee_percent' => 'decimal:2',
            'manfee_amount' => 'decimal:2',
            'grand_total' => 'decimal:2',
            'business_trip_ids' => 'array',
            'checked_by_list' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (OfficialTravelRecap $recap): void {
            $setting = ReimbursementSetting::current();
            $ids = is_array($recap->business_trip_ids) ? $recap->business_trip_ids : [];
            if ($recap->business_trip_id && ! in_array($recap->business_trip_id, $ids)) {
                $ids[] = $recap->business_trip_id;
            }
            $trips = ! empty($ids)
                ? BusinessTrip::with(['memo.department', 'sppds.employee.department'])->whereIn('id', $ids)->get()
                : collect();
            $firstTrip = $trips->first();
            $allSppds = $trips->flatMap->sppds;

            if (empty($recap->department_id)) {
                $recap->department_id = $allSppds->first()?->employee?->department_id
                    ?? $firstTrip?->memo?->department_id
                    ?? Department::where('code', 'MESI')->value('id')
                    ?? Department::value('id');
            }

            $rawDate = $allSppds->pluck('departure_date')->filter()->sort()->first()
                ?? $firstTrip?->start_date
                ?? now();
            $periodDate = \Carbon\Carbon::parse($rawDate)->locale('id');

            if (empty($recap->period_month)) {
                $recap->period_month = $periodDate->translatedFormat('F');
            }

            if (empty($recap->period_label)) {
                $recap->period_label = $periodDate->translatedFormat('F Y');
            }

            if (empty($recap->work_name)) {
                $unitNames = $allSppds->map(fn ($s) => $s->employee?->department?->name)->filter()->unique()->values();
                if ($unitNames->isEmpty() && $recap->department_id) {
                    $deptName = Department::whereKey($recap->department_id)->value('name');
                    if ($deptName) {
                        $unitNames = collect([$deptName]);
                    }
                }
                if ($unitNames->isEmpty() && $firstTrip?->memo?->department?->name) {
                    $unitNames = collect([$firstTrip->memo->department->name]);
                }
                $recap->work_name = 'Pekerjaan ' . ($unitNames->isNotEmpty() ? $unitNames->implode(', ') : ($firstTrip?->activity_name ?: 'Perjalanan Dinas'));
            }

            if ($recap->manfee_percent === null) {
                $recap->manfee_percent = $setting->travel_manfee_percent;
            }

            $sig = $recap->resolveSignatories();

            if (empty($recap->prepared_by_name) && ! empty($sig['prepared_by'])) {
                $recap->prepared_by_name = $sig['prepared_by']->name;
                $recap->prepared_by_position = $sig['prepared_by']->position?->name ?? 'Business Division Head';
            }

            if (empty($recap->checked_by_list) && ! empty($sig['checked_by']) && $sig['checked_by']->isNotEmpty()) {
                $recap->checked_by_list = $sig['checked_by']
                    ->map(fn ($emp) => [
                        'name' => $emp->name,
                        'position' => $emp->position?->name ?? 'Manajer',
                    ])
                    ->values()
                    ->all();
            }

            if (empty($recap->approved_by_name) && ! empty($sig['approved_by'])) {
                $recap->approved_by_name = $sig['approved_by']->name;
                $recap->approved_by_position = $sig['approved_by']->position?->name ?? 'Division Head';
            }

            if (empty($recap->recap_number)) {
                $recap->recap_number = static::generateRecapNumber($recap);
            }
        });

        static::deleting(function (OfficialTravelRecap $recap): void {
            $recap->items()->delete();

            ApprovalRequest::where('document_type', static::class)
                ->where('document_id', $recap->id)
                ->get()
                ->each(function (ApprovalRequest $req): void {
                    $req->steps()->delete();
                    $req->delete();
                });

            if (\Illuminate\Support\Facades\Schema::hasTable('document_signatures')) {
                DocumentSignature::where('document_type', static::class)
                    ->where('document_id', $recap->id)
                    ->delete();
            }
        });
    }

    public function resolveSignatories(): array
    {
        return BusinessTrip::resolveSignatoriesForRecap($this);
    }

    public static function generateRecapNumber(self $recap): string
    {
        $year = date('Y');
        $month = date('m');

        $deptCode = Department::whereKey($recap->department_id)->value('code')
            ?? Department::where('code', 'MESI')->value('code')
            ?? 'MESI';

        $areaCode = 'JKT';
        if (\Illuminate\Support\Facades\Schema::hasTable('work_areas')) {
            $involvedAreaIds = BusinessTrip::resolveInvolvedWorkAreaIdsForRecap($recap);
            if (count($involvedAreaIds) === 1) {
                $areaCode = WorkArea::whereKey($involvedAreaIds[0])->value('code') ?: 'JKT';
            } elseif (empty($involvedAreaIds) && $recap->creator_id) {
                $creatorAreaCode = Employee::where('user_id', $recap->creator_id)
                    ->with('workArea')
                    ->first()
                    ?->workArea?->code;
                if ($creatorAreaCode) {
                    $areaCode = $creatorAreaCode;
                }
            }
        }

        $count = static::whereYear('created_at', $year)->whereNotNull('recap_number')->count();
        $seq = $count + 1;

        do {
            $nextSeq = str_pad((string) $seq, 4, '0', STR_PAD_LEFT);
            $candidate = "{$nextSeq}/{$deptCode}/{$areaCode}/{$month}/{$year}";
            $seq++;
        } while (static::where('recap_number', $candidate)->exists());

        return $candidate;
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function businessTrip(): BelongsTo
    {
        return $this->belongsTo(BusinessTrip::class);
    }

    public function approvalRequests()
    {
        return $this->morphMany(ApprovalRequest::class, 'document');
    }

    public function ensureApprovalRequestExists(?Employee $requester = null): ApprovalRequest
    {
        return BusinessTrip::createRecapApprovalFor($this, $requester, $this->department_id);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creator_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(OfficialTravelRecapItem::class)->orderBy('id');
    }

    public function recalculateTotals(): void
    {
        $totalWorkCost = (float) $this->items()->sum('amount');
        $maxPercent = (float) ReimbursementSetting::current()->travel_manfee_percent;
        $rawPercent = (float) ($this->manfee_percent ?? $maxPercent);
        $percent = max(0.0, min($rawPercent, $maxPercent));
        $manfeeAmount = round($totalWorkCost * ($percent / 100), 0);

        $this->total_work_cost = $totalWorkCost;
        $this->manfee_percent = $percent;
        $this->manfee_amount = $manfeeAmount;
        $this->grand_total = $totalWorkCost + $manfeeAmount;
        $this->saveQuietly();
    }

    public static function syncForMonth(int $month, int $year, ?int $creatorId = null, ?int $workAreaId = null): ?self
    {
        $start = \Carbon\Carbon::create($year, $month, 1)->startOfDay();
        $end = $start->copy()->endOfMonth()->endOfDay();

        $approvedCostsQuery = SppdCost::with(['sppd.employee', 'sppd.businessTrip.memo'])
            ->where('status', 'approved')
            ->whereHas('sppd', function ($q) use ($start, $end, $workAreaId) {
                $q->where(function ($dq) use ($start, $end) {
                    $dq->whereBetween('departure_date', [$start->toDateString(), $end->toDateString()])
                        ->orWhere(function ($cq) use ($start, $end) {
                            $cq->whereNull('departure_date')
                                ->whereBetween('created_at', [$start, $end]);
                        });
                });

                if ($workAreaId) {
                    $q->whereHas('employee', fn ($eq) => $eq->where('work_area_id', $workAreaId));
                }
            })
            ->orderBy('id');

        $approvedCosts = $approvedCostsQuery->get();

        if ($approvedCosts->isEmpty()) {
            return null;
        }

        $periodMonthCode = sprintf('%04d-%02d', $year, $month);
        $periodLabel = $start->copy()->locale('id')->translatedFormat('F Y');
        $monthNameId = $start->copy()->locale('id')->translatedFormat('F');

        $newTripIds = $approvedCosts->pluck('sppd.business_trip_id')->filter()->unique()->values()->all();
        $firstSppd = $approvedCosts->first()->sppd;
        $departmentId = $firstSppd?->employee?->department_id
            ?? $firstSppd?->businessTrip?->memo?->department_id
            ?? Department::where('code', 'MESI')->value('id')
            ?? Department::value('id');
        $deptName = $departmentId ? Department::whereKey($departmentId)->value('name') : null;
        $workName = 'Pekerjaan ' . ($deptName ?: 'Management Enterprise System Infrastructure Information (MESI)');
        $setting = ReimbursementSetting::current();

        $recap = static::where(function ($q) use ($periodMonthCode, $periodLabel, $monthNameId, $year) {
            $q->where('period_month', $periodMonthCode)
                ->orWhere('period_label', $periodLabel)
                ->orWhere(function ($mq) use ($monthNameId, $year) {
                    $mq->where('period_month', $monthNameId)
                        ->whereYear('created_at', $year);
                });
        })
            ->latest('id')
            ->first();

        if ($recap && $recap->approvalRequests()->where('status', 'approved')->exists()) {
            return $recap;
        }

        if (! $recap) {
            $recap = static::create([
                'department_id' => $departmentId,
                'business_trip_id' => $newTripIds[0] ?? null,
                'business_trip_ids' => $newTripIds,
                'work_name' => $workName,
                'period_label' => $periodLabel,
                'period_month' => $monthNameId,
                'manfee_percent' => $setting->travel_manfee_percent,
                'creator_id' => $creatorId ?? auth()->id(),
            ]);
        } else {
            $existingTripIds = is_array($recap->business_trip_ids) ? $recap->business_trip_ids : [];
            if ($recap->business_trip_id) {
                $existingTripIds[] = $recap->business_trip_id;
            }
            $mergedTripIds = array_values(array_unique(array_filter(array_merge($existingTripIds, $newTripIds))));

            $recap->update([
                'department_id' => $departmentId ?? $recap->department_id,
                'business_trip_id' => $mergedTripIds[0] ?? $recap->business_trip_id,
                'business_trip_ids' => $mergedTripIds,
                'work_name' => $workName,
                'period_month' => $monthNameId,
                'period_label' => $periodLabel,
                'manfee_percent' => $recap->manfee_percent ?? $setting->travel_manfee_percent,
            ]);
        }

        if ($workAreaId) {
            $recap->items()
                ->whereHas('sppd.employee', fn ($eq) => $eq->where('work_area_id', $workAreaId))
                ->delete();
        } else {
            $recap->items()->delete();
        }

        $baseSort = (int) $recap->items()->max('sort_order');

        foreach ($approvedCosts as $index => $cost) {
            $sppd = $cost->sppd;
            if (! $sppd) {
                continue;
            }
            $empName = $sppd->employee?->name ?? 'Pegawai';
            $origin = $sppd->origin_location ?: 'Jakarta';
            $dest = $sppd->destination_location ?: ($sppd->businessTrip?->destination ?? '-');
            $depDate = $sppd->departure_date
                ? \Carbon\Carbon::parse($sppd->departure_date)->locale('id')->translatedFormat('d F Y')
                : '';
            $retDate = $sppd->return_date
                ? \Carbon\Carbon::parse($sppd->return_date)->locale('id')->translatedFormat('d F Y')
                : '';
            $dateRange = ($depDate && $retDate) ? " tanggal {$depDate} s/d {$retDate}" : '';

            $recap->items()->create([
                'sppd_id' => $sppd->id,
                'sppd_cost_id' => $cost->id,
                'description' => "SPPD {$empName} dari {$origin} ke {$dest}{$dateRange}",
                'amount' => (float) $cost->total_cost,
                'sort_order' => $baseSort + $index + 1,
            ]);
        }

        $recap->recalculateTotals();

        $pendingApproval = $recap->approvalRequests()->where('status', 'pending')->latest('id')->first();
        if ($pendingApproval) {
            $sig = BusinessTrip::resolveSignatoriesForRecap($recap);
            BusinessTrip::syncPendingRecapManagerSteps($pendingApproval, max(1, $sig['checked_by']->count()));
        }

        return $recap;
    }

    /**
     * @return array{months: int, items: int}
     */
    public static function syncAllMissingMonths(?int $creatorId = null, ?int $workAreaId = null): array
    {
        $syncedCostIds = OfficialTravelRecapItem::whereNotNull('sppd_cost_id')
            ->when($workAreaId, fn ($q) => $q->whereHas('sppd.employee', fn ($eq) => $eq->where('work_area_id', $workAreaId)))
            ->pluck('sppd_cost_id')
            ->all();

        $unsyncedCosts = SppdCost::with('sppd')
            ->where('status', 'approved')
            ->when($workAreaId, fn ($q) => $q->whereHas('sppd.employee', fn ($eq) => $eq->where('work_area_id', $workAreaId)))
            ->when(! empty($syncedCostIds), fn ($q) => $q->whereNotIn('id', $syncedCostIds))
            ->get();

        if ($unsyncedCosts->isEmpty()) {
            return ['months' => 0, 'items' => 0];
        }

        $periods = $unsyncedCosts
            ->map(function (SppdCost $cost): array {
                $date = $cost->sppd?->departure_date
                    ? \Carbon\Carbon::parse($cost->sppd->departure_date)
                    : ($cost->created_at ?? now());

                return [(int) $date->year, (int) $date->month];
            })
            ->unique(fn (array $pair) => sprintf('%04d-%02d', $pair[0], $pair[1]))
            ->values();

        $syncedMonths = 0;
        $syncedItems = 0;

        foreach ($periods as [$year, $month]) {
            $recap = static::syncForMonth($month, $year, $creatorId, $workAreaId);
            if ($recap) {
                $syncedMonths++;
                $syncedItems += $recap->items()
                    ->when($workAreaId, fn ($q) => $q->whereHas('sppd.employee', fn ($eq) => $eq->where('work_area_id', $workAreaId)))
                    ->count();
            }
        }

        return ['months' => $syncedMonths, 'items' => $syncedItems];
    }
}
