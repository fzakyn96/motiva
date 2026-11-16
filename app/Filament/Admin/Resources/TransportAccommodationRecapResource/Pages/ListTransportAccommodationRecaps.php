<?php

namespace App\Filament\Admin\Resources\TransportAccommodationRecapResource\Pages;

use App\Filament\Admin\Resources\TransportAccommodationRecapResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListTransportAccommodationRecaps extends ListRecords
{
    protected static string $resource = TransportAccommodationRecapResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label('Buat Rekap Tiket & Akomodasi')
                ->icon('heroicon-o-plus'),
        ];
    }
}
