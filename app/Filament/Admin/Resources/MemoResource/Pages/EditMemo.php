<?php

namespace App\Filament\Admin\Resources\MemoResource\Pages;

use App\Filament\Admin\Resources\MemoResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditMemo extends EditRecord
{
    protected static string $resource = MemoResource::class;

    protected static ?string $title = 'Ubah Memo';

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make('delete')
                ->label('Hapus')
                ->icon('heroicon-o-trash')
                ->color('danger')
                ->visible(fn () => MemoResource::canDelete($this->getRecord()))
                ->requiresConfirmation()
                ->modalIcon('heroicon-o-exclamation-triangle')
                ->modalHeading(fn (): string => $this->getRecord()->status === 'approved'
                    ? 'Hapus Memo yang Sudah Disetujui?'
                    : 'Hapus Dokumen Memo?')
                ->modalDescription(fn (): string => $this->getRecord()->status === 'approved'
                    ? 'Memo ini telah disetujui (Approved). Menghapus Memo ini akan otomatis menghapus SPPD, Biaya SPPD, dan Reimbursement terkait. Wajib sertakan alasan penghapusan di bawah ini.'
                    : 'Menghapus Memo ini akan otomatis menghapus SPPD, Biaya SPPD, dan Reimbursement terkait (jika ada).')
                ->modalSubmitActionLabel(fn (): string => $this->getRecord()->status === 'approved'
                    ? 'Ya, Hapus'
                    : 'Ya, Hapus')
                ->form(fn (): array => $this->getRecord()->status === 'approved'
                    ? [
                        \Filament\Forms\Components\Textarea::make('deletion_reason')
                            ->label('Alasan Penghapusan Memo')
                            ->placeholder('Wajib diisi: Jelaskan secara rinci alasan penghapusan Memo yang sudah disetujui ini...')
                            ->required()
                            ->minLength(5)
                            ->rows(3),
                    ]
                    : [
                        \Filament\Forms\Components\Textarea::make('deletion_reason')
                            ->label('Alasan Penghapusan Memo (Audit Trail)')
                            ->placeholder('Opsional: Tambahkan catatan/alasan penghapusan untuk Audit Trail...')
                            ->rows(2),
                    ])
                ->action(function (array $data): void {
                    /** @var \App\Models\Memo $record */
                    $record = $this->getRecord();
                    $wasApproved = $record->status === 'approved';
                    $record->deleteWithAudit(auth()->user(), $data['deletion_reason'] ?? null);

                    \Filament\Notifications\Notification::make()
                        ->title($wasApproved ? 'Memo yang sudah disetujui berhasil dihapus ' : 'Memo berhasil dihapus')
                            ->body('Dokumen Memo beserta SPPD, Biaya SPPD, dan Reimbursement terkait telah dihapus.')
                        ->warning()
                        ->send();

                    $this->redirect(MemoResource::getUrl('index'));
                }),
        ];
    }

    protected function getFormActions(): array
    {
        return [
            $this->getSaveFormAction()
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
