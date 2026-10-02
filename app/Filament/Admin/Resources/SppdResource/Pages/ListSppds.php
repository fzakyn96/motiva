<?php

namespace App\Filament\Admin\Resources\SppdResource\Pages;

use App\Filament\Admin\Resources\SppdResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListSppds extends ListRecords
{
    protected static string $resource = SppdResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
