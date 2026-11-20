<?php

namespace App\Http\Controllers;

use App\Models\ApprovalRequest;
use App\Models\Employee;
use App\Models\Memo;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;

class MemoPrintController extends Controller
{
    public function __invoke(Memo $memo, bool $bypassAuth = false)
    {
        if (! $bypassAuth) {
            $user = auth()->user();
            abort_if(! $user || ($user->hasRole('Staff') && ! $user->hasAnyRole(['Superadmin', 'Admin', 'HR', 'Manager'])), 403);
            if ($user->hasRole('HR') && ! $user->hasAnyRole(['Superadmin', 'Admin']) && $user->employee?->work_area_id) {
                $memo->loadMissing('creator');
                if ($memo->creator?->work_area_id && (int) $memo->creator->work_area_id !== (int) $user->employee->work_area_id) {
                    abort(403, 'Akses memo area kerja lain tidak diizinkan.');
                }
            }
        }

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

