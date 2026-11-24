<?php

namespace App\Filament\Admin\Resources\SppdCostResource\Pages;

use App\Filament\Admin\Resources\SppdCostResource;
use App\Filament\Admin\Resources\SppdResource;
use App\Models\Sppd;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewSppdCost extends ViewRecord
{
    protected static string $resource = SppdCostResource::class;

    protected static ?string $title = 'Rincian Biaya SPPD';

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('employee_sign_cost')
                ->label('Setujui & Tandatangani')
                ->icon('heroicon-o-check-badge')
                ->color('success')
                ->visible(fn (): bool => SppdResource::canEmployeeSignCost($this->getRecord()))
                ->requiresConfirmation()
                ->modalIcon('heroicon-o-clipboard-document-check')
                ->modalHeading('Konfirmasi Persetujuan & Tanda Tangan Biaya SPPD')
                ->modalDescription(function (): string {
                    $user = \Filament\Facades\Filament::auth()->user() ?? auth()->user();
                    if (! $user?->hasConfiguredSignature()) {
                        return 'PERHATIAN: Anda belum mengatur tanda tangan digital. Silakan atur tanda tangan terlebih dahulu di menu Pengaturan Tanda Tangan.';
                    }

                    /** @var Sppd $sppd */
                    $sppd = $this->getRecord();
                    $total = 'Rp ' . number_format((float) ($sppd->cost?->grand_total ?: $sppd->cost?->total_cost ?: 0), 0, ',', '.');

                    return "Total rincian biaya SPPD Anda sebesar {$total}. Dengan menekan tombol di bawah, Anda menyetujui rincian biaya dan membubuhkan tanda tangan digital Anda.";
                })
                ->modalSubmitActionLabel('Ya, Setujui & Tandatangani')
                ->modalCancelActionLabel('Batal')
                ->action(function (): void {
                    if (SppdResource::performEmployeeCostSignature($this->getRecord())) {
                        $this->redirect(SppdCostResource::getUrl('view', ['record' => $this->getRecord()]));
                    }
                }),

            Actions\Action::make('print')
                ->label('Cetak')
                ->icon('heroicon-o-printer')
                ->color('info')
                ->visible(fn (): bool => $this->getRecord()?->cost !== null)
                ->url(fn (): string => route('sppd-costs.print', $this->getRecord()->cost))
                ->openUrlInNewTab(),

            Actions\Action::make('back')
                ->label('Kembali')
                ->icon('heroicon-o-arrow-left')
                ->color('gray')
                ->url(SppdCostResource::getUrl('index')),
        ];
    }
}
