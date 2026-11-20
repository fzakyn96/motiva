<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Reimbursement extends Model
{
    use HasFactory;

    protected $fillable = [
        'reimbursement_number',
        'sppd_id',
        'employee_id',
        'title',
        'notes',
        'total_amount',
        'total_claim_amount',
        'status',
        'submitted_at',
        'approved_at',
        'rejected_at',
        'rejection_note',
    ];

    protected function casts(): array
    {
        return [
            'total_amount' => 'decimal:2',
            'total_claim_amount' => 'decimal:2',
            'submitted_at' => 'datetime',
            'approved_at' => 'datetime',
            'rejected_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Reimbursement $reimbursement) {
            if ($reimbursement->status === 'approved' && empty($reimbursement->reimbursement_number)) {
                $reimbursement->reimbursement_number = static::generateNumber();
            }
        });

        static::updating(function (Reimbursement $reimbursement) {
            if ($reimbursement->status === 'approved' && empty($reimbursement->reimbursement_number)) {
                $reimbursement->reimbursement_number = static::generateNumber();
            }
        });

        static::deleting(function (Reimbursement $reimbursement) {
            $reimbursement->expenses()->delete();
            $reimbursement->approvalRequests()->each(function ($req) {
                $req->steps()->delete();
                $req->delete();
            });
        });
    }

    public static function generateNumber(?Employee $employee = null): string
    {
        $year = date('Y');
        $month = date('m');
        $deptCode = $employee?->department?->code
            ?? Department::where('code', 'MESI')->value('code')
            ?? 'MESI';
        $areaCode = $employee?->workArea?->code ?? 'JKT';

        $count = static::whereYear('created_at', $year)->whereNotNull('reimbursement_number')->count();
        $seq = $count + 1;

        do {
            $nextSeq = str_pad((string) $seq, 4, '0', STR_PAD_LEFT);
            $candidate = "{$nextSeq}/RMB/{$deptCode}/{$areaCode}/{$month}/{$year}";
            $seq++;
        } while (static::where('reimbursement_number', $candidate)->exists());

        return $candidate;
    }

    /**
     * Hitung ulang total biaya keseluruhan dan total yang diklaim oleh pegawai
     */
    public function recalculateTotals(): void
    {
        $expenses = $this->expenses()->get();
        $this->total_amount = $expenses->sum('amount');
        $this->total_claim_amount = $expenses->where('is_company_paid', false)->sum('amount');
        $this->saveQuietly();
    }

    public function sppd()
    {
        return $this->belongsTo(Sppd::class);
    }

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    public function expenses()
    {
        return $this->hasMany(Expense::class);
    }

    public function approvalRequests()
    {
        return $this->morphMany(ApprovalRequest::class, 'document');
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isApproved(): bool
    {
        return $this->status === 'approved';
    }

    public function isDraft(): bool
    {
        return $this->status === 'draft';
    }

    public function isRejected(): bool
    {
        return $this->status === 'rejected';
    }

    /**
     * Hapus Reimbursement dan catat ke Log Reimbursement (Audit Trail).
     */
    public function deleteWithAudit(?User $user = null, ?string $reason = null): ReimbursementLog
    {
        $user = $user ?? auth()->user();
        $trimmedReason = trim((string) $reason) ?: 'Penghapusan dokumen Reimbursement (Status: ' . ($this->status ?: 'draft') . ')';

        return \Illuminate\Support\Facades\DB::transaction(function () use ($user, $trimmedReason) {
            $this->loadMissing(['employee.department', 'sppd.businessTrip.memo', 'expenses']);

            $log = ReimbursementLog::create([
                'reimbursement_id' => $this->id,
                'sppd_id' => $this->sppd_id,
                'reimbursement_number' => $this->reimbursement_number,
                'sppd_number' => $this->sppd?->sppd_number,
                'nomor_memo' => $this->sppd?->businessTrip?->memo?->nomor_memo,
                'employee_name' => $this->employee?->name,
                'department_name' => $this->employee?->department?->name,
                'title' => $this->title,
                'total_amount' => (float) $this->total_amount,
                'total_claim_amount' => (float) $this->total_claim_amount,
                'expenses_count' => $this->expenses->count(),
                'reimbursement_status' => $this->status ?: 'draft',
                'action' => 'deleted_reimbursement',
                'deletion_reason' => $trimmedReason,
                'deleted_by_user_id' => $user?->id,
                'deleted_by_name' => $user?->employee?->name ?? $user?->name ?? 'System',
                'deleted_by_role' => $user ? $user->getRoleNames()->implode(', ') : null,
                'reimbursement_snapshot' => [
                    'reimbursement_number' => $this->reimbursement_number,
                    'title' => $this->title,
                    'notes' => $this->notes,
                    'status' => $this->status,
                    'total_amount' => (float) $this->total_amount,
                    'total_claim_amount' => (float) $this->total_claim_amount,
                    'expenses' => $this->expenses->map(fn ($exp) => [
                        'expense_type' => $exp->expense_type,
                        'description' => $exp->description,
                        'amount' => (float) $exp->amount,
                        'is_company_paid' => (bool) $exp->is_company_paid,
                    ])->values()->all(),
                ],
            ]);

            DocumentSignature::where('document_type', self::class)
                ->where('document_id', $this->id)
                ->delete();

            $this->delete();

            return $log;
        });
    }
}
