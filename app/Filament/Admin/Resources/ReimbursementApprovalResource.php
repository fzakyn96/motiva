<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\ReimbursementApprovalResource\Pages;
use App\Models\ApprovalRequest;
use App\Models\Reimbursement;
use App\Observers\ReimbursementObserver;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ReimbursementApprovalResource extends Resource
{
    protected static ?string $model = ApprovalRequest::class;

    protected static ?string $navigationIcon = 'heroicon-o-check-badge';
    protected static ?string $navigationGroup = 'Persetujuan';
    protected static ?string $navigationLabel = 'Persetujuan Reimbursement';
    protected static ?string $slug = 'reimbursement-approvals';
    protected static ?string $modelLabel = 'Persetujuan Reimbursement';
    protected static ?string $pluralModelLabel = 'Persetujuan Reimbursement';
    protected static ?int $navigationSort = 3;

    public static function canViewAny(): bool
    {
        $user = \Filament\Facades\Filament::auth()->user() ?? auth()->user();
        if (! $user) return false;
        return $user->hasAnyRole(['Superadmin', 'Admin', 'Manager', 'HR']);
    }

    public static function getNavigationBadge(): ?string
    {
        $pendingCount = static::getEloquentQuery()->where('status', 'pending')->count();
        if ($pendingCount > 0) {
            return (string) $pendingCount;
        }

        $totalCount = static::getEloquentQuery()->count();
        return $totalCount > 0 ? (string) $totalCount : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return static::getEloquentQuery()->where('status', 'pending')->exists()
            ? 'warning'
            : 'success';
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return static::getEloquentQuery()->where('status', 'pending')->exists()
            ? 'Menunggu Persetujuan Reimbursement'
            : 'Total Data Persetujuan Reimbursement';
    }

    public static function getNavigationItems(): array
    {
        return [
            \Filament\Navigation\NavigationItem::make(static::getNavigationLabel())
                ->group(static::getNavigationGroup())
                ->parentItem(static::getNavigationParentItem())
                ->icon(static::getNavigationIcon())
                ->activeIcon(static::getActiveNavigationIcon())
                ->isActiveWhen(function () {
                    if (request()->routeIs(static::getRouteBaseName() . '.*')) {
                        return true;
                    }
                    if (request()->routeIs('filament.admin.resources.reimbursements.review') && request()->query('from') === 'approvals') {
                        return true;
                    }
                    return false;
                })
                ->badge(static::getNavigationBadge(), color: static::getNavigationBadgeColor())
                ->badgeTooltip(static::getNavigationBadgeTooltip())
                ->sort(static::getNavigationSort())
                ->url(static::getNavigationUrl()),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        // Auto-heal: jika ada Reimbursement pending yang belum memiliki ApprovalRequest, sinkronkan otomatis
        $unlinked = Reimbursement::where('status', 'pending')
            ->whereDoesntHave('approvalRequests')
            ->get();

        if ($unlinked->isNotEmpty()) {
            $observer = new ReimbursementObserver();
            foreach ($unlinked as $reimb) {
                $observer->ensureApprovalRequestExists($reimb);
            }
        }

        $query = parent::getEloquentQuery()->where('document_type', Reimbursement::class);
        $user = \Filament\Facades\Filament::auth()->user() ?? auth()->user();

        if (! $user || $user->hasRole(['Superadmin', 'Admin'])) {
            return $query;
        }

        $employee = $user->employee;
        if (! $employee) {
            return $query;
        }

        // Manager, Supervisor & HR
        return $query->where(function (Builder $q) use ($user, $employee) {
            $q->whereHasMorph('document', [Reimbursement::class], function (Builder $reimbQuery) use ($user, $employee) {
                $reimbQuery->whereHas('employee', function (Builder $empQuery) use ($employee) {
                    $empQuery->where('department_id', $employee->department_id)
                        ->orWhere('manager_id', $employee->id)
                        ->orWhere('id', $employee->id);
                });

                if ($user->hasRole('Manager') || $user->hasRole('HR')) {
                    $reimbQuery->orWhere('status', 'pending');
                }
            });
        });
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('status')
                    ->label('Status')
                    ->disabled(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\Layout\Split::make([
                    Tables\Columns\Layout\Stack::make([
                        Tables\Columns\TextColumn::make('document.reimbursement_number')
                            ->label('Nomor Reimbursement')
                            ->weight('bold')
                            ->color('primary')
                            ->searchable()
                            ->copyable(),
                        Tables\Columns\TextColumn::make('document.title')
                            ->label('Keperluan')
                            ->color('gray')
                            ->limit(40),
                        Tables\Columns\TextColumn::make('document.employee.name')
                            ->label('Pegawai Pengaju')
                            ->icon('heroicon-o-user')
                            ->formatStateUsing(function (ApprovalRequest $record) {
                                $emp = $record->document?->employee;
                                if (! $emp) return '-';
                                $pos = $emp->position?->name;
                                return $pos ? "{$emp->name} ({$pos})" : $emp->name;
                            }),
                    ])->space(1),

                    Tables\Columns\Layout\Stack::make([
                        Tables\Columns\TextColumn::make('document.sppd.sppd_number')
                            ->label('Nomor SPPD')
                            ->badge()
                            ->color('gray')
                            ->formatStateUsing(fn ($state) => $state ? "SPPD: {$state}" : '-'),
                        Tables\Columns\TextColumn::make('document.sppd.businessTrip.destination')
                            ->label('Tujuan Dinas')
                            ->icon('heroicon-o-map-pin')
                            ->color('gray')
                            ->placeholder('-'),
                        Tables\Columns\TextColumn::make('status')
                            ->badge()
                            ->formatStateUsing(fn (string $state): string => match ($state) {
                                'pending' => 'Menunggu Persetujuan Manajer',
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
                    ])->space(1),

                    Tables\Columns\Layout\Stack::make([
                        Tables\Columns\TextColumn::make('document.total_claim_amount')
                            ->label('Total Klaim')
                            ->money('IDR', locale: 'id')
                            ->weight('bold')
                            ->color('warning')
                            ->description('Klaim Yang Diganti', position: 'above'),
                        Tables\Columns\TextColumn::make('created_at')
                            ->label('Diajukan Pada')
                            ->dateTime('d/m/Y H:i')
                            ->color('gray'),
                    ])->space(1)->alignment('right'),
                ])->from('md'),

                Tables\Columns\Layout\Panel::make([
                    Tables\Columns\Layout\Grid::make(['default' => 1, 'sm' => 2, 'md' => 3])
                        ->schema([
                            Tables\Columns\TextColumn::make('document.expenses_count')
                                ->label('Item Kwitansi')
                                ->state(fn (ApprovalRequest $record) => ($record->document?->expenses()->count() ?? 0) . ' Item Kwitansi Terlampir')
                                ->icon('heroicon-o-receipt-percent'),
                            Tables\Columns\TextColumn::make('document.total_amount')
                                ->label('Total Keseluruhan')
                                ->money('IDR', locale: 'id')
                                ->description('Total Termasuk Kantor', position: 'above'),
                            Tables\Columns\TextColumn::make('document.notes')
                                ->label('Catatan Pengaju')
                                ->placeholder('-')
                                ->description('Catatan Tambahan', position: 'above'),
                        ]),
                ])->collapsible(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label('Status Persetujuan')
                    ->options([
                        'pending' => 'Menunggu Persetujuan Manajer',
                        'approved' => 'Disetujui Manajer',
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
                    ->url(fn (ApprovalRequest $record) => ReimbursementResource::getUrl('review', ['record' => $record->document_id, 'from' => 'approvals']))
                    ->visible(fn (ApprovalRequest $record) => $record->status === 'pending'),

                Tables\Actions\Action::make('view_review')
                    ->label('Lihat')
                    ->icon('heroicon-o-document-magnifying-glass')
                    ->button()
                    ->size('sm')
                    ->color('gray')
                    ->url(fn (ApprovalRequest $record) => ReimbursementResource::getUrl('review', ['record' => $record->document_id, 'from' => 'approvals']))
                    ->visible(fn (ApprovalRequest $record) => $record->status !== 'pending'),

                Tables\Actions\Action::make('print')
                    ->label('Cetak')
                    ->icon('heroicon-o-printer')
                    ->button()
                    ->size('sm')
                    ->color('info')
                    ->url(fn (ApprovalRequest $record) => route('reimbursements.print', $record->document_id))
                    ->openUrlInNewTab()
                    ->visible(fn (ApprovalRequest $record) => $record->status === 'approved'),
            ])
            ->bulkActions([
                //
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListReimbursementApprovals::route('/'),
        ];
    }
}
