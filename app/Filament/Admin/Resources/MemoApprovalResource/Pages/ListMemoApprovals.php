<?php

namespace App\Filament\Admin\Resources\MemoApprovalResource\Pages;

use App\Filament\Admin\Resources\MemoApprovalResource;
use Filament\Resources\Pages\ListRecords;

class ListMemoApprovals extends ListRecords
{
    protected static string $resource = MemoApprovalResource::class;

    protected static ?string $title = 'Daftar Persetujuan Memo';

    protected function getHeaderActions(): array
    {
        return [];
    }
}
