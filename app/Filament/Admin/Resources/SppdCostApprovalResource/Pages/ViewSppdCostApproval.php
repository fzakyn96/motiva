<?php

namespace App\Filament\Admin\Resources\SppdCostApprovalResource\Pages;

use App\Filament\Admin\Resources\SppdCostApprovalResource;
use App\Models\ApprovalRequest;
use App\Services\Approval\ApprovalResolver;
use App\Services\Signature\SignatureService;
use Filament\Actions;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;

class ViewSppdCostApproval extends ViewRecord
{
    protected static string $resource = SppdCostApprovalResource::class;

    protected static ?string $title = 'Review Perhitungan Biaya SPPD';

    protected function canActOnRecord(): bool
    {
        $user = \Filament\Facades\Filament::auth()->user() ?? auth()->user();
        $employee = $user?->employee;
        if (! $employee) {
            return false;
        }

        /** @var ApprovalRequest $record */
        $record = $this->getRecord();

        return $record->status === 'pending' && (new ApprovalResolver())->canApprove($record, $employee);
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('approve')
                ->label('Setujui & Tandatangani')
                ->icon('heroicon-o-check-badge')
                ->color('success')
                ->form([
                    Textarea::make('notes')
                        ->label('Catatan Persetujuan (Opsional)')
                        ->rows(2),
                ])
                ->modalHeading('Konfirmasi Persetujuan Biaya SPPD')
                ->modalDescription('Perhitungan biaya SPPD ini akan disetujui dan dibubuhi tanda tangan digital.')
                ->modalSubmitActionLabel('Ya, Setujui & Tandatangani')
                ->visible(fn (): bool => $this->canActOnRecord())
                ->action(function (array $data): void {
                    $user = \Filament\Facades\Filament::auth()->user() ?? auth()->user();
                    $employee = $user?->employee;
                    if (! $employee || ! app(SignatureService::class)->ensureUserCanSign($user)) {
                        return;
                    }

                    /** @var ApprovalRequest $record */
                    $record = $this->getRecord();
                    (new ApprovalResolver())->approve($record, $employee, $data['notes'] ?? null);

                    Notification::make()
                        ->title('Biaya SPPD Disetujui & Ditandatangani')
                        ->body('Perhitungan biaya SPPD telah disahkan dan siap direkap.')
                        ->success()
                        ->send();

                    $this->redirect(SppdCostApprovalResource::getUrl('index'));
                }),

            Actions\Action::make('reject')
                ->label('Tolak')
                ->icon('heroicon-o-x-circle')
                ->color('danger')
                ->form([
                    Textarea::make('notes')
                        ->label('Alasan Penolakan')
                        ->required()
                        ->rows(2),
                ])
                ->modalHeading('Tolak Perhitungan Biaya SPPD')
                ->modalSubmitActionLabel('Ya, Tolak')
                ->visible(fn (): bool => $this->canActOnRecord())
                ->action(function (array $data): void {
                    $employee = (\Filament\Facades\Filament::auth()->user() ?? auth()->user())?->employee;
                    if (! $employee) {
                        return;
                    }

                    /** @var ApprovalRequest $record */
                    $record = $this->getRecord();
                    (new ApprovalResolver())->reject($record, $employee, $data['notes']);

                    Notification::make()
                        ->title('Biaya SPPD Ditolak')
                        ->danger()
                        ->send();

                    $this->redirect(SppdCostApprovalResource::getUrl('index'));
                }),

            Actions\Action::make('print')
                ->label('Cetak')
                ->icon('heroicon-o-printer')
                ->color('info')
                ->url(fn (): string => route('sppd-costs.print', $this->getRecord()->document_id))
                ->openUrlInNewTab(),

            Actions\Action::make('back')
                ->label('Kembali')
                ->icon('heroicon-o-arrow-left')
                ->color('gray')
                ->url(SppdCostApprovalResource::getUrl('index')),
        ];
    }
}
