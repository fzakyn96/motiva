<?php

namespace App\Http\Controllers;

use App\Models\Sppd;
use Barryvdh\DomPDF\Facade\Pdf;

class SppdPrintController extends Controller
{
    public function __invoke(Sppd $sppd)
    {
        $sppd->load([
            'employee.position',
            'employee.department',
            'employee.manager.position',
            'businessTrip.memo.creator.manager.position',
            'businessTrip.memo.creator.position',
        ]);

        // Pejabat yang memberi perintah selalu Manajer
        $signer = $sppd->resolveAuthorizingManager();

        $signerName = $signer?->name ?? '____________________';
        $signerPosition = $signer?->position?->name ?? 'Manajer';

        $sigService = app(\App\Services\Signature\SignatureService::class);
        $signatureRender = $sigService->resolveRenderData(
            $sppd,
            $signer,
            'manager',
            $sppd->status === 'approved'
        );

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

        $pdf = Pdf::loadView('sppds.print', compact('sppd', 'signer', 'signerName', 'signerPosition', 'signatureRender', 'employeeSignatureRender'));
        $pdf->setPaper('a4', 'portrait');

        $cleanNumber = str_replace(['/', '\\'], '-', $sppd->sppd_number ?? (string) $sppd->id);
        $filename = "SPPD-{$cleanNumber}.pdf";

        return $pdf->stream($filename);
    }
}
