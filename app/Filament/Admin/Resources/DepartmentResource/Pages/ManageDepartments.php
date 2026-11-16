<?php

namespace App\Filament\Admin\Resources\DepartmentResource\Pages;

use App\Filament\Admin\Resources\DepartmentResource;
use App\Models\Department;
use App\Services\MasterData\CsvImportExportService;
use Filament\Actions;
use Filament\Forms\Components\Actions\Action as FormAction;
use Filament\Forms\Components\FileUpload;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ManageRecords;

class ManageDepartments extends ManageRecords
{
    protected static string $resource = DepartmentResource::class;

    protected static ?string $title = 'Master Unit Kerja';

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('export')
                ->label('Export XLS')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('success')
                ->action(function (CsvImportExportService $service) {
                    $rows = Department::with('organization')
                        ->orderBy('name')
                        ->get()
                        ->map(fn (Department $dept): array => [
                            $dept->code,
                            $dept->name,
                            $dept->organization?->name ?? '',
                        ]);

                    return $service->streamXlsDownload(
                        'master-unit-kerja-' . now()->format('Y-m-d') . '.xls',
                        ['kode', 'nama_unit_kerja', 'organisasi'],
                        $rows,
                        'Master Unit Kerja'
                    );
                }),
            Actions\Action::make('import')
                ->label('Import XLS')
                ->icon('heroicon-o-arrow-up-tray')
                ->color('info')
                ->modalHeading('Import Master Unit Kerja (XLS)')
                ->modalDescription('Unggah file Excel (.xls) dengan kolom: kode, nama_unit_kerja, organisasi (opsional).')
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
                                ->label('Download Template Unit Kerja (.xls)')
                                ->icon('heroicon-o-document-arrow-down')
                                ->color('success')
                                ->action(fn (CsvImportExportService $service) => $service->streamXlsDownload(
                                    'template-import-unit-kerja.xls',
                                    ['kode', 'nama_unit_kerja', 'organisasi'],
                                    [['MESI', 'MESI', 'PT Permata Karya Jasa']],
                                    'Master Unit Kerja'
                                ))
                        )
                        ->required(),
                ])
                ->action(function (array $data, CsvImportExportService $service): void {
                    $stats = $service->importDepartments($data['file']);

                    Notification::make()
                        ->title('Import Unit Kerja Selesai')
                        ->body("Ditambahkan: {$stats['created']}, Diperbarui: {$stats['updated']}, Dilewati: {$stats['skipped']}")
                        ->success()
                        ->send();
                }),
            Actions\CreateAction::make()
                ->label('Tambah Unit Kerja')
                ->icon('heroicon-o-plus'),
        ];
    }
}
