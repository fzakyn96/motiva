<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\SppdCost;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;

class SppdCostPrintController extends Controller
{
    public function __invoke(SppdCost $sppdCost)
    {
        $cost = $sppdCost;
        $cost->load([
            'sppd.employee.position',
            'sppd.employee.department',
            'sppd.businessTrip.memo.creator.manager.position',
            'sppd.businessTrip.memo.creator.position',
        ]);

        $sppd = $cost->sppd;

        $user = auth()->user();
        if (! $user) {
            abort(401);
        }
        if (! $user->hasRole(['Superadmin', 'HR']) && (int) $sppd?->employee?->user_id !== (int) $user->id) {
            abort(403, 'Akses formulir biaya SPPD dibatasi khusus bagian HR.');
        }

        // Resolve authorizing official (Pejabat yang memberi perintah)
        $signer = $sppd->businessTrip?->memo?->creator?->manager
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
        $signerPosition = $signer?->position?->name ?? 'Pejabat Berwenang';

        $sigService = app(\App\Services\Signature\SignatureService::class);
        $signerSignatureRender = $sigService->resolveRenderData(
            $sppd,
            $signer,
            'manager',
            $sppd?->status === 'approved'
        );
        $employeeSignatureRender = $sigService->resolveRenderData(
            $sppd,
            $sppd?->employee,
            'requester',
            in_array($sppd?->status, ['pending', 'approved'], true)
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
