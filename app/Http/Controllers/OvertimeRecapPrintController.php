<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\OvertimeRecap;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;

class OvertimeRecapPrintController extends Controller
{
    public function show(OvertimeRecap $recap): Response
    {
        $recap->loadMissing(['items.employee.position', 'department']);

        $divisionHead = Employee::query()
            ->with('position')
            ->whereHas('position', fn ($q) => $q->whereIn('code', ['BDH', 'BDH-PKJ'])
                ->orWhere('name', 'like', '%Business Division Head%'))
            ->first();

        $months = [
            1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
            5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
            9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember',
        ];
        $periodLabel = ($months[$recap->period_month] ?? $recap->period_month) . ' ' . $recap->period_year;

        $pdf = Pdf::loadView('overtimes.recap-print', [
            'recap' => $recap,
            'periodLabel' => $periodLabel,
            'divisionHead' => $divisionHead,
        ])->setPaper('a4', 'landscape');

        $safeFileName = str_replace(['/', '\\'], '-', (string) ($recap->recap_number ?: 'rekap-lembur-' . $recap->id));

        return $pdf->stream("{$safeFileName}.pdf");
    }
}
