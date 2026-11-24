<?php

namespace App\Filament\Admin\Resources\OvertimeRecapResource\Pages;

use App\Filament\Admin\Resources\OvertimeRecapResource;
use App\Models\OvertimeRecap;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;

class ViewOvertimeRecap extends ViewRecord
{
    protected static string $resource = OvertimeRecapResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('recalculate')
                ->label('Hitung Ulang dari SPKL')
                ->icon('heroicon-o-arrow-path')
                ->color('warning')
                ->visible(fn (): bool => OvertimeRecapResource::canCreate())
                ->action(function (): void {
                    /** @var OvertimeRecap $record */
                    $record = $this->getRecord();
                    $record->recalculateFromOrders();

                    Notification::make()
                        ->title('Rekap Lembur Diperbarui')
                        ->body('Total lembur Shift dan Non-Shift berhasil dihitung ulang.')
                        ->success()
                        ->send();

                    $this->redirect(OvertimeRecapResource::getUrl('view', ['record' => $record]));
                }),

            Actions\Action::make('print_pdf')
                ->label('Cetak Rekap PDF')
                ->icon('heroicon-o-printer')
                ->color('info')
                ->url(fn (): string => route('overtime-recaps.print', ['recap' => $this->getRecord()]))
                ->openUrlInNewTab(),

            Actions\Action::make('back')
                ->label('Kembali')
                ->icon('heroicon-o-arrow-left')
                ->url(OvertimeRecapResource::getUrl('index')),
        ];
    }
}
