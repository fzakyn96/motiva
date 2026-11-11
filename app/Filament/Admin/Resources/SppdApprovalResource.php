<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\SppdApprovalResource\Pages;
use App\Models\ApprovalRequest;
use App\Models\Sppd;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class SppdApprovalResource extends Resource
{
    protected static ?string $model = ApprovalRequest::class;

    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-check';
    protected static ?string $navigationGroup = 'Persetujuan';
    protected static ?string $navigationLabel = 'Persetujuan SPPD';
    protected static ?string $slug = 'sppd-approvals';
    protected static ?string $modelLabel = 'Surat Perintah Perjalanan Dinas';
    protected static ?string $pluralModelLabel = 'Persetujuan SPPD';
    protected static ?int $navigationSort = 2;

    public static function canViewAny(): bool
    {
        $user = \Filament\Facades\Filament::auth()->user() ?? auth()->user();
        if (! $user) return false;
        return $user->hasAnyRole(['Superadmin', 'Admin', 'Manager', 'Supervisor', 'HR']);
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
            ? 'Menunggu Persetujuan SPPD'
            : 'Total Data Persetujuan SPPD';
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
                    if (request()->routeIs('filament.admin.resources.sppds.review') && request()->query('from') === 'approvals') {
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
        // Auto-heal: jika ada SPPD pending yang belum memiliki ApprovalRequest, sinkronkan otomatis
        $unlinked = Sppd::where('status', 'pending')
            ->whereDoesntHave('approvalRequests')
            ->get();
        if ($unlinked->isNotEmpty()) {
            $observer = new \App\Observers\SppdObserver();
            foreach ($unlinked as $sppd) {
                $observer->ensureApprovalRequestExists($sppd);
            }
        }

        $query = parent::getEloquentQuery()->where('document_type', Sppd::class);
        $user = \Filament\Facades\Filament::auth()->user() ?? auth()->user();

        if (! $user || $user->hasRole(['Superadmin', 'Admin'])) {
            return $query;
        }

        $employee = $user->employee;
        if (! $employee) {
            return $query;
        }

        // Supervisor & Manager can view SPPD approvals:
        // 1. Departemen yang sama
        // 2. Direct manager atau pengaju
        // 3. Jika berstatus 'pending', supervisor dan manager unit dapat melihatnya untuk ditinjau
        return $query->where(function (Builder $q) use ($user, $employee) {
            $q->whereHasMorph('document', [Sppd::class], function (Builder $sppdQuery) use ($user, $employee) {
                $sppdQuery->whereHas('employee', function (Builder $empQuery) use ($employee) {
                    $empQuery->where('department_id', $employee->department_id)
                        ->orWhere('manager_id', $employee->id)
                        ->orWhere('id', $employee->id);
                });

                if ($user->hasRole('Supervisor') || $user->hasRole('Manager')) {
                    $sppdQuery->orWhere('status', 'pending');
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
                    Tables\Columns\TextColumn::make('agenda')
                        ->label('Agenda')
                        ->state(fn (ApprovalRequest $record) => $record->document?->assignment_purpose ?: ($record->document?->businessTrip?->memo?->agenda ?: ($record->document?->businessTrip?->activity_name ?: '-')))
                        ->wrap()
                        ->weight('bold')
                        ->searchable(query: function (Builder $query, string $search): Builder {
                            return $query->whereHasMorph('document', [Sppd::class], function (Builder $q) use ($search) {
                                $q->where('assignment_purpose', 'like', "%{$search}%")
                                    ->orWhereHas('businessTrip', fn ($bt) => $bt->where('activity_name', 'like', "%{$search}%")
                                        ->orWhereHas('memo', fn ($mq) => $mq->where('agenda', 'like', "%{$search}%")));
                            });
                        }),
                    Tables\Columns\TextColumn::make('approval_stage')
                        ->label('Tahap Persetujuan')
                        ->badge()
                        ->grow(false)
                        ->state(function (ApprovalRequest $record) {
                            if ($record->status === 'approved') {
                                return 'Disetujui Sepenuhnya';
                            }
                            if ($record->status === 'rejected') {
                                return 'Ditolak';
                            }
                            $step = $record->currentStep();
                            if ($step && (int) $step->policyStep?->step_order === 1) {
                                return 'Step 1: Menunggu SPV';
                            }
                            if ($step && (int) $step->policyStep?->step_order === 2) {
                                return 'Step 2: Menunggu Manajer (SPV Disetujui)';
                            }
                            return 'Menunggu Persetujuan';
                        })
                        ->color(fn (string $state): string => match ($state) {
                            'Disetujui Sepenuhnya' => 'success',
                            'Ditolak' => 'danger',
                            'Step 2: Menunggu Manajer (SPV Disetujui)' => 'info',
                            'Step 1: Menunggu SPV' => 'warning',
                            default => 'warning',
                        }),
                ])->from('md'),

                Tables\Columns\Layout\Panel::make([
                    Tables\Columns\Layout\Grid::make(['default' => 1, 'sm' => 2, 'md' => 3])
                        ->schema([
                            Tables\Columns\TextColumn::make('document.employee.name')
                                ->description('Pegawai yang Ditugaskan', position: 'above')
                                ->weight('medium')
                                ->formatStateUsing(function (ApprovalRequest $record) {
                                    $emp = $record->document?->employee;
                                    if (! $emp) return '-';
                                    $pos = $emp->position?->name;
                                    return $pos ? "{$emp->name} ({$pos})" : $emp->name;
                                }),
                            Tables\Columns\TextColumn::make('period')
                                ->description('Jadwal Dinas', position: 'above')
                                ->state(function (ApprovalRequest $record) {
                                    $doc = $record->document;
                                    if (! $doc) return '-';
                                    $start = $doc->departure_date ?? ($doc->businessTrip?->memo?->assignment_start_date ?? $doc->businessTrip?->start_date);
                                    $end = $doc->return_date ?? ($doc->businessTrip?->memo?->assignment_end_date ?? $doc->businessTrip?->end_date);
                                    $startFormatted = $start ? \Carbon\Carbon::parse($start)->format('d M Y') : '-';
                                    $endFormatted = $end ? \Carbon\Carbon::parse($end)->format('d M Y') : '-';
                                    $days = $doc->total_days;
                                    if (! $days && $start && $end) {
                                        $days = \Carbon\Carbon::parse($start)->diffInDays(\Carbon\Carbon::parse($end)) + 1;
                                    }
                                    $daysStr = $days ? " ({$days} Hari)" : '';
                                    return "{$startFormatted} s/d {$endFormatted}{$daysStr}";
                                }),
                            Tables\Columns\TextColumn::make('document.destination_location')
                                ->description('Lokasi Penugasan', position: 'above')
                                ->placeholder('-'),
                            Tables\Columns\TextColumn::make('document.sppd_number')
                                ->description('Nomor SPPD', position: 'above')
                                ->placeholder('Belum terbit (Draf)'),
                            Tables\Columns\TextColumn::make('status')
                                ->description('Status Dokumen', position: 'above')
                                ->badge()
                                ->formatStateUsing(fn (string $state): string => match ($state) {
                                    'pending' => 'Menunggu Persetujuan',
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
                            Tables\Columns\TextColumn::make('created_at')
                                ->description('Diajukan Pada', position: 'above')
                                ->dateTime('d/m/Y H:i'),
                        ]),
                ])->collapsible(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label('Status Persetujuan')
                    ->options([
                        'pending' => 'Menunggu Persetujuan',
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
                    ->url(fn (ApprovalRequest $record) => SppdResource::getUrl('review', ['record' => $record->document_id, 'from' => 'approvals']))
                    ->visible(fn (ApprovalRequest $record) => $record->status === 'pending'),
                Tables\Actions\Action::make('view_review')
                    ->label('Lihat')
                    ->icon('heroicon-o-document-magnifying-glass')
                    ->button()
                    ->size('sm')
                    ->color('gray')
                    ->url(fn (ApprovalRequest $record) => SppdResource::getUrl('review', ['record' => $record->document_id, 'from' => 'approvals']))
                    ->visible(fn (ApprovalRequest $record) => $record->status !== 'pending'),
                Tables\Actions\Action::make('print')
                    ->label('Cetak')
                    ->icon('heroicon-o-printer')
                    ->button()
                    ->size('sm')
                    ->color('info')
                    ->url(fn (ApprovalRequest $record) => route('sppds.print', $record->document_id))
                    ->openUrlInNewTab()
                    ->visible(fn (ApprovalRequest $record) => $record->document?->status !== 'draft'),
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
            'index' => Pages\ListSppdApprovals::route('/'),
        ];
    }
}
