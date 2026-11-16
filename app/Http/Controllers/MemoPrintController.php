<?php

namespace App\Http\Controllers;

use App\Models\ApprovalRequest;
use App\Models\Employee;
use App\Models\Memo;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;

class MemoPrintController extends Controller
{
    public function __invoke(Memo $memo)
    {
        $user = auth()->user();
        abort_if(! $user || ($user->hasRole('Staff') && ! $user->hasAnyRole(['Superadmin', 'Admin', 'HR', 'Manager'])), 403);

        $memo->load(['creator.position', 'creator.manager.position', 'department']);

        // Resolve the Manager who approved or oversees this memo
        $approvalRequest = ApprovalRequest::where('document_type', Memo::class)
            ->where('document_id', $memo->id)
            ->first();

        $approvedStep = $approvalRequest?->steps()
            ->where('status', 'approved')
            ->with('approver.position')
            ->latest('updated_at')
            ->first();

        $manager = $approvedStep?->approver;

        if (! $manager) {
            $manager = \App\Services\Approval\Resolvers\ManagerAuthorityResolver::resolveManagerForEmployee(
                $memo->creator,
                $memo->department_id
            );
        }

        if (! $manager) {
            $manager = Employee::with('position')
                ->whereHas('user.roles', fn ($rq) => $rq->where('name', 'Manager'))
                ->first();
        }

        $managerTitle = $manager?->position?->name ?? 'Manajer';

        $signatureRender = app(\App\Services\Signature\SignatureService::class)->resolveRenderData(
            $memo,
            $manager,
            'manager',
            $memo->status === 'approved'
        );

        $pdf = Pdf::loadView('memos.print', compact('memo', 'manager', 'managerTitle', 'signatureRender'));

        $filename = 'Memo-' . str_replace('/', '-', $memo->nomor_memo ?? $memo->id) . '.pdf';

        return $pdf->stream($filename);
    }
}

