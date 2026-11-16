<?php

namespace App\Filament\Admin\Resources\OfficialTravelRecapResource\Pages;

use App\Filament\Admin\Resources\OfficialTravelRecapResource;
use Filament\Resources\Pages\CreateRecord;

class CreateOfficialTravelRecap extends CreateRecord
{
    protected static string $resource = OfficialTravelRecapResource::class;

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

