<?php

namespace App\Filament\Admin\Resources\PositionResource\Pages;

use App\Filament\Admin\Resources\PositionResource;
use App\Models\Position;
use App\Services\MasterData\CsvImportExportService;
use Filament\Actions;
use Filament\Forms\Components\Actions\Action as FormAction;
use Filament\Forms\Components\FileUpload;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ManageRecords;

class ManagePositions extends ManageRecords
{
    protected static string $resource = PositionResource::class;

    protected static ?string $title = 'Master Jabatan';

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('export')
                ->label('Export XLS')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('success')
                ->action(function (CsvImportExportService $service) {
                    $rows = Position::query()
                        ->orderBy('level')
                        ->orderBy('name')
                        ->get()
                        ->map(fn (Position $pos): array => [
                            $pos->code,
                            $pos->name,
                            $pos->level,
                        ]);

                    return $service->streamXlsDownload(
                        'master-jabatan-' . now()->format('Y-m-d') . '.xls',
                        ['kode', 'nama_jabatan', 'level'],
                        $rows,
                        'Master Jabatan'
                    );
                }),
            Actions\Action::make('import')
                ->label('Import XLS')
                ->icon('heroicon-o-arrow-up-tray')
                ->color('info')
                ->modalHeading('Import Master Jabatan (XLS)')
                ->modalDescription('Unggah file Excel (.xls) dengan kolom: kode, nama_jabatan, level.')
                ->form([
                    FileUpload::make('file')
                        ->label('File Excel (.xls)')
                        ->disk('local')
                        ->directory('imports')
                        ->acceptedFileTypes([
                            'application/vnd.ms-excel',
                            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                            'text/xml',
                            'application/xml',
                        ])
                        ->hintAction(
                            FormAction::make('download_template')
                                ->label('Download Template Jabatan (.xls)')
                                ->icon('heroicon-o-document-arrow-down')
                                ->color('success')
                                ->action(fn (CsvImportExportService $service) => $service->streamXlsDownload(
                                    'template-import-jabatan.xls',
                                    ['kode', 'nama_jabatan', 'level'],
                                    [['STF', 'Staff', 4]],
                                    'Master Jabatan'
                                ))
                        )
                        ->required(),
                ])
                ->action(function (array $data, CsvImportExportService $service): void {
                    $stats = $service->importPositions($data['file']);

                    Notification::make()
                        ->title('Import Master Jabatan Selesai')
                        ->body("Ditambahkan: {$stats['created']}, Diperbarui: {$stats['updated']}, Dilewati: {$stats['skipped']}")
                        ->success()
                        ->send();
                }),
            Actions\CreateAction::make()
                ->label('Tambah Jabatan')
                ->icon('heroicon-o-plus'),
        ];
    }
}
