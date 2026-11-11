<?php

namespace App\Filament\Admin\Resources\MemoResource\Pages;

use App\Filament\Admin\Resources\MemoResource;
use Filament\Resources\Pages\CreateRecord;

class CreateMemo extends CreateRecord
{
    protected static string $resource = MemoResource::class;

    protected static bool $canCreateAnother = false;

    protected static ?string $title = 'Buat Memo';

    protected function getFormActions(): array
    {
        return [
            $this->getCreateFormAction()
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
