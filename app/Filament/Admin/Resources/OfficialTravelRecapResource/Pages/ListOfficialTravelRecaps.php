<?php

namespace App\Filament\Admin\Resources\OfficialTravelRecapResource\Pages;

use App\Filament\Admin\Resources\OfficialTravelRecapResource;
use App\Models\OfficialTravelRecap;
use Carbon\Carbon;
use Filament\Actions;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;

class ListOfficialTravelRecaps extends ListRecords
{
    protected static string $resource = OfficialTravelRecapResource::class;

    protected function getHeaderActions(): array
    {
        $months = collect(range(1, 12))->mapWithKeys(fn (int $m) => [
            $m => Carbon::create(2026, $m, 1)->locale('id')->translatedFormat('F'),
        ])->toArray();

        $currentYear = (int) now()->year;
        $years = collect(range($currentYear - 1, $currentYear + 1))->mapWithKeys(fn (int $y) => [$y => (string) $y])->toArray();

        return [
            Actions\Action::make('sync_monthly_recap')
                ->label('Sinkronkan Rekap Perjalanan Dinas')
                ->icon('heroicon-o-arrow-path')
                ->color('success')
                ->visible(fn (): bool => OfficialTravelRecapResource::canCreate())
                ->modalHeading('Sinkronkan Rekap Perjalanan Dinas Bulanan')
                ->modalDescription('Mengumpulkan otomatis seluruh Biaya SPPD yang sudah disetujui Manajer pada periode bulan terpilih.')
                ->form([
                    \Filament\Forms\Components\Toggle::make('sync_all_months')
                        ->label('Sinkronkan semua bulan ke belakang yang belum terekap')
                        ->default(false)
                        ->live(),
                    Select::make('month')
                        ->label('Bulan')
                        ->options($months)
                        ->default((int) now()->month)
                        ->visible(fn (\Filament\Forms\Get $get): bool => ! $get('sync_all_months'))
                        ->required(fn (\Filament\Forms\Get $get): bool => ! $get('sync_all_months')),
                    Select::make('year')
                        ->label('Tahun')
                        ->options($years)
                        ->default($currentYear)
                        ->visible(fn (\Filament\Forms\Get $get): bool => ! $get('sync_all_months'))
                        ->required(fn (\Filament\Forms\Get $get): bool => ! $get('sync_all_months')),
                ])
                ->action(function (array $data): void {
                    $user = auth()->user();
                    $hrAreaId = ($user && $user->hasRole('HR') && ! $user->hasAnyRole(['Superadmin', 'Admin']))
                        ? ($user->employee?->work_area_id ? (int) $user->employee->work_area_id : null)
                        : null;

                    if (! empty($data['sync_all_months'])) {
                        $result = OfficialTravelRecap::syncAllMissingMonths(auth()->id(), $hrAreaId);
                        if ($result['months'] === 0) {
                            Notification::make()
                                ->title('Semua Bulan Sudah Tersinkron')
                                ->body('Tidak ada Biaya SPPD yang disetujui Manajer dari bulan sebelumnya yang tertinggal.')
                                ->info()
                                ->send();

                            return;
                        }

                        Notification::make()
                            ->title('Sinkronisasi Multi-Bulan Berhasil')
                            ->body("{$result['months']} periode bulan ({$result['items']} SPPD) berhasil disinkronkan.")
                            ->success()
                            ->send();

                        return;
                    }

                    $recap = OfficialTravelRecap::syncForMonth((int) $data['month'], (int) $data['year'], auth()->id(), $hrAreaId);

                    if (! $recap) {
                        Notification::make()
                            ->title('Belum Ada Biaya SPPD Disetujui')
                            ->body('Tidak ditemukan Biaya SPPD yang sudah disetujui Manajer pada bulan tersebut.')
                            ->warning()
                            ->send();

                        return;
                    }

                    Notification::make()
                        ->title('Rekap Perjalanan Dinas Tersinkron')
                        ->body("{$recap->items()->count()} SPPD berhasil disinkronkan ke Rekap {$recap->period_label}.")
                        ->success()
                        ->send();
                }),
        ];
    }
}
