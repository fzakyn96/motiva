<?php

namespace App\Http\Controllers;

use App\Models\ApprovalRequest;
use App\Models\Employee;
use App\Models\Reimbursement;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;

class ReimbursementPrintController extends Controller
{
    public function __invoke(Reimbursement $reimbursement)
    {
        $reimbursement->load([
            'employee.position',
            'employee.department',
            'sppd.businessTrip',
            'expenses',
        ]);

        // Resolve approving manager
        $approvalRequest = ApprovalRequest::where('document_type', Reimbursement::class)
            ->where('document_id', $reimbursement->id)
            ->first();

        $approvedStep = $approvalRequest?->steps()
            ->where('status', 'approved')
            ->with('approver.position')
            ->latest('updated_at')
            ->first();

        $manager = $approvedStep?->approver;

        if (! $manager && $reimbursement->employee?->manager_id) {
            $manager = $reimbursement->employee->manager;
        }

        if (! $manager && $reimbursement->employee?->department_id) {
            $manager = Employee::with('position')
                ->where('department_id', $reimbursement->employee->department_id)
                ->where(function ($q) {
                    $q->whereHas('position', fn ($pq) => $pq->where('name', 'like', '%Manager%'))
                      ->orWhereHas('user.roles', fn ($rq) => $rq->where('name', 'Manager'));
                })
                ->first();
        }

        $managerName = $manager?->name ?? '____________________';
        $managerPosition = $manager?->position?->name ?? 'Manajer Terkait';

        $sigService = app(\App\Services\Signature\SignatureService::class);
        $requesterSignatureRender = $sigService->resolveRenderData(
            $reimbursement,
            $reimbursement->employee,
            'requester',
            in_array($reimbursement->status, ['pending', 'approved', 'paid'], true)
        );
        $managerSignatureRender = $sigService->resolveRenderData(
            $reimbursement,
            $manager,
            'manager',
            in_array($reimbursement->status, ['approved', 'paid'], true)
        );

        $pdf = Pdf::loadView('reimbursements.print', compact(
            'reimbursement',
            'manager',
            'managerName',
            'managerPosition',
            'requesterSignatureRender',
            'managerSignatureRender'
        ));
        $pdf->setPaper('a4', 'portrait');

        $cleanNumber = str_replace(['/', '\\'], '-', $reimbursement->reimbursement_number ?? (string) $reimbursement->id);
        $filename = "Reimbursement-{$cleanNumber}.pdf";

        return $pdf->stream($filename);
    }
}
