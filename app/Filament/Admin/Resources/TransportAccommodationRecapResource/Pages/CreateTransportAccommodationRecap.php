<?php

namespace App\Filament\Admin\Resources\TransportAccommodationRecapResource\Pages;

use App\Filament\Admin\Resources\TransportAccommodationRecapResource;
use Filament\Resources\Pages\CreateRecord;

class CreateTransportAccommodationRecap extends CreateRecord
{
    protected static string $resource = TransportAccommodationRecapResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['creator_id'] = auth()->id();

        return $data;
    }

    protected function afterCreate(): void
    {
        $this->record->recalculateTotals();
    }
}

