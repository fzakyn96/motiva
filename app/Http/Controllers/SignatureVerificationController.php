<?php

namespace App\Http\Controllers;

use App\Models\DocumentSignature;
use App\Models\Memo;
use App\Models\Reimbursement;
use App\Models\Sppd;
use App\Models\SppdCost;
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
            }
        }

        return view('signatures.verify', [
            'token' => $token,
            'signature' => $signature,
            'document' => $document,
            'documentSummary' => $documentSummary,
        ]);
    }
}

