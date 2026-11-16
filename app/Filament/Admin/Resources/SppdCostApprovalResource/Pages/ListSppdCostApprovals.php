<?php

namespace App\Filament\Admin\Resources\SppdCostApprovalResource\Pages;

use App\Filament\Admin\Resources\SppdCostApprovalResource;
use Filament\Resources\Pages\ListRecords;

class ListSppdCostApprovals extends ListRecords
{
    protected static string $resource = SppdCostApprovalResource::class;

    protected static ?string $title = 'Persetujuan Biaya SPPD';
}
