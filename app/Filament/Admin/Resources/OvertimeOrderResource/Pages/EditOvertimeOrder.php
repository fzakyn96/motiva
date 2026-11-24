<?php

namespace App\Filament\Admin\Resources\OvertimeOrderResource\Pages;

use App\Filament\Admin\Resources\OvertimeOrderResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditOvertimeOrder extends EditRecord
{
    protected static string $resource = OvertimeOrderResource::class;

    protected static ?string $title = 'Ubah Perintah Kerja Lembur';

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
}
