<?php

namespace App\Filament\Admin\Resources\EmployeeResource\Pages;

use App\Filament\Admin\Resources\EmployeeResource;
use App\Models\Employee;
use App\Services\MasterData\CsvImportExportService;
use Filament\Actions;
use Filament\Forms\Components\Actions\Action as FormAction;
use Filament\Forms\Components\FileUpload;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ManageRecords;

class ManageEmployees extends ManageRecords
{
    protected static string $resource = EmployeeResource::class;

    protected static ?string $title = 'Data Pegawai';

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('export')
                ->label('Export XLS')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('success')
                ->action(function (CsvImportExportService $service) {
                    $rows = Employee::with(['department', 'position', 'manager', 'user'])
                        ->orderBy('name')
                        ->get()
                        ->map(fn (Employee $emp): array => [
                            $emp->identity_number,
                            $emp->name,
                            $emp->department?->name ?? '',
                            $emp->department?->code ?? '',
                            $emp->position?->name ?? '',
                            $emp->position?->code ?? '',
                            $emp->manager?->identity_number ?? $emp->manager?->name ?? '',
                            $emp->user?->email ?? '',
                            $emp->user?->username ?? '',
                        ]);

                    return $service->streamXlsDownload(
                        'data-pegawai-' . now()->format('Y-m-d') . '.xls',
                        ['nip', 'nama_pegawai', 'unit_kerja', 'kode_unit_kerja', 'jabatan', 'kode_jabatan', 'atasan_langsung', 'email', 'username'],
                        $rows,
                        'Data Pegawai'
                    );
                }),
            Actions\Action::make('import')
                ->label('Import XLS')
                ->icon('heroicon-o-arrow-up-tray')
                ->color('info')
                ->modalHeading('Import Data Pegawai (XLS)')
                ->modalDescription('Unggah file Excel (.xls) dengan kolom: nip, nama_pegawai, unit_kerja/kode_unit_kerja, jabatan/kode_jabatan, atasan_langsung, email, username.')
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
                                ->label('Download Template Import Pegawai (.xls)')
                                ->icon('heroicon-o-document-arrow-down')
                                ->color('success')
                                ->action(function (CsvImportExportService $service) {
                                    return $service->streamXlsDownload(
                                        'template-import-pegawai.xls',
                                        ['nip', 'nama_pegawai', 'unit_kerja', 'kode_unit_kerja', 'jabatan', 'kode_jabatan', 'atasan_langsung', 'email', 'username'],
                                        [
                                            ['PKJ-2026001', 'Budi Santoso', 'MESI', 'MESI', 'Staff', 'STF', 'PKJ-MGR001', 'budi.santoso@perusahaan.co.id', 'budi.santoso'],
                                        ]
                                    );
                                })
                        )
                        ->required(),
                ])
                ->action(function (array $data, CsvImportExportService $service): void {
                    $stats = $service->importEmployees($data['file']);

                    Notification::make()
                        ->title('Import Data Pegawai Selesai')
                        ->body("Ditambahkan: {$stats['created']}, Diperbarui: {$stats['updated']}, Dilewati: {$stats['skipped']}")
                        ->success()
                        ->send();
                }),
            Actions\CreateAction::make()
                ->label('Tambah Pegawai')
                ->icon('heroicon-o-plus'),
        ];
    }
}
