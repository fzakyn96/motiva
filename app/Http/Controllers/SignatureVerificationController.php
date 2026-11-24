<?php

namespace App\Http\Controllers;

use App\Models\DocumentSignature;
use App\Models\Memo;
use App\Models\OfficialTravelRecap;
use App\Models\OvertimeOrder;
use App\Models\Reimbursement;
use App\Models\Sppd;
use App\Models\SppdCost;
use App\Models\TransportAccommodationRecap;
use Illuminate\Support\Facades\Schema;

class SignatureVerificationController extends Controller
{
    public function __invoke(string $token)
    {
        $signature = null;
        $document = null;
        $documentSummary = null;

        if (Schema::hasTable('document_signatures')) {
            $signature = DocumentSignature::where('token', $token)->first();
        }

        if ($signature) {
            $document = $signature->document;
            if ($document instanceof Memo) {
                $documentSummary = $document->agenda ?: $document->title;
            } elseif ($document instanceof Sppd) {
                $documentSummary = ($document->assignment_purpose ?: 'Perjalanan Dinas') . ' — ' . ($document->destination_location ?: '-');
            } elseif ($document instanceof Reimbursement) {
                $documentSummary = $document->title ?: 'Klaim Penggantian Biaya Dinas';
            } elseif ($document instanceof SppdCost) {
                $documentSummary = 'Perhitungan Biaya SPPD ' . ($document->sppd?->sppd_number ?: '');
            } elseif ($document instanceof TransportAccommodationRecap || $document instanceof OfficialTravelRecap) {
                $documentSummary = ($document->work_name ?: 'Rekapitulasi Biaya')
                    . ($document->period_label ? ' — Periode ' . $document->period_label : '');
            } elseif ($document instanceof OvertimeOrder) {
                $documentSummary = 'Surat Perintah Kerja Lembur — ' . ($document->employee?->name ?: '') . ' (' . ($document->overtime_date?->format('d/m/Y') ?: '-') . ')';
            }
        }

        return view('signatures.verify', [
            'token' => $token,
            'signature' => $signature,
            'document' => $document,
            'documentSummary' => $documentSummary,
        ]);
    }

    public function download(string $token)
    {
        abort_unless(Schema::hasTable('document_signatures'), 404);

        $signature = DocumentSignature::where('token', $token)->firstOrFail();
        $document = $signature->document;
        abort_if(! $document, 404);

        $response = match (true) {
            $document instanceof Memo => app(MemoPrintController::class)($document, true),
            $document instanceof Sppd => app(SppdPrintController::class)($document, true),
            $document instanceof SppdCost => app(SppdCostPrintController::class)($document, true),
            $document instanceof TransportAccommodationRecap => app(RecapPrintController::class)->transport($document),
            $document instanceof OfficialTravelRecap => app(RecapPrintController::class)->travel($document),
            $document instanceof OvertimeOrder => app(OvertimePrintController::class)($document, true),
            default => abort(404),
        };

        if (! request()->boolean('inline') && method_exists($response, 'header')) {
            $disposition = (string) $response->headers->get('Content-Disposition', '');
            if (str_starts_with($disposition, 'inline')) {
                $response->headers->set('Content-Disposition', preg_replace('/^inline/', 'attachment', $disposition));
            }
        }

        return $response;
    }
}

