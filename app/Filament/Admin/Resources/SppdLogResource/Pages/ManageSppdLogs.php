<?php

namespace App\Filament\Admin\Resources\SppdLogResource\Pages;

use App\Filament\Admin\Resources\SppdLogResource;
use Filament\Resources\Pages\ManageRecords;

class ManageSppdLogs extends ManageRecords
{
    protected static string $resource = SppdLogResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}

