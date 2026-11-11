<?php

namespace App\Filament\Admin\Resources\ReimbursementResource\Pages;

use App\Filament\Admin\Resources\ReimbursementResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListReimbursements extends ListRecords
{
    protected static string $resource = ReimbursementResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label('Buat Pengajuan')
                ->icon('heroicon-o-plus'),
        ];
    }
}
