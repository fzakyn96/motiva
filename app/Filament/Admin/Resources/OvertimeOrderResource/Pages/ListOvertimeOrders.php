<?php

namespace App\Filament\Admin\Resources\OvertimeOrderResource\Pages;

use App\Filament\Admin\Resources\OvertimeOrderResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListOvertimeOrders extends ListRecords
{
    protected static string $resource = OvertimeOrderResource::class;

    protected static ?string $title = 'Surat Perintah Kerja Lembur (SPKL)';

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label('Buat Perintah Lembur')
                ->icon('heroicon-o-plus')
                ->color('primary'),
        ];
    }
}
