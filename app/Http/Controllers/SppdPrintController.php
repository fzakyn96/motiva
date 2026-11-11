<?php

namespace App\Http\Controllers;

use App\Models\ApprovalRequest;
use App\Models\Employee;
use App\Models\Sppd;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;

class SppdPrintController extends Controller
{
    public function __invoke(Sppd $sppd)
    {
        $sppd->load([
            'employee.position',
            'employee.department',
            'businessTrip.memo.creator.manager.position',
            'businessTrip.memo.creator.position',
        ]);

        // Resolve authorizing official (Pejabat yang memberi perintah / User)
        $approvalRequest = ApprovalRequest::where('document_type', Sppd::class)
            ->where('document_id', $sppd->id)
            ->first();

        $finalApprovedStep = $approvalRequest?->steps()
            ->where('status', 'approved')
            ->with(['approver.position', 'approver.user', 'policyStep'])
            ->get()
            ->sortByDesc(fn ($s) => (int) ($s->policyStep?->step_order ?? 0))
            ->first();

        $signer = ($sppd->status === 'approved' ? $finalApprovedStep?->approver : null)
            ?? $sppd->businessTrip?->memo?->creator?->manager 
            ?? $sppd->businessTrip?->memo?->creator
            ?? $sppd->employee?->manager;

        if (! $signer && $sppd->employee?->department_id) {
            $signer = Employee::with('position')
                ->where('department_id', $sppd->employee->department_id)
                ->where(function ($q) {
                    $q->whereHas('position', fn ($pq) => $pq->where('name', 'like', '%Manager%'))
                      ->orWhereHas('user.roles', fn ($rq) => $rq->where('name', 'Manager'));
                })
                ->first();
        }

        $signerName = $signer?->name ?? '____________________';
        $signerPosition = $signer?->position?->name ?? 'User';

        $signatureRender = app(\App\Services\Signature\SignatureService::class)->resolveRenderData(
            $sppd,
            $signer,
            'manager',
            $sppd->status === 'approved'
        );

        $pdf = Pdf::loadView('sppds.print', compact('sppd', 'signer', 'signerName', 'signerPosition', 'signatureRender'));
        $pdf->setPaper('a4', 'portrait');

        $cleanNumber = str_replace(['/', '\\'], '-', $sppd->sppd_number ?? (string) $sppd->id);
        $filename = "SPPD-{$cleanNumber}.pdf";

        return $pdf->stream($filename);
    }
}
