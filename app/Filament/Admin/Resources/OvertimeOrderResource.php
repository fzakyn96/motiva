<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\OvertimeOrderResource\Pages;
use App\Models\Employee;
use App\Models\OvertimeOrder;
use App\Models\OvertimeSetting;
use App\Services\Approval\ApprovalResolver;
use App\Services\Overtime\OvertimeWorkflowService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class OvertimeOrderResource extends Resource
{
    protected static ?string $model = OvertimeOrder::class;

    protected static ?string $navigationIcon = 'heroicon-o-clock';

    protected static ?string $navigationGroup = 'Dokumen';

    protected static ?string $navigationLabel = 'Kerja Lembur (SPKL)';

    protected static ?string $slug = 'overtimes';

    protected static ?string $modelLabel = 'Surat Perintah Kerja Lembur';

    protected static ?string $pluralModelLabel = 'Surat Perintah Kerja Lembur';

    protected static ?int $navigationSort = 3;

    protected static function currentUser(): ?\App\Models\User
    {
        return (\Filament\Facades\Filament::getCurrentPanel() ? \Filament\Facades\Filament::auth()->user() : null) ?? auth()->user();
    }

    public static function canViewAny(): bool
    {
        return static::currentUser() !== null;
    }

    public static function canCreate(): bool
    {
        $user = static::currentUser();
        if (! $user) {
            return false;
        }

        return OvertimeSetting::current()->canUserCreate($user);
    }

    public static function canEdit(Model $record): bool
    {
        $user = static::currentUser();
        if (! $user || ! $record instanceof OvertimeOrder) {
            return false;
        }

        if ($record->command_status === 'approved') {
            return false;
        }

        if ($user->hasAnyRole(['Superadmin', 'Admin'])) {
            return true;
        }

        return static::canCreate() && (int) $record->creator_user_id === (int) $user->id;
    }

    public static function canDelete(Model $record): bool
    {
        $user = static::currentUser();
        if (! $user || ! $record instanceof OvertimeOrder) {
            return false;
        }

        if ($user->hasAnyRole(['Superadmin', 'Admin'])) {
            return true;
        }

        return $record->command_status !== 'approved' && (int) $record->creator_user_id === (int) $user->id;
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()
            ->with(['employee.position', 'employee.department', 'employee.workArea', 'creator', 'commandApprover', 'reportApprover'])
            ->latest('id');

        $user = static::currentUser();
        if (! $user || $user->hasAnyRole(['Superadmin', 'Admin'])) {
            return $query;
        }

        $employee = $user->employee;
        if (! $employee) {
            return $query->whereRaw('1 = 0');
        }

        if ($user->hasAnyRole(['HR', 'Manager', 'Supervisor'])) {
            if ($employee->work_area_id) {
                return $query->whereHas('employee', fn (Builder $q) => $q->where('work_area_id', $employee->work_area_id));
            }

            return $query;
        }

        return $query->where('employee_id', $employee->id);
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('A. Surat Perintah Kerja Lembur (SPKL)')
                    ->icon('heroicon-o-document-text')
                    ->iconColor('primary')
                    ->schema([
                        Forms\Components\Select::make('employee_id')
                            ->label('Pegawai yang Ditugaskan')
                            ->options(function (): array {
                                $user = \Filament\Facades\Filament::auth()->user() ?? auth()->user();
                                $query = Employee::query()->with(['position', 'workArea'])->orderBy('name');

                                if ($user && ! $user->hasAnyRole(['Superadmin', 'Admin']) && $user->employee?->work_area_id) {
                                    $query->where('work_area_id', $user->employee->work_area_id);
                                }

                                return $query->get()->mapWithKeys(function (Employee $emp): array {
                                    $pos = $emp->position?->name;
                                    $area = $emp->workArea?->code;
                                    $label = $emp->name . ($pos ? " — {$pos}" : '') . ($area ? " ({$area})" : '');

                                    return [$emp->id => $label];
                                })->all();
                            })
                            ->searchable()
                            ->preload()
                            ->required()
                            ->live()
                            ->afterStateUpdated(function ($state, Forms\Set $set): void {
                                if (! $state) {
                                    return;
                                }
                                $emp = Employee::with(['department', 'workArea'])->find($state);
                                if ($emp?->department?->name) {
                                    $set('satuan_kerja', $emp->department->name);
                                }
                                if ($emp?->workArea?->name) {
                                    $set('location_city', $emp->workArea->name);
                                }
                                if ($emp?->overtime_work_mode) {
                                    $set('work_mode', $emp->overtime_work_mode);
                                }
                            }),
                        Forms\Components\TextInput::make('satuan_kerja')
                            ->label('Satuan Kerja')
                            ->placeholder('Contoh: Operation and Maintenance Management Region III')
                            ->required()
                            ->maxLength(255),
                        Forms\Components\TextInput::make('location_city')
                            ->label('Kota / Lokasi Surat')
                            ->default(fn () => (auth()->user()?->employee?->workArea?->name) ?: 'Jakarta')
                            ->required()
                            ->maxLength(100),
                        Forms\Components\DatePicker::make('command_date')
                            ->label('Tanggal Surat Perintah')
                            ->default(now())
                            ->native(false)
                            ->displayFormat('d F Y')
                            ->required(),
                        Forms\Components\DatePicker::make('overtime_date')
                            ->label('Tanggal Pelaksanaan Lembur')
                            ->default(now())
                            ->native(false)
                            ->displayFormat('d F Y')
                            ->live()
                            ->required(),
                        Forms\Components\Radio::make('overtime_type')
                            ->label('Jenis Kerja Lembur')
                            ->options([
                                'Biasa' => 'Biasa',
                                'Emergency' => 'Emergency',
                            ])
                            ->default('Biasa')
                            ->inline()
                            ->required(),
                        Forms\Components\Select::make('work_mode')
                            ->label('Status Shift / Non-Shift')
                            ->options([
                                'non_shift' => 'Non-Shift',
                                'shift' => 'Shift',
                            ])
                            ->default('non_shift')
                            ->live()
                            ->required(),
                        Forms\Components\Toggle::make('is_shift_day_off')
                            ->label('Hari Libur Jadwal Shift (L)')
                            ->helperText('Centang jika pegawai Shift lembur pada hari libur jadwal shift-nya.')
                            ->visible(fn (Forms\Get $get): bool => $get('work_mode') === 'shift')
                            ->live()
                            ->default(false),
                        Forms\Components\Placeholder::make('day_type_preview')
                            ->label('Klasifikasi Hari Otomatis')
                            ->content(function (Forms\Get $get): string {
                                $date = $get('overtime_date');
                                if (! $date) {
                                    return '-';
                                }
                                $workMode = $get('work_mode') ?: 'non_shift';
                                $isDayOff = (bool) $get('is_shift_day_off');
                                $dayType = app(\App\Services\Overtime\OvertimeCalculatorService::class)->resolveDayType($date, $workMode, $isDayOff);
                                $carbon = \Carbon\Carbon::parse($date);

                                if ($dayType === 'L') {
                                    if (\App\Models\NationalHoliday::isHoliday($carbon)) {
                                        return 'L — Hari Libur Nasional / Cuti Bersama (Tarif 2×/3×/4×)';
                                    }
                                    if ($workMode === 'non_shift' && $carbon->isWeekend()) {
                                        return 'L — Akhir Pekan Sabtu/Minggu, 5 Hari Kerja (Tarif 2×/3×/4×)';
                                    }

                                    return 'L — Hari Libur Jadwal Shift (Tarif 2×/3×/4×)';
                                }

                                return 'N — Hari Kerja Normal (Tarif 1,5× jam ke-1, 2× jam berikutnya)';
                            }),
                        Forms\Components\TextInput::make('planned_hours')
                            ->label('Estimasi Durasi Lembur (Jam)')
                            ->numeric()
                            ->suffix('Jam')
                            ->helperText('Digunakan untuk validasi awal batas PP 35/2021 (maks 4 jam/hari kerja, 18 jam/minggu).'),
                        Forms\Components\Textarea::make('work_description')
                            ->label('Uraian Kerja Lembur')
                            ->rows(3)
                            ->required()
                            ->columnSpanFull(),
                        Forms\Components\Textarea::make('compliance_override_reason')
                            ->label('Catatan Khusus (Jika Melebihi Batas Jam Lembur)')
                            ->placeholder('Isi alasan operasional/darurat jika estimasi lembur melebihi batas harian/mingguan.')
                            ->rows(2)
                            ->columnSpanFull(),
                    ])
                    ->columns(['default' => 1, 'sm' => 2]),
            ]);
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Infolists\Components\Section::make('Status & Nomor Dokumen SPKL')
                    ->icon('heroicon-o-shield-check')
                    ->iconColor('primary')
                    ->schema([
                        Infolists\Components\TextEntry::make('overtime_number')
                            ->label('Nomor Dokumen')
                            ->badge()
                            ->color('info')
                            ->placeholder('Terbit setelah Perintah disetujui Manajer'),
                        Infolists\Components\TextEntry::make('status')
                            ->label('Status Keseluruhan')
                            ->badge()
                            ->formatStateUsing(fn (string $state): string => match ($state) {
                                'command_pending' => 'Tahap 1: Menunggu Persetujuan Perintah (Manajer)',
                                'command_approved' => 'Tahap 2: Menunggu Pertanggungjawaban Pegawai',
                                'report_pending' => 'Tahap 2: Menunggu Pengesahan Manajer',
                                'approved' => 'Disetujui Sepenuhnya',
                                'rejected' => 'Ditolak',
                                default => ucfirst($state),
                            })
                            ->color(fn (string $state): string => match ($state) {
                                'command_pending', 'report_pending' => 'warning',
                                'command_approved' => 'info',
                                'approved' => 'success',
                                'rejected' => 'danger',
                                default => 'gray',
                            }),
                        Infolists\Components\TextEntry::make('creator.name')
                            ->label('Dibuat Oleh')
                            ->placeholder('-'),
                    ])
                    ->columns(['default' => 1, 'sm' => 3]),

                Infolists\Components\Section::make('A. Surat Perintah Kerja Lembur')
                    ->icon('heroicon-o-document-text')
                    ->iconColor('primary')
                    ->schema([
                        Infolists\Components\TextEntry::make('employee.name')
                            ->label('Pegawai yang Ditugaskan')
                            ->weight('bold'),
                        Infolists\Components\TextEntry::make('satuan_kerja')
                            ->label('Satuan Kerja'),
                        Infolists\Components\TextEntry::make('overtime_date')
                            ->label('Tanggal Lembur')
                            ->date('d F Y'),
                        Infolists\Components\TextEntry::make('overtime_type')
                            ->label('Jenis Kerja Lembur')
                            ->badge()
                            ->color(fn (string $state): string => $state === 'Emergency' ? 'danger' : 'info'),
                        Infolists\Components\TextEntry::make('command_date')
                            ->label('Tanggal Perintah')
                            ->date('d F Y'),
                        Infolists\Components\TextEntry::make('commandApprover.name')
                            ->label('Pejabat yang Memerintahkan')
                            ->placeholder('Menunggu persetujuan Manajer'),
                        Infolists\Components\TextEntry::make('work_description')
                            ->label('Uraian Kerja Lembur')
                            ->columnSpanFull(),
                    ])
                    ->columns(['default' => 1, 'sm' => 3]),

                Infolists\Components\Section::make('B. Surat Pertanggung Jawaban Kerja Lembur')
                    ->icon('heroicon-o-clipboard-document-check')
                    ->iconColor('success')
                    ->schema([
                        Infolists\Components\TextEntry::make('time_range')
                            ->label('Jam Pelaksanaan Lembur')
                            ->state(fn (OvertimeOrder $record): string => ($record->start_time && $record->end_time) ? "{$record->start_time} s/d {$record->end_time}" : '-'),
                        Infolists\Components\TextEntry::make('report_date')
                            ->label('Tanggal Pertanggungjawaban')
                            ->date('d F Y')
                            ->placeholder('-'),
                        Infolists\Components\TextEntry::make('report_status')
                            ->label('Status Pertanggungjawaban')
                            ->badge()
                            ->formatStateUsing(fn (string $state): string => match ($state) {
                                'not_submitted' => 'Belum Diisi Pegawai',
                                'pending' => 'Menunggu Pengesahan Manajer',
                                'approved' => 'Disahkan Manajer',
                                'rejected' => 'Ditolak (Perlu Perbaikan)',
                                default => ucfirst($state),
                            })
                            ->color(fn (string $state): string => match ($state) {
                                'not_submitted' => 'gray',
                                'pending' => 'warning',
                                'approved' => 'success',
                                'rejected' => 'danger',
                                default => 'gray',
                            }),
                        Infolists\Components\TextEntry::make('actual_result')
                            ->label('Hasil Kerja Lembur')
                            ->placeholder('Belum diisi oleh pegawai')
                            ->columnSpanFull(),
                    ])
                    ->columns(['default' => 1, 'sm' => 3]),

                Infolists\Components\Section::make('Perhitungan Upah Lembur & Kepatuhan (PP 35/2021)')
                    ->icon('heroicon-o-calculator')
                    ->iconColor('info')
                    ->schema([
                        Infolists\Components\TextEntry::make('work_mode')
                            ->label('Pola Kerja')
                            ->badge()
                            ->formatStateUsing(fn (?string $state): string => $state === 'shift' ? 'Shift' : 'Non-Shift'),
                        Infolists\Components\TextEntry::make('day_type')
                            ->label('Klasifikasi Hari')
                            ->badge()
                            ->formatStateUsing(fn (?string $state): string => match ($state) {
                                'L' => 'L — Hari Libur / Akhir Pekan',
                                'N' => 'N — Hari Kerja Normal',
                                default => '-',
                            })
                            ->color(fn (?string $state): string => $state === 'L' ? 'warning' : 'info'),
                        Infolists\Components\TextEntry::make('actual_hours')
                            ->label('Durasi Aktual')
                            ->formatStateUsing(fn ($state): string => $state !== null ? number_format((float) $state, 2) . ' Jam' : '-'),
                        Infolists\Components\TextEntry::make('hourly_tariff')
                            ->label('Tarif Sejam (Valid)')
                            ->formatStateUsing(fn ($state): string => $state !== null ? 'Rp ' . number_format((float) $state, 2, ',', '.') : '-'),
                        Infolists\Components\TextEntry::make('multiplier')
                            ->label('Koefisien Pengali')
                            ->formatStateUsing(fn ($state): string => $state !== null ? number_format((float) $state, 2) . '×' : '-'),
                        Infolists\Components\TextEntry::make('overtime_pay')
                            ->label('Estimasi Nilai Lembur')
                            ->weight('bold')
                            ->formatStateUsing(fn ($state): string => $state !== null ? 'Rp ' . number_format(round((float) $state, 0), 0, ',', '.') : '-'),
                        Infolists\Components\TextEntry::make('compliance_status')
                            ->label('Status Kepatuhan')
                            ->badge()
                            ->formatStateUsing(fn (?string $state): string => match ($state) {
                                'ok' => 'Sesuai Aturan',
                                'warning' => 'Melebihi Batas (Dengan Catatan Khusus)',
                                'blocked' => 'Diblokir',
                                default => 'Sesuai Aturan',
                            })
                            ->color(fn (?string $state): string => match ($state) {
                                'ok' => 'success',
                                'warning' => 'warning',
                                'blocked' => 'danger',
                                default => 'gray',
                            }),
                        Infolists\Components\TextEntry::make('compliance_override_reason')
                            ->label('Catatan Khusus Kepatuhan')
                            ->placeholder('-')
                            ->columnSpan(2),
                    ])
                    ->columns(['default' => 1, 'sm' => 3]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->contentGrid(['default' => 1, 'xl' => 1])
            ->columns([
                Tables\Columns\Layout\Split::make([
                    Tables\Columns\TextColumn::make('employee.name')
                        ->label('Pegawai')
                        ->weight('bold')
                        ->searchable(),
                    Tables\Columns\TextColumn::make('overtime_type')
                        ->label('Jenis')
                        ->badge()
                        ->color(fn (string $state): string => $state === 'Emergency' ? 'danger' : 'info')
                        ->grow(false),
                    Tables\Columns\TextColumn::make('status')
                        ->label('Status')
                        ->badge()
                        ->grow(false)
                        ->formatStateUsing(fn (string $state): string => match ($state) {
                            'command_pending' => 'Menunggu Perintah Manajer',
                            'command_approved' => 'Menunggu Pertanggungjawaban',
                            'report_pending' => 'Menunggu Pengesahan Manajer',
                            'approved' => 'Disetujui Sepenuhnya',
                            'rejected' => 'Ditolak',
                            default => ucfirst($state),
                        })
                        ->color(fn (string $state): string => match ($state) {
                            'command_pending', 'report_pending' => 'warning',
                            'command_approved' => 'info',
                            'approved' => 'success',
                            'rejected' => 'danger',
                            default => 'gray',
                        }),
                ])->from('md'),

                Tables\Columns\Layout\Panel::make([
                    Tables\Columns\Layout\Grid::make(['default' => 1, 'sm' => 2, 'md' => 3])
                        ->schema([
                            Tables\Columns\TextColumn::make('overtime_number')
                                ->description('Nomor SPKL', position: 'above')
                                ->badge()
                                ->color('info')
                                ->placeholder('Belum terbit'),
                            Tables\Columns\TextColumn::make('overtime_date')
                                ->description('Tanggal Lembur', position: 'above')
                                ->date('d M Y'),
                            Tables\Columns\TextColumn::make('work_mode')
                                ->description('Pola Kerja', position: 'above')
                                ->badge()
                                ->formatStateUsing(fn (?string $state): string => $state === 'shift' ? 'Shift' : 'Non-Shift'),
                            Tables\Columns\TextColumn::make('satuan_kerja')
                                ->description('Satuan Kerja', position: 'above'),
                            Tables\Columns\TextColumn::make('time_range')
                                ->description('Jam & Durasi Lembur', position: 'above')
                                ->state(fn (OvertimeOrder $record): string => ($record->start_time && $record->end_time) ? "{$record->start_time} s/d {$record->end_time} ({$record->actual_hours} jam)" : 'Belum diisi'),
                            Tables\Columns\TextColumn::make('overtime_pay')
                                ->description('Nilai Lembur', position: 'above')
                                ->formatStateUsing(fn ($state): string => $state !== null ? 'Rp ' . number_format(round((float) $state, 0), 0, ',', '.') : '-'),
                        ]),
                ])->collapsible(),
            ])
            ->filters([
                \App\Filament\Admin\Support\RecapTableSupport::monthYearFilter('overtime_date'),
                Tables\Filters\SelectFilter::make('status')
                    ->label('Status')
                    ->options([
                        'command_pending' => 'Menunggu Perintah Manajer',
                        'command_approved' => 'Menunggu Pertanggungjawaban',
                        'report_pending' => 'Menunggu Pengesahan Manajer',
                        'approved' => 'Disetujui Sepenuhnya',
                        'rejected' => 'Ditolak',
                    ]),
            ])
            ->actions([
                Tables\Actions\ViewAction::make()
                    ->label('Lihat / Proses')
                    ->icon('heroicon-o-eye')
                    ->button()
                    ->size('sm')
                    ->color('primary'),
                Tables\Actions\Action::make('print')
                    ->label('Cetak')
                    ->icon('heroicon-o-printer')
                    ->button()
                    ->size('sm')
                    ->color('info')
                    ->url(fn (OvertimeOrder $record): string => route('overtimes.print', $record))
                    ->openUrlInNewTab(),
            ]);
    }

    public static function canUserApproveStage(OvertimeOrder $record, ?\App\Models\User $user = null): bool
    {
        $user = $user ?? (\Filament\Facades\Filament::auth()->user() ?? auth()->user());
        $employee = $user?->employee;
        if (! $employee) {
            return false;
        }

        $workflow = app(OvertimeWorkflowService::class);
        $pendingReq = $workflow->getPendingCommandRequest($record) ?? $workflow->getPendingReportRequest($record);

        return $pendingReq !== null && (new ApprovalResolver())->canApprove($pendingReq, $employee);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListOvertimeOrders::route('/'),
            'create' => Pages\CreateOvertimeOrder::route('/create'),
            'view' => Pages\ViewOvertimeOrder::route('/{record}'),
            'edit' => Pages\EditOvertimeOrder::route('/{record}/edit'),
        ];
    }
}
