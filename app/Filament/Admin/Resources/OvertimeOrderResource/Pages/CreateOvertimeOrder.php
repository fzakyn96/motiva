<?php

namespace App\Filament\Admin\Resources\OvertimeOrderResource\Pages;

use App\Filament\Admin\Resources\OvertimeOrderResource;
use App\Models\Employee;
use App\Models\OvertimeOrder;
use App\Services\Overtime\OvertimeCalculatorService;
use App\Services\Overtime\OvertimeComplianceService;
use App\Services\Overtime\OvertimeWorkflowService;
use Filament\Notifications\Notification;
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

        if (! empty($data['employee_id']) && ! empty($data['overtime_date']) && (float) ($data['planned_hours'] ?? 0) > 0) {
            $emp = Employee::find($data['employee_id']);
            if ($emp) {
                $workMode = $data['work_mode'] ?? ($emp->overtime_work_mode ?: 'non_shift');
                $dayType = app(OvertimeCalculatorService::class)->resolveDayType(
                    $data['overtime_date'],
                    $workMode,
                    (bool) ($data['is_shift_day_off'] ?? false)
                );
                $compliance = app(OvertimeComplianceService::class)->evaluate(
                    $emp,
                    $data['overtime_date'],
                    (float) $data['planned_hours'],
                    $dayType
                );

                if ($compliance['status'] === 'blocked') {
                    Notification::make()
                        ->title('Pengajuan Diblokir Aturan Ketenagakerjaan')
                        ->body(implode(' ', $compliance['warnings']))
                        ->danger()
                        ->persistent()
                        ->send();
                    $this->halt();
                }

                if ($compliance['status'] === 'warning' && trim((string) ($data['compliance_override_reason'] ?? '')) === '') {
                    Notification::make()
                        ->title('Wajib Isi Catatan Khusus')
                        ->body(implode(' ', $compliance['warnings']) . ' Silakan isi Catatan Khusus sebelum mengirim.')
                        ->warning()
                        ->persistent()
                        ->send();
                    $this->halt();
                }
            }
        }

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
