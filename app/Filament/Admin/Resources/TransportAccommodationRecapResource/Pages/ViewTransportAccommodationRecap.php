<?php

namespace App\Filament\Admin\Resources\TransportAccommodationRecapResource\Pages;

use App\Filament\Admin\Resources\TransportAccommodationRecapResource;
use App\Filament\Admin\Support\RecapTableSupport;
use Filament\Resources\Pages\ViewRecord;

class ViewTransportAccommodationRecap extends ViewRecord
{
    protected static string $resource = TransportAccommodationRecapResource::class;

    protected static ?string $title = 'Detail & Review Rekap Tiket & Akomodasi';

    protected function getHeaderActions(): array
    {
        return RecapTableSupport::viewHeaderActions(
            $this->getRecord(),
            static::getResource()::getUrl('index'),
            'recaps.transport.print'
        );
    }
}

