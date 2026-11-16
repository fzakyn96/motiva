<?php

namespace App\Filament\Admin\Resources\OfficialTravelRecapResource\Pages;

use App\Filament\Admin\Resources\OfficialTravelRecapResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListOfficialTravelRecaps extends ListRecords
{
    protected static string $resource = OfficialTravelRecapResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label('Buat Rekap Perjalanan Dinas')
                ->icon('heroicon-o-plus'),
        ];
    }
}
