<?php

namespace App\Http\Controllers;

use App\Models\DocumentSignature;
use App\Models\OvertimeOrder;
use App\Models\OvertimeSetting;
use App\Services\Signature\SignatureService;
use Barryvdh\DomPDF\Facade\Pdf;

class OvertimePrintController extends Controller
{
    public function __invoke(OvertimeOrder $overtimeOrder, bool $bypassAuth = false)
    {
        $order = $overtimeOrder->load([
            'employee.position',
            'employee.department',
            'employee.workArea',
            'commandApprover.position',
            'reportApprover.position',
        ]);

        if (! $bypassAuth) {
            $user = auth()->user();
            if (! $user) {
                abort(401);
            }

            if (! $user->hasAnyRole(['Superadmin', 'Admin', 'HR', 'Manager', 'Supervisor']) && (int) $order->employee?->user_id !== (int) $user->id) {
                abort(403, 'Akses formulir kerja lembur tidak diizinkan.');
            }

            if (
                $user->hasAnyRole(['HR', 'Manager', 'Supervisor'])
                && ! $user->hasAnyRole(['Superadmin', 'Admin'])
                && $user->employee?->work_area_id
                && $order->employee?->work_area_id
                && (int) $order->employee->work_area_id !== (int) $user->employee->work_area_id
            ) {
                abort(403, 'Akses formulir kerja lembur area kerja lain tidak diizinkan.');
            }
        }

        $setting = OvertimeSetting::current();
        $sigService = app(SignatureService::class);

        $commandManager = $order->resolveCommandingManager();
        $reportManager = $order->reportApprover ?? $commandManager;

        $hasCommandSig = $order->command_status === 'approved'
            || DocumentSignature::where('document_type', OvertimeOrder::class)
                ->where('document_id', $order->id)
                ->where('role_type', 'command_manager')
                ->exists();

        $hasEmployeeSig = DocumentSignature::where('document_type', OvertimeOrder::class)
            ->where('document_id', $order->id)
            ->where('role_type', 'requester')
            ->exists();

        $hasReportSig = $order->report_status === 'approved'
            || DocumentSignature::where('document_type', OvertimeOrder::class)
                ->where('document_id', $order->id)
                ->where('role_type', 'report_manager')
                ->exists();

        $commandSignatureRender = $sigService->resolveRenderData($order, $commandManager, 'command_manager', $hasCommandSig);
        $employeeSignatureRender = $sigService->resolveRenderData($order, $order->employee, 'requester', $hasEmployeeSig);
        $reportSignatureRender = $sigService->resolveRenderData($order, $reportManager, 'report_manager', $hasReportSig);

        $pdf = Pdf::loadView('overtimes.print', compact(
            'order',
            'setting',
            'commandManager',
            'reportManager',
            'commandSignatureRender',
            'employeeSignatureRender',
            'reportSignatureRender',
        ));
        $pdf->setPaper('a4', 'portrait');

        $cleanNumber = str_replace(['/', '\\'], '-', $order->overtime_number ?? (string) $order->id);

        return $pdf->stream("SPKL-{$cleanNumber}.pdf");
    }
}
