<?php

namespace App\Filament\Admin\Resources\OvertimeRecapResource\Pages;

use App\Filament\Admin\Resources\OvertimeRecapResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListOvertimeRecaps extends ListRecords
{
    protected static string $resource = OvertimeRecapResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()->label('Buat Rekap Bulanan'),
        ];
    }
}
