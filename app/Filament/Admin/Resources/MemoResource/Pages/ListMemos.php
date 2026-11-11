<?php

namespace App\Filament\Admin\Resources\MemoResource\Pages;

use App\Filament\Admin\Resources\MemoResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListMemos extends ListRecords
{
    protected static string $resource = MemoResource::class;

    protected static ?string $title = 'Daftar Memo';

    public function mount(): void
    {
        $user = \Filament\Facades\Filament::auth()->user() ?? auth()->user();
        if ($user && $user->hasRole('Manager') && ! $user->hasRole(['Superadmin', 'Admin', 'HR'])) {
            $this->redirect(\App\Filament\Admin\Resources\MemoApprovalResource::getUrl('index'));
            return;
        }

        parent::mount();
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label('Buat Memo')
                ->icon('heroicon-o-plus'),
        ];
    }
}
