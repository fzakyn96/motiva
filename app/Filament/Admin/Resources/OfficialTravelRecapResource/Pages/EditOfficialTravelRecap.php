<?php

namespace App\Filament\Admin\Resources\OfficialTravelRecapResource\Pages;

use App\Filament\Admin\Resources\OfficialTravelRecapResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditOfficialTravelRecap extends EditRecord
{
    protected static string $resource = OfficialTravelRecapResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('print')
                ->label('Cetak PDF')
                ->icon('heroicon-o-printer')
                ->color('info')
                ->url(fn () => route('recaps.travel.print', $this->getRecord()))
                ->openUrlInNewTab(),
            Actions\DeleteAction::make()
                ->label('Hapus'),
        ];
    }

    protected function afterSave(): void
    {
        $this->record->recalculateTotals();
    }
}

