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
