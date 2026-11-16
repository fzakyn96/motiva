<?php

namespace App\Filament\Admin\Resources\MemoLogResource\Pages;

use App\Filament\Admin\Resources\MemoLogResource;
use Filament\Resources\Pages\ManageRecords;

class ManageMemoLogs extends ManageRecords
{
    protected static string $resource = MemoLogResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}

