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
        });
    }

    public function resolveSignatories(): array
    {
        $ids = is_array($this->business_trip_ids) ? $this->business_trip_ids : [];
        if ($this->business_trip_id && ! in_array($this->business_trip_id, $ids)) {
            $ids[] = $this->business_trip_id;
        }

        return BusinessTrip::resolveSignatoriesForTrips($ids, $this->department_id);
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
        $percent = (float) ($this->manfee_percent ?? ReimbursementSetting::current()->travel_manfee_percent);
        $manfeeAmount = round($totalWorkCost * ($percent / 100), 0);

        $this->total_work_cost = $totalWorkCost;
        $this->manfee_percent = $percent;
        $this->manfee_amount = $manfeeAmount;
        $this->grand_total = $totalWorkCost + $manfeeAmount;
        $this->saveQuietly();
    }

    public static function syncForMonth(int $month, int $year, ?int $creatorId = null): ?self
    {
        $start = \Carbon\Carbon::create($year, $month, 1)->startOfDay();
        $end = $start->copy()->endOfMonth()->endOfDay();

        $approvedCosts = SppdCost::with(['sppd.employee', 'sppd.businessTrip.memo'])
            ->where('status', 'approved')
            ->whereHas('sppd', function ($q) use ($start, $end) {
                $q->whereBetween('departure_date', [$start->toDateString(), $end->toDateString()])
                    ->orWhere(function ($cq) use ($start, $end) {
                        $cq->whereNull('departure_date')
                            ->whereBetween('created_at', [$start, $end]);
                    });
            })
            ->orderBy('id')
            ->get();

        if ($approvedCosts->isEmpty()) {
            return null;
        }

        $periodMonth = sprintf('%04d-%02d', $year, $month);
        $periodLabel = $start->copy()->locale('id')->translatedFormat('F Y');
        $monthNameId = $start->copy()->locale('id')->translatedFormat('F');

        $tripIds = $approvedCosts->pluck('sppd.business_trip_id')->filter()->unique()->values()->all();
        $firstSppd = $approvedCosts->first()->sppd;
        $departmentId = $firstSppd?->employee?->department_id ?? $firstSppd?->businessTrip?->memo?->department_id;
        $setting = ReimbursementSetting::current();

        $recap = static::where(function ($q) use ($periodMonth, $periodLabel, $monthNameId, $year) {
            $q->where('period_month', $periodMonth)
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
                'business_trip_id' => $tripIds[0] ?? null,
                'business_trip_ids' => $tripIds,
                'work_name' => 'Rekap Perjalanan Dinas ' . $periodLabel,
                'period_label' => $periodLabel,
                'period_month' => $periodMonth,
                'manfee_percent' => $setting->travel_manfee_percent,
                'creator_id' => $creatorId ?? auth()->id(),
            ]);
        } else {
            $recap->update([
                'business_trip_id' => $tripIds[0] ?? $recap->business_trip_id,
                'business_trip_ids' => $tripIds,
                'period_month' => $periodMonth,
                'period_label' => $periodLabel,
            ]);
        }

        $recap->items()->delete();

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
                'sort_order' => $index + 1,
            ]);
        }

        $recap->recalculateTotals();

        return $recap;
    }

    /**
     * @return array{months: int, items: int}
     */
    public static function syncAllMissingMonths(?int $creatorId = null): array
    {
        $syncedCostIds = OfficialTravelRecapItem::whereNotNull('sppd_cost_id')
            ->pluck('sppd_cost_id')
            ->all();

        $unsyncedCosts = SppdCost::with('sppd')
            ->where('status', 'approved')
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
            $recap = static::syncForMonth($month, $year, $creatorId);
            if ($recap) {
                $syncedMonths++;
                $syncedItems += $recap->items()->count();
            }
        }

        return ['months' => $syncedMonths, 'items' => $syncedItems];
    }
}
