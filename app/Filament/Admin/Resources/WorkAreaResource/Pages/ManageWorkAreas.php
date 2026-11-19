<?php

namespace App\Filament\Admin\Resources\WorkAreaResource\Pages;

use App\Filament\Admin\Resources\WorkAreaResource;
use Filament\Actions;
use Filament\Resources\Pages\ManageRecords;

class ManageWorkAreas extends ManageRecords
{
    protected static string $resource = WorkAreaResource::class;

    protected static ?string $title = 'Master Area Kerja';

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label('Tambah Area Kerja')
                ->icon('heroicon-o-plus'),
        ];
    }
}
