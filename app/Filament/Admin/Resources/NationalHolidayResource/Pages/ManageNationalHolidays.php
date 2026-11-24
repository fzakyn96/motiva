<?php

namespace App\Filament\Admin\Resources\NationalHolidayResource\Pages;

use App\Filament\Admin\Resources\NationalHolidayResource;
use App\Services\Overtime\NationalHolidaySyncService;
use Filament\Actions;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ManageRecords;

class ManageNationalHolidays extends ManageRecords
{
    protected static string $resource = NationalHolidayResource::class;

    public function getSubheading(): ?string
    {
        return 'Hari Sabtu & Minggu sudah otomatis dihitung sebagai Hari Libur (L) untuk sistem 5 hari kerja (Non-Shift). Daftar ini khusus untuk Hari Libur Nasional & Cuti Bersama.';
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('sync_google_calendar')
                ->label('Sinkronkan Kalender Google (Indonesia)')
                ->icon('heroicon-o-arrow-path')
                ->color('info')
                ->form([
                    TextInput::make('year')
                        ->label('Tahun Kalender')
                        ->numeric()
                        ->default((int) now()->year)
                        ->minValue(2020)
                        ->maxValue(2035)
                        ->required(),
                ])
                ->modalHeading('Sinkronkan Hari Libur & Cuti Bersama (Google Calendar)')
                ->modalDescription('Mengambil daftar Hari Libur Nasional dan Cuti Bersama dari kalender resmi Google Indonesia. Keduanya aktif sebagai Hari Libur (L).')
                ->modalSubmitActionLabel('Sinkronkan Sekarang')
                ->action(function (array $data): void {
                    try {
                        $result = app(NationalHolidaySyncService::class)->syncYear((int) $data['year']);

                        Notification::make()
                            ->title('Sinkronisasi Berhasil')
                            ->body("Tahun {$data['year']}: {$result['created']} tanggal baru ditambahkan, {$result['updated']} diperbarui (total {$result['total']} hari libur).")
                            ->success()
                            ->send();
                    } catch (\Throwable $e) {
                        Notification::make()
                            ->title('Sinkronisasi Gagal')
                            ->body($e->getMessage())
                            ->danger()
                            ->send();
                    }
                }),
            Actions\CreateAction::make(),
        ];
    }
}
