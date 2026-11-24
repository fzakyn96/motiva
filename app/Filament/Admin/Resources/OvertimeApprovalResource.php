<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\OvertimeApprovalResource\Pages;
use App\Models\ApprovalRequest;
use App\Models\OvertimeOrder;
use App\Services\Approval\ApprovalResolver;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class OvertimeApprovalResource extends Resource
{
    protected static ?string $model = ApprovalRequest::class;

    protected static ?string $navigationIcon = 'heroicon-o-clock';

    protected static ?string $navigationGroup = 'Persetujuan';

    protected static ?string $navigationLabel = 'Persetujuan Lembur';

    protected static ?string $slug = 'overtime-approvals';

    protected static ?string $modelLabel = 'Persetujuan Lembur';

    protected static ?string $pluralModelLabel = 'Persetujuan Lembur';

    protected static ?int $navigationSort = 5;

    protected static function currentUser(): ?\App\Models\User
    {
        return (\Filament\Facades\Filament::getCurrentPanel() ? \Filament\Facades\Filament::auth()->user() : null) ?? auth()->user();
    }

    public static function canViewAny(): bool
    {
        $user = static::currentUser();

        return $user !== null && $user->hasAnyRole(['Superadmin', 'Admin', 'Manager']);
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function getNavigationBadge(): ?string
    {
        $count = static::getEloquentQuery()->where('status', 'pending')->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return static::getNavigationBadge() ? 'warning' : 'success';
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()
            ->where('document_type', OvertimeOrder::class)
            ->whereIn('document_id', OvertimeOrder::select('id'))
            ->with(['document.employee.position', 'policy', 'steps.policyStep'])
            ->latest('id');

        $user = static::currentUser();
        if (! $user || $user->hasAnyRole(['Superadmin', 'Admin'])) {
            return $query;
        }

        $employee = $user->employee;
        if ($employee && $employee->work_area_id) {
            $query->whereHasMorph('document', [OvertimeOrder::class], function (Builder $orderQuery) use ($employee) {
                $orderQuery->whereHas('employee', fn (Builder $eq) => $eq->where('work_area_id', $employee->work_area_id));
            });
        }

        return $query;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->contentGrid(['default' => 1, 'xl' => 1])
            ->columns([
                Tables\Columns\Layout\Split::make([
                    Tables\Columns\TextColumn::make('employee_name')
                        ->label('Pegawai')
                        ->weight('bold')
                        ->state(fn (ApprovalRequest $record): string => $record->document?->employee?->name ?? '-'),
                    Tables\Columns\TextColumn::make('stage')
                        ->label('Tahap')
                        ->badge()
                        ->color('info')
                        ->grow(false)
                        ->state(fn (ApprovalRequest $record): string => str_contains((string) $record->policy?->model_type, ':report') ? 'Bagian B: Pertanggungjawaban' : 'Bagian A: Perintah Lembur'),
                    Tables\Columns\TextColumn::make('status')
                        ->label('Status')
                        ->badge()
                        ->grow(false)
                        ->formatStateUsing(fn (string $state): string => match ($state) {
                            'pending' => 'Menunggu Manajer',
                            'approved' => 'Disetujui',
                            'rejected' => 'Ditolak',
                            default => ucfirst($state),
                        })
                        ->color(fn (string $state): string => match ($state) {
                            'pending' => 'warning',
                            'approved' => 'success',
                            'rejected' => 'danger',
                            default => 'gray',
                        }),
                ])->from('md'),

                Tables\Columns\Layout\Panel::make([
                    Tables\Columns\Layout\Grid::make(['default' => 1, 'sm' => 3])
                        ->schema([
                            Tables\Columns\TextColumn::make('document.overtime_number')
                                ->description('Nomor SPKL', position: 'above')
                                ->placeholder('Belum terbit'),
                            Tables\Columns\TextColumn::make('document.overtime_date')
                                ->description('Tanggal Lembur', position: 'above')
                                ->date('d M Y'),
                            Tables\Columns\TextColumn::make('document.satuan_kerja')
                                ->description('Satuan Kerja', position: 'above'),
                        ]),
                ])->collapsible(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label('Status')
                    ->options([
                        'pending' => 'Menunggu Manajer',
                        'approved' => 'Disetujui',
                        'rejected' => 'Ditolak',
                    ]),
            ])
            ->actions([
                Tables\Actions\Action::make('review')
                    ->label('Proses')
                    ->icon('heroicon-o-play-circle')
                    ->button()
                    ->size('sm')
                    ->color('warning')
                    ->visible(function (ApprovalRequest $record): bool {
                        $employee = (\Filament\Facades\Filament::auth()->user() ?? auth()->user())?->employee;

                        return $employee && $record->status === 'pending' && (new ApprovalResolver())->canApprove($record, $employee);
                    })
                    ->url(fn (ApprovalRequest $record): string => OvertimeOrderResource::getUrl('view', ['record' => $record->document_id, 'from' => 'approvals'])),
                Tables\Actions\Action::make('view_detail')
                    ->label('Lihat')
                    ->icon('heroicon-o-eye')
                    ->button()
                    ->size('sm')
                    ->color('primary')
                    ->visible(function (ApprovalRequest $record): bool {
                        $employee = (\Filament\Facades\Filament::auth()->user() ?? auth()->user())?->employee;

                        return ! ($employee && $record->status === 'pending' && (new ApprovalResolver())->canApprove($record, $employee));
                    })
                    ->url(fn (ApprovalRequest $record): string => OvertimeOrderResource::getUrl('view', ['record' => $record->document_id, 'from' => 'approvals'])),
                Tables\Actions\Action::make('print')
                    ->label('Cetak')
                    ->icon('heroicon-o-printer')
                    ->button()
                    ->size('sm')
                    ->color('info')
                    ->url(fn (ApprovalRequest $record): string => route('overtimes.print', $record->document_id))
                    ->openUrlInNewTab(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListOvertimeApprovals::route('/'),
        ];
    }
}
