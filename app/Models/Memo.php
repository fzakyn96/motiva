<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Memo extends Model
{
    protected $fillable = [
        'nomor_memo', 'department_id', 'creator_employee_id', 'to_recipient', 
        'title', 'content', 'date', 'status', 
        'assignment_start_date', 'assignment_end_date', 'agenda', 'location', 'participants'
    ];

    protected function casts(): array
    {
        return [
            'date' => 'datetime',
            'assignment_start_date' => 'datetime',
            'assignment_end_date' => 'datetime',
            'participants' => 'array',
        ];
    }
    public function department()
    {
        return $this->belongsTo(Department::class);
    }

    public function creator()
    {
        return $this->belongsTo(Employee::class, 'creator_employee_id');
    }

    public function businessTrips()
    {
        return $this->hasMany(BusinessTrip::class);
    }

    public function approvalRequests()
    {
        return $this->morphMany(ApprovalRequest::class, 'document');
    }

    /**
     * Hapus Memo (baik yang sudah disetujui maupun belum disetujui) beserta seluruh
     * SPPD, Biaya SPPD, dan Reimbursement terkait secara otomatis di SQL Server,
     * serta catat ke Log Memo, Log SPPD, Log Biaya SPPD, dan Log Reimbursement (Audit Trail).
     */
    public function deleteWithAudit(?User $user = null, ?string $reason = null): MemoLog
    {
        $user = $user ?? auth()->user();
        $trimmedReason = trim((string) $reason);

        if ($this->status === 'approved' && $trimmedReason === '') {
            throw new \InvalidArgumentException('Alasan penghapusan wajib diisi untuk menghapus Memo yang sudah disetujui.');
        }

        if ($trimmedReason === '') {
            $trimmedReason = 'Penghapusan dokumen Memo (Status: ' . ($this->status ?: 'draft') . ')';
        }

        return \Illuminate\Support\Facades\DB::transaction(function () use ($user, $trimmedReason) {
            $this->loadMissing([
                'department',
                'creator',
                'businessTrips.sppds.employee.department',
                'businessTrips.sppds.cost',
            ]);

            $deleterId = $user?->id;
            $deleterName = $user?->employee?->name ?? $user?->name ?? 'System';
            $deleterRole = $user ? $user->getRoleNames()->implode(', ') : null;

            $sppdNumbers = $this->businessTrips
                ->flatMap(fn ($trip) => $trip->sppds)
                ->map(fn ($sppd) => [
                    'sppd_number' => $sppd->sppd_number,
                    'employee_name' => $sppd->employee?->name,
                    'status' => $sppd->status,
                ])
                ->values()
                ->all();

            $log = MemoLog::create([
                'memo_id' => $this->id,
                'nomor_memo' => $this->nomor_memo,
                'title' => $this->title,
                'agenda' => $this->agenda ?: $this->title,
                'to_recipient' => $this->to_recipient,
                'department_name' => $this->department?->name,
                'creator_name' => $this->creator?->name,
                'memo_date' => $this->date ? $this->date->toDateString() : ($this->created_at ? $this->created_at->toDateString() : now()->toDateString()),
                'memo_status' => $this->status ?: 'draft',
                'action' => $this->status === 'approved' ? 'deleted_approved_memo' : 'deleted_memo',
                'deletion_reason' => $trimmedReason,
                'deleted_by_user_id' => $deleterId,
                'deleted_by_name' => $deleterName,
                'deleted_by_role' => $deleterRole,
                'memo_snapshot' => [
                    'nomor_memo' => $this->nomor_memo,
                    'title' => $this->title,
                    'agenda' => $this->agenda,
                    'to_recipient' => $this->to_recipient,
                    'location' => $this->location,
                    'date' => $this->date?->format('Y-m-d'),
                    'assignment_start_date' => $this->assignment_start_date?->format('Y-m-d'),
                    'assignment_end_date' => $this->assignment_end_date?->format('Y-m-d'),
                    'content' => $this->content,
                    'participants' => $this->participants ?? [],
                    'related_sppds' => $sppdNumbers,
                ],
            ]);

            // Cleanup morph ApprovalRequest & DocumentSignature for Memo
            $memoApprovalRequests = ApprovalRequest::where('document_type', self::class)
                ->where('document_id', $this->id)
                ->get();
            foreach ($memoApprovalRequests as $req) {
                $req->steps()->delete();
                $req->delete();
            }
            DocumentSignature::where('document_type', self::class)
                ->where('document_id', $this->id)
                ->delete();

            // Cleanup BusinessTrips, SPPDs, Biaya SPPD, & Reimbursements + Audit Trail Logs
            foreach ($this->businessTrips as $trip) {
                foreach ($trip->sppds as $sppd) {
                    $sppdDeptName = $sppd->employee?->department?->name ?? $this->department?->name;
                    $sppdDest = $sppd->destination_location ?: ($trip->destination ?: $this->location);
                    $depDate = $sppd->departure_date ?? $trip->start_date ?? $this->assignment_start_date;
                    $retDate = $sppd->return_date ?? $trip->end_date ?? $this->assignment_end_date;

                    $sppdLog = SppdLog::create([
                        'memo_log_id' => $log->id,
                        'sppd_id' => $sppd->id,
                        'sppd_number' => $sppd->sppd_number,
                        'nomor_memo' => $this->nomor_memo,
                        'employee_name' => $sppd->employee?->name,
                        'department_name' => $sppdDeptName,
                        'destination' => $sppdDest,
                        'departure_date' => $depDate ? \Carbon\Carbon::parse($depDate)->toDateString() : null,
                        'return_date' => $retDate ? \Carbon\Carbon::parse($retDate)->toDateString() : null,
                        'total_days' => $sppd->total_days ?: ($depDate && $retDate ? \Carbon\Carbon::parse($depDate)->diffInDays(\Carbon\Carbon::parse($retDate)) + 1 : 1),
                        'sppd_status' => $sppd->status ?: 'draft',
                        'action' => 'deleted_via_memo',
                        'deletion_reason' => $trimmedReason,
                        'deleted_by_user_id' => $deleterId,
                        'deleted_by_name' => $deleterName,
                        'deleted_by_role' => $deleterRole,
                        'sppd_snapshot' => [
                            'sppd_number' => $sppd->sppd_number,
                            'nomor_memo' => $this->nomor_memo,
                            'employee_name' => $sppd->employee?->name,
                            'travel_grade' => $sppd->travel_grade,
                            'origin_location' => $sppd->origin_location,
                            'destination_location' => $sppdDest,
                            'assignment_purpose' => $sppd->assignment_purpose ?: ($trip->activity_name ?: $this->agenda),
                            'transportation_mode' => $sppd->transportation_mode,
                            'travel_category' => $sppd->travel_category,
                            'status' => $sppd->status,
                        ],
                    ]);

                    // Audit Trail & Cleanup Biaya SPPD (SppdCost)
                    if ($sppd->cost) {
                        $cost = $sppd->cost;
                        SppdCostLog::create([
                            'memo_log_id' => $log->id,
                            'sppd_log_id' => $sppdLog->id,
                            'sppd_cost_id' => $cost->id,
                            'sppd_id' => $sppd->id,
                            'sppd_number' => $sppd->sppd_number,
                            'nomor_memo' => $this->nomor_memo,
                            'employee_name' => $sppd->employee?->name,
                            'department_name' => $sppdDeptName,
                            'base_type' => $cost->base_type,
                            'official_days' => (int) $cost->official_days,
                            'subtotal_transport' => (float) $cost->subtotal_transport,
                            'subtotal_per_diem' => (float) $cost->subtotal_per_diem,
                            'subtotal_other' => (float) $cost->subtotal_other,
                            'total_cost' => (float) $cost->total_cost,
                            'action' => 'deleted_via_memo',
                            'deletion_reason' => $trimmedReason,
                            'deleted_by_user_id' => $deleterId,
                            'deleted_by_name' => $deleterName,
                            'deleted_by_role' => $deleterRole,
                            'cost_snapshot' => $cost->toArray(),
                        ]);

                        DocumentSignature::where('document_type', SppdCost::class)
                            ->where('document_id', $cost->id)
                            ->delete();
                        $cost->delete();
                    }

                    // Audit Trail & Cleanup Reimbursement
                    $reimbursements = Reimbursement::with(['employee.department', 'expenses'])
                        ->where('sppd_id', $sppd->id)
                        ->get();
                    foreach ($reimbursements as $reimb) {
                        ReimbursementLog::create([
                            'memo_log_id' => $log->id,
                            'sppd_log_id' => $sppdLog->id,
                            'reimbursement_id' => $reimb->id,
                            'sppd_id' => $sppd->id,
                            'reimbursement_number' => $reimb->reimbursement_number,
                            'sppd_number' => $sppd->sppd_number,
                            'nomor_memo' => $this->nomor_memo,
                            'employee_name' => $reimb->employee?->name ?? $sppd->employee?->name,
                            'department_name' => $reimb->employee?->department?->name ?? $sppdDeptName,
                            'title' => $reimb->title,
                            'total_amount' => (float) $reimb->total_amount,
                            'total_claim_amount' => (float) $reimb->total_claim_amount,
                            'expenses_count' => $reimb->expenses->count(),
                            'reimbursement_status' => $reimb->status ?: 'draft',
                            'action' => 'deleted_via_memo',
                            'deletion_reason' => $trimmedReason,
                            'deleted_by_user_id' => $deleterId,
                            'deleted_by_name' => $deleterName,
                            'deleted_by_role' => $deleterRole,
                            'reimbursement_snapshot' => [
                                'reimbursement_number' => $reimb->reimbursement_number,
                                'title' => $reimb->title,
                                'notes' => $reimb->notes,
                                'status' => $reimb->status,
                                'total_amount' => (float) $reimb->total_amount,
                                'total_claim_amount' => (float) $reimb->total_claim_amount,
                                'expenses' => $reimb->expenses->map(fn ($exp) => [
                                    'expense_type' => $exp->expense_type,
                                    'description' => $exp->description,
                                    'amount' => (float) $exp->amount,
                                    'is_company_paid' => (bool) $exp->is_company_paid,
                                ])->values()->all(),
                            ],
                        ]);

                        $rApprovals = ApprovalRequest::where('document_type', Reimbursement::class)
                            ->where('document_id', $reimb->id)
                            ->get();
                        foreach ($rApprovals as $rReq) {
                            $rReq->steps()->delete();
                            $rReq->delete();
                        }
                        DocumentSignature::where('document_type', Reimbursement::class)
                            ->where('document_id', $reimb->id)
                            ->delete();
                        $reimb->expenses()->delete();
                        $reimb->delete();
                    }

                    $sppdApprovals = ApprovalRequest::where('document_type', Sppd::class)
                        ->where('document_id', $sppd->id)
                        ->get();
                    foreach ($sppdApprovals as $sReq) {
                        $sReq->steps()->delete();
                        $sReq->delete();
                    }
                    DocumentSignature::where('document_type', Sppd::class)
                        ->where('document_id', $sppd->id)
                        ->delete();

                    $sppd->delete();
                }

                $trip->delete();
            }

            $this->delete();

            return $log;
        });
    }
}
