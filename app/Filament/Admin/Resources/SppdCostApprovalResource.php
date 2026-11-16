<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\SppdCostApprovalResource\Pages;
use App\Filament\Admin\Support\RecapTableSupport;
use App\Models\ApprovalRequest;
use App\Models\SppdCost;
use App\Services\Approval\ApprovalResolver;
use App\Services\Signature\SignatureService;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

class SppdCostApprovalResource extends Resource
{
    protected static ?string $model = ApprovalRequest::class;

    protected static ?string $navigationIcon = 'heroicon-o-calculator';
    protected static ?string $navigationGroup = 'Persetujuan';
    protected static ?string $navigationLabel = 'Persetujuan Biaya SPPD';
    protected static ?string $slug = 'sppd-cost-approvals';
    protected static ?string $modelLabel = 'Persetujuan Biaya SPPD';
    protected static ?string $pluralModelLabel = 'Persetujuan Biaya SPPD';
    protected static ?int $navigationSort = 3;

    public static function canViewAny(): bool
    {
        $user = \Filament\Facades\Filament::auth()->user() ?? auth()->user();

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
        return RecapTableSupport::pendingApprovalBadge(SppdCost::class);
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return static::getNavigationBadge() ? 'warning' : 'success';
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->where('document_type', SppdCost::class)
            ->with(['document.sppd.employee.position', 'document.sppd.businessTrip', 'steps.policyStep'])
            ->latest('id');
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\Layout\Split::make([
                    Tables\Columns\TextColumn::make('sppd_number')
                        ->label('Nomor SPPD')
                        ->state(fn (ApprovalRequest $record): string => $record->document?->sppd?->sppd_number ?: 'SPPD #' . ($record->document?->sppd_id ?? '-'))
                        ->weight('bold'),
                    Tables\Columns\TextColumn::make('employee_name')
                        ->label('Pegawai')
                        ->state(fn (ApprovalRequest $record): string => $record->document?->sppd?->employee?->name ?? '-'),
                    Tables\Columns\TextColumn::make('total_cost')
                        ->label('Total Biaya')
                        ->state(fn (ApprovalRequest $record): string => 'Rp ' . number_format((float) ($record->document?->total_cost ?? 0), 0, ',', '.'))
                        ->badge()
                        ->color('success')
                        ->grow(false),
                    Tables\Columns\TextColumn::make('status')
                        ->label('Status')
                        ->badge()
                        ->grow(false)
                        ->formatStateUsing(fn (string $state): string => match ($state) {
                            'pending' => 'Menunggu Manajer',
                            'approved' => 'Disetujui Manajer',
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
                            Tables\Columns\TextColumn::make('destination')
                                ->description('Tujuan & Jadwal', position: 'above')
                                ->state(function (ApprovalRequest $record): string {
                                    $sppd = $record->document?->sppd;
                                    if (! $sppd) {
                                        return '-';
                                    }
                                    $dest = $sppd->destination_location ?: ($sppd->businessTrip?->destination ?? '-');
                                    $days = (int) ($record->document?->official_days ?? $sppd->total_days ?? 1);

                                    return "{$dest} ({$days} Hari)";
                                }),
                            Tables\Columns\TextColumn::make('breakdown')
                                ->description('Rincian (Transport • Harian • Lain)', position: 'above')
                                ->state(function (ApprovalRequest $record): string {
                                    $c = $record->document;
                                    if (! $c) {
                                        return '-';
                                    }

                                    return 'Rp ' . number_format((float) $c->subtotal_transport, 0, ',', '.')
                                        . ' • Rp ' . number_format((float) $c->subtotal_per_diem, 0, ',', '.')
                                        . ' • Rp ' . number_format((float) $c->subtotal_other, 0, ',', '.');
                                }),
                            Tables\Columns\TextColumn::make('created_at')
                                ->description('Diajukan Pada', position: 'above')
                                ->dateTime('d/m/Y H:i'),
                        ]),
                ])->collapsible(),
            ])
            ->filters([
                RecapTableSupport::monthYearFilter('created_at'),
                Tables\Filters\SelectFilter::make('status')
                    ->label('Status Persetujuan')
                    ->options([
                        'pending' => 'Menunggu Manajer',
                        'approved' => 'Disetujui Manajer',
                        'rejected' => 'Ditolak',
                    ]),
            ])
            ->actions([
                Tables\Actions\Action::make('print')
                    ->label('Lihat FM-BS-02')
                    ->icon('heroicon-o-printer')
                    ->color('info')
                    ->button()
                    ->size('sm')
                    ->url(fn (ApprovalRequest $record): string => route('sppd-costs.print', $record->document_id))
                    ->openUrlInNewTab(),

                Tables\Actions\Action::make('approve')
                    ->label('Setujui & Tandatangani')
                    ->icon('heroicon-o-check-badge')
                    ->color('success')
                    ->button()
                    ->size('sm')
                    ->form([
                        Textarea::make('notes')
                            ->label('Catatan Persetujuan (Opsional)')
                            ->rows(2),
                    ])
                    ->modalHeading('Konfirmasi Persetujuan Biaya SPPD')
                    ->modalSubmitActionLabel('Ya, Setujui & Tandatangani')
                    ->visible(function (ApprovalRequest $record): bool {
                        $employee = (\Filament\Facades\Filament::auth()->user() ?? auth()->user())?->employee;

                        return $employee && $record->status === 'pending' && (new ApprovalResolver())->canApprove($record, $employee);
                    })
                    ->action(function (ApprovalRequest $record, array $data): void {
                        $user = \Filament\Facades\Filament::auth()->user() ?? auth()->user();
                        $employee = $user?->employee;
                        if (! $employee || ! app(SignatureService::class)->ensureUserCanSign($user)) {
                            return;
                        }

                        (new ApprovalResolver())->approve($record, $employee, $data['notes'] ?? null);

                        Notification::make()
                            ->title('Biaya SPPD Disetujui & Ditandatangani')
                            ->body('Perhitungan biaya SPPD telah disahkan dan siap direkap.')
                            ->success()
                            ->send();
                    }),

                Tables\Actions\Action::make('reject')
                    ->label('Tolak')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->button()
                    ->size('sm')
                    ->form([
                        Textarea::make('notes')
                            ->label('Alasan Penolakan')
                            ->required()
                            ->rows(2),
                    ])
                    ->visible(function (ApprovalRequest $record): bool {
                        $employee = (\Filament\Facades\Filament::auth()->user() ?? auth()->user())?->employee;

                        return $employee && $record->status === 'pending' && (new ApprovalResolver())->canApprove($record, $employee);
                    })
                    ->action(function (ApprovalRequest $record, array $data): void {
                        $employee = (\Filament\Facades\Filament::auth()->user() ?? auth()->user())?->employee;
                        if (! $employee) {
                            return;
                        }

                        (new ApprovalResolver())->reject($record, $employee, $data['notes']);

                        Notification::make()
                            ->title('Biaya SPPD Ditolak')
                            ->danger()
                            ->send();
                    }),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\BulkAction::make('bulk_approve')
                        ->label('Setujui & Tandatangani Terpilih')
                        ->icon('heroicon-o-check-badge')
                        ->color('success')
                        ->deselectRecordsAfterCompletion()
                        ->requiresConfirmation()
                        ->modalHeading('Konfirmasi Persetujuan Massal Biaya SPPD')
                        ->modalSubmitActionLabel('Ya, Setujui & Tandatangani')
                        ->form([
                            Textarea::make('notes')
                                ->label('Catatan Persetujuan (Opsional)')
                                ->rows(2),
                        ])
                        ->action(function (Collection $records, array $data): void {
                            $user = \Filament\Facades\Filament::auth()->user() ?? auth()->user();
                            $employee = $user?->employee;
                            if (! $employee || ! app(SignatureService::class)->ensureUserCanSign($user)) {
                                return;
                            }

                            $resolver = new ApprovalResolver();
                            $count = 0;
                            $notes = $data['notes'] ?? 'Disetujui massal oleh Manajer';

                            foreach ($records as $record) {
                                if (! $record instanceof ApprovalRequest || $record->status !== 'pending') {
                                    continue;
                                }
                                if (! $resolver->canApprove($record, $employee)) {
                                    continue;
                                }

                                $resolver->approve($record, $employee, $notes);
                                $count++;
                            }

                            Notification::make()
                                ->title('Persetujuan Massal Biaya SPPD Selesai')
                                ->body("{$count} dokumen Biaya SPPD berhasil disetujui dan ditandatangani.")
                                ->success()
                                ->send();
                        }),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSppdCostApprovals::route('/'),
        ];
    }
}
