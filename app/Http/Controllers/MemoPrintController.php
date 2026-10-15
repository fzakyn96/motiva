<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class MemoPrintController extends Controller
{
    public function __invoke(\App\Models\Memo $memo)
    {
        $memo->load(['creator.position', 'department']);
        
        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('memos.print', compact('memo'));
        
        $filename = 'Memo-' . str_replace('/', '-', $memo->nomor_memo ?? $memo->id) . '.pdf';
        
        return $pdf->stream($filename);
    }
}
