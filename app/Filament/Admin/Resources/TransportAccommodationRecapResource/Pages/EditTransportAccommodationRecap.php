<?php

namespace App\Filament\Admin\Resources\TransportAccommodationRecapResource\Pages;

use App\Filament\Admin\Resources\TransportAccommodationRecapResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditTransportAccommodationRecap extends EditRecord
{
    protected static string $resource = TransportAccommodationRecapResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('print')
                ->label('Cetak PDF')
                ->icon('heroicon-o-printer')
                ->color('info')
                ->url(fn () => route('recaps.transport.print', $this->getRecord()))
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

