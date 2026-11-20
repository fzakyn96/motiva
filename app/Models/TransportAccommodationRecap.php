<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TransportAccommodationRecap extends Model
{
    protected $fillable = [
        'recap_number',
        'department_id',
        'business_trip_id',
        'business_trip_ids',
        'work_name',
        'period_label',
        'period_month',
        'subtotal_amount',
        'total_service_fee',
        'total_cost',
        'manfee_percent',
        'manfee_amount',
        'grand_total',
        'prepared_by_name',
        'prepared_by_position',
        'checked_by_name',
        'checked_by_position',
        'approved_by_name',
        'approved_by_position',
        'creator_id',
    ];

    protected function casts(): array
    {
        return [
            'subtotal_amount' => 'decimal:2',
            'total_service_fee' => 'decimal:2',
            'total_cost' => 'decimal:2',
            'manfee_percent' => 'decimal:2',
            'manfee_amount' => 'decimal:2',
            'grand_total' => 'decimal:2',
            'business_trip_ids' => 'array',
        ];
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

    protected static function booted(): void
    {
        static::saving(function (self $recap): void {
            $setting = ReimbursementSetting::current();
            $trip = $recap->business_trip_id
                ? BusinessTrip::with(['memo.department', 'sppds.employee.department'])->find($recap->business_trip_id)
                : null;

            if (empty($recap->period_month)) {
                $rawDate = $trip?->sppds?->pluck('departure_date')->filter()->sort()->first() ?? $trip?->start_date ?? now();
                $recap->period_month = \Carbon\Carbon::parse($rawDate)->locale('id')->translatedFormat('F Y');
            }

            if (empty($recap->period_label)) {
                $recap->period_label = $recap->period_month;
            }

            if (empty($recap->work_name)) {
                $unitNames = $trip?->sppds?->map(fn ($s) => $s->employee?->department?->name)->filter()->unique()->values() ?? collect();
                if ($unitNames->isEmpty() && $recap->department_id) {
                    $deptName = Department::whereKey($recap->department_id)->value('name');
                    if ($deptName) {
                        $unitNames = collect([$deptName]);
                    }
                }
                if ($unitNames->isEmpty() && $trip?->memo?->department?->name) {
                    $unitNames = collect([$trip->memo->department->name]);
                }
                $recap->work_name = 'Pekerjaan ' . ($unitNames->isNotEmpty() ? $unitNames->implode(', ') : ($trip?->activity_name ?: 'Perjalanan Dinas'));
            }

            if ($recap->manfee_percent === null) {
                $recap->manfee_percent = $setting->transport_manfee_percent;
            }

            $sig = $trip?->resolveRecapSignatories() ?? [
                'prepared_by' => Employee::with('position')->whereHas('position', fn ($q) => $q->where('code', 'BDH')->orWhere('name', 'like', '%Business Division Head%'))->first(),
                'checked_by' => collect(),
                'approved_by' => Employee::with('position')->whereHas('position', fn ($q) => $q->where('level', 0)->orWhere('name', 'like', '%Division Head%'))->first(),
            ];

            if (empty($recap->prepared_by_name) && $sig['prepared_by']) {
                $recap->prepared_by_name = $sig['prepared_by']->name;
                $recap->prepared_by_position = $sig['prepared_by']->position?->name ?? 'Business Division Head';
            }
            if (empty($recap->checked_by_name) && $sig['checked_by']->isNotEmpty()) {
                $checker = $sig['checked_by']->first();
                $recap->checked_by_name = $checker->name;
                $recap->checked_by_position = $checker->position?->name ?? 'Manajer';
            }
            if (empty($recap->approved_by_name) && $sig['approved_by']) {
                $recap->approved_by_name = $sig['approved_by']->name;
                $recap->approved_by_position = $sig['approved_by']->position?->name ?? 'Division Head';
            }
            if (empty($recap->recap_number)) {
                $recap->recap_number = static::generateRecapNumber($recap);
            }
        });

        static::deleting(function (TransportAccommodationRecap $recap): void {
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
        return $this->hasMany(TransportAccommodationRecapItem::class)->orderBy('id');
    }

    public function recalculateTotals(): void
    {
        $items = $this->items()->get();
        $subtotalAmount = 0.0;
        $totalServiceFee = 0.0;
        $totalCost = 0.0;

        foreach ($items as $item) {
            $itemTotal = (float) $item->amount + (float) $item->service_fee;
            if ((float) $item->total_amount !== $itemTotal) {
                $item->updateQuietly(['total_amount' => $itemTotal]);
            }
            $subtotalAmount += (float) $item->amount;
            $totalServiceFee += (float) $item->service_fee;
            $totalCost += $itemTotal;
        }

        $maxPercent = (float) ReimbursementSetting::current()->transport_manfee_percent;
        $rawPercent = (float) ($this->manfee_percent ?? $maxPercent);
        $percent = max(0.0, min($rawPercent, $maxPercent));
        $manfeeAmount = round($totalCost * ($percent / 100), 0);

        $this->subtotal_amount = $subtotalAmount;
        $this->total_service_fee = $totalServiceFee;
        $this->total_cost = $totalCost;
        $this->manfee_percent = $percent;
        $this->manfee_amount = $manfeeAmount;
        $this->grand_total = $totalCost + $manfeeAmount;
        $this->saveQuietly();
    }
}
