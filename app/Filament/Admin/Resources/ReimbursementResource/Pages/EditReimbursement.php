<?php

namespace App\Filament\Admin\Resources\ReimbursementResource\Pages;

use App\Filament\Admin\Resources\ReimbursementResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditReimbursement extends EditRecord
{
    protected static string $resource = ReimbursementResource::class;

    protected function afterSave(): void
    {
        $this->record->recalculateTotals();
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make()
                ->label('Hapus')
                ->icon('heroicon-o-trash')
                ->visible(fn () => $this->record->status === 'draft' || auth()->user()?->hasRole(['Superadmin', 'Admin'])),
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

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
