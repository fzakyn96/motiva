<?php

namespace App\Http\Controllers;

use App\Models\Sppd;
use Barryvdh\DomPDF\Facade\Pdf;

class SppdPrintController extends Controller
{
    public function __invoke(Sppd $sppd, bool $bypassAuth = false)
    {
        $sppd->load([
            'employee.position',
            'employee.department',
            'employee.manager.position',
            'businessTrip.memo.creator.manager.position',
            'businessTrip.memo.creator.position',
        ]);

        if (! $bypassAuth) {
            $user = auth()->user();
            if ($user && $user->hasRole('HR') && ! $user->hasAnyRole(['Superadmin', 'Admin']) && $user->employee?->work_area_id) {
                if ($sppd->employee?->work_area_id && (int) $sppd->employee->work_area_id !== (int) $user->employee->work_area_id) {
                    abort(403, 'Akses form SPPD area kerja lain tidak diizinkan.');
                }
            }
        }

        // Pejabat yang memberi perintah selalu Manajer
        $signer = $sppd->resolveAuthorizingManager();

        $signerName = $signer?->name ?? '____________________';
        $signerPosition = $signer?->position?->name ?? 'Manajer';

        // Supervisor (Mengetahui) jika di area kerja pegawai tersebut terdapat SPV
        $supervisor = $sppd->resolveAreaSupervisor();
        $supervisorName = $supervisor?->name ?? null;
        $supervisorPosition = $supervisor?->position?->name ?? 'Supervisor';

        $sigService = app(\App\Services\Signature\SignatureService::class);
        $signatureRender = $sigService->resolveRenderData(
            $sppd,
            $signer,
            'manager',
            $sppd->status === 'approved'
        );

        $hasSupervisorSigned = false;
        if ($supervisor) {
            $hasSupervisorSigned = $sppd->status === 'approved'
                || \App\Models\DocumentSignature::where('document_type', Sppd::class)
                    ->where('document_id', $sppd->id)
                    ->where('role_type', 'supervisor')
                    ->exists()
                || (bool) $sppd->approvalRequest?->steps()
                    ->where('status', 'approved')
                    ->whereHas('policyStep', fn ($q) => $q->where('resolver_class', \App\Services\Approval\Resolvers\SupervisorAuthorityResolver::class))
                    ->exists();
        }

        $supervisorSignatureRender = $supervisor
            ? $sigService->resolveRenderData($sppd, $supervisor, 'supervisor', $hasSupervisorSigned)
            : null;

        $hasEmployeeSigned = \App\Models\DocumentSignature::where('document_type', Sppd::class)
            ->where('document_id', $sppd->id)
            ->where('role_type', 'requester')
            ->exists();

        $employeeSignatureRender = $sigService->resolveRenderData(
            $sppd,
            $sppd->employee,
            'requester',
            $hasEmployeeSigned
        );

        $pdf = Pdf::loadView('sppds.print', compact(
            'sppd',
            'signer',
            'signerName',
            'signerPosition',
            'signatureRender',
            'supervisor',
            'supervisorName',
            'supervisorPosition',
            'supervisorSignatureRender',
            'employeeSignatureRender'
        ));
        $pdf->setPaper('a4', 'portrait');

        $cleanNumber = str_replace(['/', '\\'], '-', $sppd->sppd_number ?? (string) $sppd->id);
        $filename = "SPPD-{$cleanNumber}.pdf";

        return $pdf->stream($filename);
    }
}
