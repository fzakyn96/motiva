<?php

namespace App\Filament\Admin\Resources\OvertimeOrderResource\Pages;

use App\Filament\Admin\Resources\OvertimeOrderResource;
use App\Models\OvertimeOrder;
use App\Services\Overtime\OvertimeWorkflowService;
use Filament\Resources\Pages\CreateRecord;

class CreateOvertimeOrder extends CreateRecord
{
    protected static string $resource = OvertimeOrderResource::class;

    protected static ?string $title = 'Buat Surat Perintah Kerja Lembur';

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $user = \Filament\Facades\Filament::auth()->user() ?? auth()->user();
        $data['creator_user_id'] = $user?->id;
        $data['creator_employee_id'] = $user?->employee?->id;
        $data['command_status'] = 'pending';
        $data['report_status'] = 'not_submitted';
        $data['status'] = 'command_pending';

        return $data;
    }

    protected function afterCreate(): void
    {
        /** @var OvertimeOrder $record */
        $record = $this->getRecord();
        app(OvertimeWorkflowService::class)->submitCommand($record);
    }

    protected function getFormActions(): array
    {
        return [
            $this->getCreateFormAction()
                ->label('Kirim Perintah Lembur')
                ->icon('heroicon-o-paper-airplane'),
            $this->getCancelFormAction()
                ->label('Batal')
                ->icon('heroicon-o-arrow-left'),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('view', ['record' => $this->getRecord()]);
    }
}
