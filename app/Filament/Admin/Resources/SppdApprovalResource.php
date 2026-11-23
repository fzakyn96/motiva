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
    protected static ?string $navigationLabel = 'SPPD';
    protected static ?string $slug = 'sppd-approvals';
    protected static ?string $modelLabel = 'Surat Perintah Perjalanan Dinas';
    protected static ?string $pluralModelLabel = 'Persetujuan SPPD';
    protected static ?int $navigationSort = 2;

    public static function canViewAny(): bool
    {
        $user = \Filament\Facades\Filament::auth()->user() ?? auth()->user();
        if (! $user) return false;
        if ($user->employee?->isBusinessDivisionHead() && ! $user->hasAnyRole(['Superadmin', 'Admin'])) {
            return false;
        }
        return $user->hasAnyRole(['Superadmin', 'Admin', 'Manager', 'Supervisor']);
    }

    public static function isSupervisorOnly(?\App\Models\User $user = null): bool
    {
        $user = $user ?? (\Filament\Facades\Filament::auth()->user() ?? auth()->user());

        return $user !== null
            && $user->hasRole('Supervisor')
            && ! $user->hasAnyRole(['Manager', 'Division Head', 'Superadmin', 'Admin', 'HR']);
    }

    public static function applyActionablePendingScope(Builder $query, ?\App\Models\User $user = null): Builder
    {
        $query->where('status', 'pending');

        if (static::isSupervisorOnly($user)) {
            $query->whereHas('steps', function (Builder $stepQuery) {
                $stepQuery->where('status', 'pending')
                    ->whereHas('policyStep', fn (Builder $ps) => $ps->where(
                        'resolver_class',
                        \App\Services\Approval\Resolvers\SupervisorAuthorityResolver::class
                    ));
            });
        }

        return $query;
    }

    public static function applySupervisorApprovedScope(Builder $query, ?\App\Models\User $user = null): Builder
    {
        if (! static::isSupervisorOnly($user)) {
            return $query->where('status', 'approved');
        }

        return $query->where('status', '!=', 'rejected')
            ->where(function (Builder $q) {
                $q->where('status', 'approved')
                    ->orWhereHas('steps', function (Builder $stepQuery) {
                        $stepQuery->where('status', 'approved')
                            ->whereHas('policyStep', fn (Builder $ps) => $ps->where(
                                'resolver_class',
                                \App\Services\Approval\Resolvers\SupervisorAuthorityResolver::class
                            ));
                    });
            });
    }

    public static function canUserActOnApproval(ApprovalRequest $record, ?\App\Models\User $user = null): bool
    {
        if ($record->status !== 'pending') {
            return false;
        }

        $user = $user ?? (\Filament\Facades\Filament::auth()->user() ?? auth()->user());
        $employee = $user?->employee;
        if ($employee && ! $user?->hasAnyRole(['Superadmin', 'Admin'])) {
            return (new \App\Services\Approval\ApprovalResolver())->canApprove($record, $employee);
        }

        if (! static::isSupervisorOnly($user)) {
            return true;
        }

        return $record->currentStep()?->policyStep?->resolver_class === \App\Services\Approval\Resolvers\SupervisorAuthorityResolver::class;
    }

    /**
     * Return 1 representative ApprovalRequest ID per Memo (business_trip_id) within the given query.
     *
     * @return array<int, int>
     */
    public static function groupIdsByMemo(Builder $query): array
    {
        $items = (clone $query)
            ->with(['document'])
            ->reorder('id', 'desc')
            ->get(['id', 'document_type', 'document_id']);

        $seen = [];
        $ids = [];

        foreach ($items as $item) {
            $tripId = $item->document?->business_trip_id;
            $key = $tripId ? "trip:{$tripId}" : "single:{$item->id}";
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $ids[] = $item->id;
        }

        return $ids;
    }

    /**
     * Get all sibling SPPD ApprovalRequests under the same Memo/BusinessTrip visible to the current user.
     *
     * @return \Illuminate\Support\Collection<int, ApprovalRequest>
     */
    public static function siblingRequests(ApprovalRequest $record, ?string $statusFilter = null): \Illuminate\Support\Collection
    {
        $tripId = $record->document?->business_trip_id;
        $query = static::getEloquentQuery()
            ->with(['document.employee.position', 'document.cost', 'document.businessTrip.memo', 'steps.policyStep']);

        if ($statusFilter) {
            $query->where('status', $statusFilter);
        }

        if ($tripId) {
            $query->whereHasMorph('document', [Sppd::class], fn (Builder $q) => $q->where('business_trip_id', $tripId));
        } else {
            $query->whereKey($record->id);
        }

        $items = $query->reorder('id', 'asc')->get();

        return $items->isNotEmpty() ? $items : collect([$record]);
    }

    /**
     * Resolve the first sibling ApprovalRequest in the same Memo that the given user can act on.
     */
    public static function resolveActionableRequestForUser(ApprovalRequest $record, ?\App\Models\User $user = null): ?ApprovalRequest
    {
        if (static::canUserActOnApproval($record, $user)) {
            return $record;
        }

        foreach (static::siblingRequests($record, 'pending') as $sibling) {
            if ((int) $sibling->id !== (int) $record->id && static::canUserActOnApproval($sibling, $user)) {
                return $sibling;
            }
        }

        return null;
    }

    public static function getNavigationBadge(): ?string
    {
        $pendingCount = static::applyActionablePendingScope(static::getEloquentQuery())->count();
        if ($pendingCount > 0) {
            return (string) $pendingCount;
        }

        $totalCount = static::getEloquentQuery()->count();
        return $totalCount > 0 ? (string) $totalCount : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return static::applyActionablePendingScope(static::getEloquentQuery())->exists()
            ? 'warning'
            : 'success';
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return static::applyActionablePendingScope(static::getEloquentQuery())->exists()
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

        if (static::isSupervisorOnly($user)) {
            return $query->whereHasMorph('document', [Sppd::class], function (Builder $sppdQuery) use ($employee) {
                $sppdQuery->whereHas('employee', function (Builder $empQuery) use ($employee) {
                    if ($employee->work_area_id) {
                        $empQuery->where('work_area_id', $employee->work_area_id);
                    }

                    $empQuery->where(function (Builder $sub) use ($employee) {
                        $sub->where('manager_id', $employee->id)
                            ->orWhere(function (Builder $fallback) {
                                $fallback->whereDoesntHave('manager', function (Builder $mq) {
                                    $mq->whereHas('user.roles', fn (Builder $rq) => $rq->where('name', 'Supervisor'))
                                        ->orWhereHas('position', fn (Builder $pq) => $pq->where('level', 2)->orWhere('code', 'SPV')->orWhere('name', 'like', '%SUPERVISOR%'));
                                });
                            });
                    });
                });
            });
        }

        if ($employee->work_area_id) {
            return $query->whereHasMorph('document', [Sppd::class], function (Builder $sppdQuery) use ($employee) {
                $sppdQuery->whereHas('employee', function (Builder $empQuery) use ($employee) {
                    $empQuery->where('work_area_id', $employee->work_area_id);
                });
            });
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
            ->contentGrid(['default' => 1, 'xl' => 1])
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
                            if ($step && $step->policyStep?->resolver_class === \App\Services\Approval\Resolvers\SupervisorAuthorityResolver::class) {
                                return 'Step 1: Menunggu SPV';
                            }
                            if ($step && $step->policyStep?->resolver_class === \App\Services\Approval\Resolvers\ManagerAuthorityResolver::class) {
                                $hasSpv = $record->steps->contains(fn ($s) => $s->policyStep?->resolver_class === \App\Services\Approval\Resolvers\SupervisorAuthorityResolver::class);

                                return $hasSpv ? 'Step 2: Menunggu Manajer' : 'Menunggu Manajer';
                            }
                            return 'Menunggu Persetujuan';
                        })
                        ->color(fn (string $state): string => match ($state) {
                            'Disetujui Sepenuhnya' => 'success',
                            'Ditolak' => 'danger',
                            'Step 2: Menunggu Manajer' => 'info',
                            'Step 1: Menunggu SPV', 'Menunggu Manajer' => 'warning',
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
                                ->placeholder('Nomor dokumen belum terbit'),
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
                \App\Filament\Admin\Support\RecapTableSupport::monthYearFilter('created_at'),
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
                    ->visible(fn (ApprovalRequest $record) => static::canUserActOnApproval($record)),
                Tables\Actions\Action::make('view_review')
                    ->label('Lihat')
                    ->icon('heroicon-o-document-magnifying-glass')
                    ->button()
                    ->size('sm')
                    ->color('primary')
                    ->url(fn (ApprovalRequest $record) => SppdResource::getUrl('review', ['record' => $record->document_id, 'from' => 'approvals']))
                    ->visible(fn (ApprovalRequest $record) => ! static::canUserActOnApproval($record)),
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
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\BulkAction::make('bulk_approve')
                        ->label('Setujui & Tandatangani Terpilih')
                        ->icon('heroicon-o-check-badge')
                        ->color('success')
                        ->deselectRecordsAfterCompletion()
                        ->requiresConfirmation()
                        ->modalHeading('Konfirmasi Persetujuan Massal SPPD')
                        ->modalDescription(function (): string {
                            $user = \Filament\Facades\Filament::auth()->user() ?? auth()->user();
                            if (! $user?->hasConfiguredSignature()) {
                                return 'PERHATIAN: Anda belum mengatur tanda tangan digital. Silakan atur tanda tangan terlebih dahulu di menu Pengaturan Tanda Tangan sebelum menyetujui SPPD.';
                            }

                            return 'Seluruh SPPD yang Anda pilih dan berada di wewenang Anda akan disetujui serta dibubuhi tanda tangan digital secara otomatis.';
                        })
                        ->modalSubmitActionLabel('Ya, Setujui & Tandatangani')
                        ->form([
                            \Filament\Forms\Components\Textarea::make('notes')
                                ->label('Catatan Persetujuan (Opsional)')
                                ->rows(2),
                        ])
                        ->action(function (\Illuminate\Support\Collection $records, array $data): void {
                            $user = \Filament\Facades\Filament::auth()->user() ?? auth()->user();
                            $employee = $user?->employee;
                            $sigService = app(\App\Services\Signature\SignatureService::class);
                            if (! $employee || ! $sigService->ensureUserCanSign($user)) {
                                return;
                            }

                            $resolver = new \App\Services\Approval\ApprovalResolver();
                            $approvedCount = 0;
                            $notes = $data['notes'] ?? 'Disetujui massal';

                            foreach ($records as $approvalRequest) {
                                if (! $approvalRequest instanceof ApprovalRequest || $approvalRequest->status !== 'pending') {
                                    continue;
                                }
                                if (! $resolver->canApprove($approvalRequest, $employee)) {
                                    continue;
                                }

                                $currentStep = $approvalRequest->currentStep();
                                $resolverClass = $currentStep?->policyStep?->resolver_class;
                                $roleType = $resolverClass === \App\Services\Approval\Resolvers\ManagerAuthorityResolver::class
                                    ? 'manager'
                                    : ($currentStep && (int) $currentStep->policyStep?->step_order === 1 ? 'supervisor' : 'manager');

                                $resolver->approve($approvalRequest, $employee, $notes);

                                $sppd = $approvalRequest->document;
                                if ($sppd instanceof Sppd) {
                                    $sigService->signDocument($sppd, $employee, $roleType, $notes, $user);
                                }
                                $approvedCount++;
                            }

                            \Filament\Notifications\Notification::make()
                                ->title('Persetujuan Massal SPPD Selesai')
                                ->body("{$approvedCount} pengajuan SPPD berhasil disetujui dan ditandatangani.")
                                ->success()
                                ->send();
                        }),
                ]),
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
