<?php

namespace App\Filament\Admin\Resources\MemoApprovalResource\Pages;

use App\Filament\Admin\Resources\MemoApprovalResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditMemoApproval extends EditRecord
{
    protected static string $resource = MemoApprovalResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make()
                ->label('Hapus')
                ->icon('heroicon-o-trash'),
        ];
    }
}
