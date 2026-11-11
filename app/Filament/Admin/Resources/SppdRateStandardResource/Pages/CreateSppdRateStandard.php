<?php

namespace App\Filament\Admin\Resources\SppdRateStandardResource\Pages;

use App\Filament\Admin\Resources\SppdRateStandardResource;
use Filament\Resources\Pages\CreateRecord;

class CreateSppdRateStandard extends CreateRecord
{
    protected static string $resource = SppdRateStandardResource::class;

    protected static ?string $title = 'Tambah Ketentuan Tarif Standar SPPD';

    protected static bool $canCreateAnother = false;

    protected function getFormActions(): array
    {
        return [
            $this->getCreateFormAction()
                ->label('Simpan Tarif')
                ->icon('heroicon-o-check'),
            $this->getCancelFormAction()
                ->label('Batal')
                ->icon('heroicon-o-arrow-left'),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
