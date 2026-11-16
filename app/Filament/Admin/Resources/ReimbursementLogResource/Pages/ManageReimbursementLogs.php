<?php

namespace App\Filament\Admin\Resources\ReimbursementLogResource\Pages;

use App\Filament\Admin\Resources\ReimbursementLogResource;
use Filament\Resources\Pages\ManageRecords;

class ManageReimbursementLogs extends ManageRecords
{
    protected static string $resource = ReimbursementLogResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}

