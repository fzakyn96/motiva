<?php

namespace App\Filament\Admin\Resources\SppdRateStandardResource\Pages;

use App\Filament\Admin\Resources\SppdRateStandardResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditSppdRateStandard extends EditRecord
{
    protected static string $resource = SppdRateStandardResource::class;

    protected static ?string $title = 'Ubah Ketentuan Tarif Standar SPPD';

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make()
                ->label('Hapus')
                ->icon('heroicon-o-trash'),
        ];
    }

    protected function getFormActions(): array
    {
        return [
            $this->getSaveFormAction()
                ->label('Simpan Perubahan')
                ->icon('heroicon-o-check'),
            $this->getCancelFormAction()
                ->label('Kembali')
                ->icon('heroicon-o-arrow-left'),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
