<?php

namespace App\Filament\Admin\Resources\SppdRateStandardResource\Pages;

use App\Filament\Admin\Resources\SppdRateStandardResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListSppdRateStandards extends ListRecords
{
    protected static string $resource = SppdRateStandardResource::class;

    protected static ?string $title = 'Master Ketentuan Tarif Standar SPPD';

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label('Tambah Tarif Standar')
                ->icon('heroicon-o-plus-circle'),
        ];
    }
}
