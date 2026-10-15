<?php

namespace App\Filament\Admin\Resources\PositionResource\Pages;

use App\Filament\Admin\Resources\PositionResource;
use Filament\Actions;
use Filament\Resources\Pages\ManageRecords;

class ManagePositions extends ManageRecords
{
    protected static string $resource = PositionResource::class;

    protected static ?string $title = 'Master Jabatan';

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label('Tambah Jabatan'),
        ];
    }
}
