<?php

namespace App\Filament\Admin\Resources\ReimbursementResource\Pages;

use App\Filament\Admin\Resources\ReimbursementResource;
use Filament\Resources\Pages\CreateRecord;

class CreateReimbursement extends CreateRecord
{
    protected static string $resource = ReimbursementResource::class;

    protected static bool $canCreateAnother = false;

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

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $user = \Filament\Facades\Filament::auth()->user() ?? auth()->user();
        if (! $user || ! $user->hasRole(['Superadmin', 'Admin', 'HR']) || empty($data['employee_id'])) {
            $data['employee_id'] = $user?->employee?->id ?? $data['employee_id'] ?? null;
        }

        $data['status'] = 'draft';

        return $data;
    }

    protected function afterCreate(): void
    {
        $this->record->recalculateTotals();
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
