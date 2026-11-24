<?php

namespace App\Filament\Admin\Resources\OvertimeApprovalResource\Pages;

use App\Filament\Admin\Resources\OvertimeApprovalResource;
use Filament\Resources\Pages\ListRecords;

class ListOvertimeApprovals extends ListRecords
{
    protected static string $resource = OvertimeApprovalResource::class;

    protected static ?string $title = 'Persetujuan Kerja Lembur (SPKL)';
}
