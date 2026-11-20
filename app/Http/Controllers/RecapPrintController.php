<?php

namespace App\Http\Controllers;

use App\Models\OfficialTravelRecap;
use App\Models\TransportAccommodationRecap;
use App\Services\Approval\Resolvers\BusinessDivisionHeadAuthorityResolver;
use App\Services\Approval\Resolvers\DivisionHeadAuthorityResolver;
use App\Services\Approval\Resolvers\ManagerAuthorityResolver;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class RecapPrintController extends Controller
{
    public function transport(TransportAccommodationRecap $recap): Response
    {
        $recap->load(['items', 'department', 'businessTrip.memo.department', 'businessTrip.sppds.employee.department']);
        $recap->recalculateTotals();

        $signatories = $recap->resolveSignatories();

        $approvalFlags = $this->resolveApprovalFlags($recap);

        $rows = $recap->items->map(fn ($item) => [
            'description' => $item->description,
            'amount' => (float) $item->amount,
            'service_fee' => (float) $item->service_fee,
            'total_amount' => (float) $item->total_amount,
        ])->toArray();

        $pdf = Pdf::loadView('recaps.transport-print', [
            'trip' => $recap,
            'summary' => [
                'rows' => $rows,
                'subtotal_amount' => (float) $recap->subtotal_amount,
                'total_service_fee' => (float) $recap->total_service_fee,
                'total_cost' => (float) $recap->total_cost,
                'manfee_percent' => (float) $recap->manfee_percent,
                'manfee_amount' => (float) $recap->manfee_amount,
                'grand_total' => (float) $recap->grand_total,
            ],
            'signatories' => $signatories,
            'step0Approved' => $approvalFlags['step0Approved'],
            'step1Approved' => $approvalFlags['step1Approved'],
            'step2Approved' => $approvalFlags['step2Approved'],
            'approvedManagerIds' => $approvalFlags['approvedManagerIds'],
            'periodMonth' => $recap->period_month,
            'periodLabel' => $recap->period_label,
            'workName' => $recap->work_name,
        ])->setPaper('a4', 'portrait');

        return $pdf->stream("Rekap-Transportasi-Akomodasi-{$recap->id}.pdf");
    }

    public function travel(OfficialTravelRecap $recap): Response
    {
        $recap->load(['items', 'department', 'businessTrip.memo.department', 'businessTrip.sppds.employee.department']);
        $recap->recalculateTotals();

        $signatories = $recap->resolveSignatories();

        $approvalFlags = $this->resolveApprovalFlags($recap);

        $rows = $recap->items->map(fn ($item) => [
            'description' => $item->description,
            'amount' => (float) $item->amount,
        ])->toArray();

        $pdf = Pdf::loadView('recaps.travel-print', [
            'trip' => $recap,
            'summary' => [
                'rows' => $rows,
                'total_work_cost' => (float) $recap->total_work_cost,
                'manfee_percent' => (float) $recap->manfee_percent,
                'manfee_amount' => (float) $recap->manfee_amount,
                'grand_total' => (float) $recap->grand_total,
            ],
            'signatories' => $signatories,
            'step0Approved' => $approvalFlags['step0Approved'],
            'step1Approved' => $approvalFlags['step1Approved'],
            'step2Approved' => $approvalFlags['step2Approved'],
            'approvedManagerIds' => $approvalFlags['approvedManagerIds'],
            'periodMonth' => $recap->period_month,
            'periodLabel' => $recap->period_label,
            'workName' => $recap->work_name,
        ])->setPaper('a4', 'portrait');

        return $pdf->stream("Rekap-Perjalanan-Dinas-{$recap->id}.pdf");
    }

    /**
     * @return array{step0Approved: bool, step1Approved: bool, step2Approved: bool, approvedManagerIds: list<int>}
     */
    protected function resolveApprovalFlags(Model $document): array
    {
        $req = $document->approvalRequests()->with('steps.policyStep')->latest('id')->first();
        if (! $req) {
            return ['step0Approved' => false, 'step1Approved' => false, 'step2Approved' => false, 'approvedManagerIds' => []];
        }

        $hasBdhStep = $req->steps->contains(fn ($s) => $s->policyStep?->resolver_class === BusinessDivisionHeadAuthorityResolver::class);
        $step0Approved = $hasBdhStep
            ? $req->steps->contains(fn ($s) => $s->status === 'approved' && $s->policyStep?->resolver_class === BusinessDivisionHeadAuthorityResolver::class)
            : true;

        $managerSteps = $req->steps->filter(fn ($s) => $s->policyStep?->resolver_class === ManagerAuthorityResolver::class);
        $step1Approved = $managerSteps->isNotEmpty() && $managerSteps->every(fn ($s) => $s->status === 'approved');
        $approvedManagerIds = $managerSteps
            ->where('status', 'approved')
            ->pluck('approver_employee_id')
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();

        $step2Approved = $req->steps->contains(fn ($s) => $s->status === 'approved' && $s->policyStep?->resolver_class === DivisionHeadAuthorityResolver::class);

        return [
            'step0Approved' => $step0Approved,
            'step1Approved' => $step1Approved,
            'step2Approved' => $step2Approved,
            'approvedManagerIds' => $approvedManagerIds,
        ];
    }

    public function attachment(string $path): StreamedResponse
    {
        abort_if(str_contains($path, '..'), 403);
        abort_unless(Storage::disk('public')->exists($path), 404);

        return Storage::disk('public')->response($path);
    }
}
