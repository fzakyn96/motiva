<?php

namespace App\Filament\Admin\Resources\OvertimeOrderResource\Pages;

use App\Filament\Admin\Resources\OvertimeApprovalResource;
use App\Filament\Admin\Resources\OvertimeOrderResource;
use App\Models\OvertimeOrder;
use App\Services\Overtime\OvertimeWorkflowService;
use App\Services\Signature\SignatureService;
use Filament\Actions;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;

class ViewOvertimeOrder extends ViewRecord
{
    protected static string $resource = OvertimeOrderResource::class;

    protected static ?string $title = 'Detail Surat Perintah Kerja Lembur (SPKL)';

    protected function getHeaderActions(): array
    {
        $backUrl = request()->query('from') === 'approvals'
            ? OvertimeApprovalResource::getUrl('index')
            : OvertimeOrderResource::getUrl('index');

        return [
            // Tahap 1: Persetujuan Bagian A oleh Manajer
            Actions\Action::make('approve_command')
                ->label('Setujui & Tandatangani Perintah (Bagian A)')
                ->icon('heroicon-o-check-badge')
                ->color('success')
                ->form([
                    Textarea::make('notes')
                        ->label('Catatan (Opsional)')
                        ->rows(2),
                ])
                ->modalHeading('Setujui Surat Perintah Kerja Lembur (Bagian A)')
                ->modalDescription('Nomor SPKL akan diterbitkan otomatis dan tanda tangan digital Anda dibubuhkan pada Bagian A.')
                ->modalSubmitActionLabel('Ya, Setujui & Tandatangani')
                ->visible(function (): bool {
                    /** @var OvertimeOrder $record */
                    $record = $this->getRecord();

                    return $record->command_status === 'pending' && OvertimeOrderResource::canUserApproveStage($record);
                })
                ->action(function (array $data): void {
                    $user = \Filament\Facades\Filament::auth()->user() ?? auth()->user();
                    $employee = $user?->employee;
                    if (! $employee || ! app(SignatureService::class)->ensureUserCanSign($user)) {
                        return;
                    }

                    app(OvertimeWorkflowService::class)->approveCommand($this->getRecord(), $employee, $data['notes'] ?? null);

                    Notification::make()
                        ->title('Perintah Kerja Lembur Disetujui')
                        ->body('Nomor SPKL diterbitkan dan pegawai telah diberi notifikasi untuk mengisi Pertanggungjawaban.')
                        ->success()
                        ->send();

                    $this->redirect(OvertimeOrderResource::getUrl('view', ['record' => $this->getRecord()]));
                }),

            Actions\Action::make('reject_command')
                ->label('Tolak Perintah')
                ->icon('heroicon-o-x-circle')
                ->color('danger')
                ->form([
                    Textarea::make('notes')
                        ->label('Alasan Penolakan')
                        ->required()
                        ->rows(2),
                ])
                ->visible(function (): bool {
                    /** @var OvertimeOrder $record */
                    $record = $this->getRecord();

                    return $record->command_status === 'pending' && OvertimeOrderResource::canUserApproveStage($record);
                })
                ->action(function (array $data): void {
                    $employee = (\Filament\Facades\Filament::auth()->user() ?? auth()->user())?->employee;
                    if (! $employee) {
                        return;
                    }

                    app(OvertimeWorkflowService::class)->rejectCommand($this->getRecord(), $employee, $data['notes']);

                    Notification::make()
                        ->title('Perintah Kerja Lembur Ditolak')
                        ->danger()
                        ->send();

                    $this->redirect(OvertimeOrderResource::getUrl('view', ['record' => $this->getRecord()]));
                }),

            // Tahap 2: Pengisian Pertanggungjawaban Bagian B oleh Pegawai yang ditugaskan
            Actions\Action::make('submit_report')
                ->label('Isi & Tandatangani Pertanggungjawaban (Bagian B)')
                ->icon('heroicon-o-pencil-square')
                ->color('warning')
                ->visible(function (): bool {
                    /** @var OvertimeOrder $record */
                    $record = $this->getRecord();
                    $user = \Filament\Facades\Filament::auth()->user() ?? auth()->user();

                    return $user
                        && $record->command_status === 'approved'
                        && in_array($record->report_status, ['not_submitted', 'rejected'], true)
                        && (int) $record->employee_id === (int) $user->employee?->id;
                })
                ->form([
                    TextInput::make('start_time')
                        ->label('Jam Mulai')
                        ->placeholder('19:00')
                        ->default(fn () => $this->getRecord()->start_time ?: '19:00')
                        ->required()
                        ->maxLength(10),
                    TextInput::make('end_time')
                        ->label('Jam Selesai')
                        ->placeholder('23:00')
                        ->default(fn () => $this->getRecord()->end_time ?: '23:00')
                        ->required()
                        ->maxLength(10),
                    DatePicker::make('report_date')
                        ->label('Tanggal Pertanggungjawaban')
                        ->default(now())
                        ->native(false)
                        ->displayFormat('d F Y')
                        ->required(),
                    Textarea::make('actual_result')
                        ->label('Hasil Kerja Lembur')
                        ->default(fn () => $this->getRecord()->actual_result ?: $this->getRecord()->work_description)
                        ->rows(3)
                        ->required(),
                ])
                ->modalHeading('Pertanggungjawaban Pelaksanaan Kerja Lembur (Bagian B)')
                ->modalSubmitActionLabel('Kirim & Tandatangani')
                ->action(function (array $data): void {
                    $user = \Filament\Facades\Filament::auth()->user() ?? auth()->user();
                    $employee = $user?->employee;
                    if (! $employee || ! app(SignatureService::class)->ensureUserCanSign($user)) {
                        return;
                    }

                    app(OvertimeWorkflowService::class)->submitReport($this->getRecord(), $employee, [
                        'start_time' => $data['start_time'],
                        'end_time' => $data['end_time'],
                        'report_date' => $data['report_date'],
                        'actual_result' => $data['actual_result'],
                    ]);

                    Notification::make()
                        ->title('Pertanggungjawaban Lembur Dikirim')
                        ->body('Tanda tangan Anda telah tercatat dan pengajuan diteruskan ke Manajer untuk pengesahan.')
                        ->success()
                        ->send();

                    $this->redirect(OvertimeOrderResource::getUrl('view', ['record' => $this->getRecord()]));
                }),

            // Tahap 2: Pengesahan Bagian B oleh Manajer
            Actions\Action::make('approve_report')
                ->label('Sahkan & Tandatangani Pertanggungjawaban (Bagian B)')
                ->icon('heroicon-o-check-badge')
                ->color('success')
                ->form([
                    Textarea::make('notes')
                        ->label('Catatan Pengesahan (Opsional)')
                        ->rows(2),
                ])
                ->modalHeading('Pengesahan Pertanggungjawaban Kerja Lembur (Bagian B)')
                ->modalSubmitActionLabel('Ya, Sahkan & Tandatangani')
                ->visible(function (): bool {
                    /** @var OvertimeOrder $record */
                    $record = $this->getRecord();

                    return $record->report_status === 'pending' && OvertimeOrderResource::canUserApproveStage($record);
                })
                ->action(function (array $data): void {
                    $user = \Filament\Facades\Filament::auth()->user() ?? auth()->user();
                    $employee = $user?->employee;
                    if (! $employee || ! app(SignatureService::class)->ensureUserCanSign($user)) {
                        return;
                    }

                    app(OvertimeWorkflowService::class)->approveReport($this->getRecord(), $employee, $data['notes'] ?? null);

                    Notification::make()
                        ->title('Pertanggungjawaban Lembur Disahkan')
                        ->body('Dokumen Kerja Lembur telah selesai dan disahkan sepenuhnya.')
                        ->success()
                        ->send();

                    $this->redirect(OvertimeOrderResource::getUrl('view', ['record' => $this->getRecord()]));
                }),

            Actions\Action::make('reject_report')
                ->label('Tolak Pertanggungjawaban')
                ->icon('heroicon-o-x-circle')
                ->color('danger')
                ->form([
                    Textarea::make('notes')
                        ->label('Alasan Penolakan')
                        ->required()
                        ->rows(2),
                ])
                ->visible(function (): bool {
                    /** @var OvertimeOrder $record */
                    $record = $this->getRecord();

                    return $record->report_status === 'pending' && OvertimeOrderResource::canUserApproveStage($record);
                })
                ->action(function (array $data): void {
                    $employee = (\Filament\Facades\Filament::auth()->user() ?? auth()->user())?->employee;
                    if (! $employee) {
                        return;
                    }

                    app(OvertimeWorkflowService::class)->rejectReport($this->getRecord(), $employee, $data['notes']);

                    Notification::make()
                        ->title('Pertanggungjawaban Lembur Ditolak')
                        ->warning()
                        ->send();

                    $this->redirect(OvertimeOrderResource::getUrl('view', ['record' => $this->getRecord()]));
                }),

            Actions\Action::make('print')
                ->label('Cetak')
                ->icon('heroicon-o-printer')
                ->color('info')
                ->url(fn (): string => route('overtimes.print', $this->getRecord()))
                ->openUrlInNewTab(),

            Actions\Action::make('back')
                ->label('Kembali')
                ->icon('heroicon-o-arrow-left')
                ->color('gray')
                ->url($backUrl),
        ];
    }
}
