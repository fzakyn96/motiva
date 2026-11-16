<?php

namespace App\Filament\Admin\Resources\OfficialTravelRecapResource\Pages;

use App\Filament\Admin\Resources\OfficialTravelRecapResource;
use App\Filament\Admin\Support\RecapTableSupport;
use Filament\Resources\Pages\ViewRecord;

class ViewOfficialTravelRecap extends ViewRecord
{
    protected static string $resource = OfficialTravelRecapResource::class;

    protected static ?string $title = 'Detail & Review Rekap Perjalanan Dinas';

    protected function getHeaderActions(): array
    {
        return RecapTableSupport::viewHeaderActions(
            $this->getRecord(),
            static::getResource()::getUrl('index'),
            'recaps.travel.print'
        );
    }
}

