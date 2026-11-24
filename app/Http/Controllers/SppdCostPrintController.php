<?php

namespace App\Http\Controllers;

use App\Models\SppdCost;
use Barryvdh\DomPDF\Facade\Pdf;

class SppdCostPrintController extends Controller
{
    public function __invoke(SppdCost $sppdCost, bool $bypassAuth = false)
    {
        $cost = $sppdCost;
        $cost->load([
            'sppd.employee.position',
            'sppd.employee.department',
            'sppd.employee.manager.position',
            'sppd.businessTrip.memo.creator.manager.position',
            'sppd.businessTrip.memo.creator.position',
        ]);

        $sppd = $cost->sppd;

        if (! $bypassAuth) {
            $user = auth()->user();
            if (! $user) {
                abort(401);
            }
            if (! $user->hasRole(['Superadmin', 'Admin', 'HR', 'Manager', 'Division Head']) && (int) $sppd?->employee?->user_id !== (int) $user->id) {
                abort(403, 'Akses formulir biaya SPPD tidak diizinkan.');
            }
            if ($user->hasRole('HR') && ! $user->hasAnyRole(['Superadmin', 'Admin']) && $user->employee?->work_area_id) {
                if ($sppd?->employee?->work_area_id && (int) $sppd->employee->work_area_id !== (int) $user->employee->work_area_id) {
                    abort(403, 'Akses formulir biaya SPPD area kerja lain tidak diizinkan.');
                }
            }
        }

        // Pejabat yang memberi perintah selalu Manajer
        $signer = $sppd?->resolveAuthorizingManager();

        $signerName = $signer?->name ?? '____________________';
        $signerPosition = $signer?->position?->name ?? 'Manajer';

        $sigService = app(\App\Services\Signature\SignatureService::class);
        $hasCostSignature = \App\Models\DocumentSignature::where('document_type', SppdCost::class)
            ->where('document_id', $cost->id)
            ->where('role_type', 'manager')
            ->exists();
        $signerSignatureRender = $sigService->resolveRenderData(
            $cost,
            $signer,
            'manager',
            $hasCostSignature || $cost->status === 'approved'
        );
        $hasEmployeeCostSignature = \App\Models\DocumentSignature::where('document_type', SppdCost::class)
            ->where('document_id', $cost->id)
            ->where('role_type', 'requester')
            ->exists();
        $employeeSignatureRender = $sigService->resolveRenderData(
            $cost,
            $sppd?->employee,
            'requester',
            $hasEmployeeCostSignature
        );

        $pdf = Pdf::loadView('sppd-costs.print', compact(
            'cost',
            'sppd',
            'signer',
            'signerName',
            'signerPosition',
            'signerSignatureRender',
            'employeeSignatureRender'
        ));
        $pdf->setPaper('a4', 'portrait');

        $cleanNumber = str_replace(['/', '\\'], '-', $sppd->sppd_number ?? (string) $cost->id);
        $filename = "Biaya-SPPD-{$cleanNumber}.pdf";

        return $pdf->stream($filename);
    }
}
