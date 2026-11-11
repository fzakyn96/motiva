<?php

namespace App\Filament\Admin\Resources\MemoResource\Pages;

use App\Filament\Admin\Resources\MemoResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditMemo extends EditRecord
{
    protected static string $resource = MemoResource::class;

    protected static ?string $title = 'Ubah Memo';

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
                ->label('Simpan')
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
