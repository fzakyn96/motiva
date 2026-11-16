<?php

namespace App\Filament\Admin\Resources\SppdCostLogResource\Pages;

use App\Filament\Admin\Resources\SppdCostLogResource;
use Filament\Resources\Pages\ManageRecords;

class ManageSppdCostLogs extends ManageRecords
{
    protected static string $resource = SppdCostLogResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}

