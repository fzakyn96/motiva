<?php

namespace App\Filament\Admin\Resources\OvertimeRecapResource\Pages;

use App\Filament\Admin\Resources\OvertimeRecapResource;
use App\Models\OvertimeRecap;
use Filament\Resources\Pages\CreateRecord;

class CreateOvertimeRecap extends CreateRecord
{
    protected static string $resource = OvertimeRecapResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $user = \Filament\Facades\Filament::auth()->user() ?? auth()->user();
        $data['creator_id'] = $user?->id;
        $data['recap_number'] = OvertimeRecap::generateRecapNumber(
            (int) $data['period_month'],
            (int) $data['period_year']
        );
        $data['status'] = 'draft';

        return $data;
    }

    protected function afterCreate(): void
    {
        /** @var OvertimeRecap $record */
        $record = $this->getRecord();
        $record->recalculateFromOrders();
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('view', ['record' => $this->getRecord()]);
    }
}
