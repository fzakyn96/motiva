<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\SppdCostApprovalResource\Pages;
use App\Filament\Admin\Support\RecapTableSupport;
use App\Models\ApprovalRequest;
use App\Models\SppdCost;
use App\Services\Approval\ApprovalResolver;
use App\Services\Signature\SignatureService;
use Filament\Forms\Components\Textarea;
use Filament\Infolists;
use Filament\Infolists\Infolist;
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
    protected static ?string $navigationLabel = 'Biaya SPPD';
    protected static ?string $slug = 'sppd-cost-approvals';
    protected static ?string $modelLabel = 'Persetujuan Biaya SPPD';
    protected static ?string $pluralModelLabel = 'Persetujuan Biaya SPPD';
    protected static ?int $navigationSort = 3;

    public static function canViewAny(): bool
    {
        $user = \Filament\Facades\Filament::auth()->user() ?? auth()->user();
        if (! $user) {
            return false;
        }
        if ($user->employee?->isBusinessDivisionHead() && ! $user->hasAnyRole(['Superadmin', 'Admin'])) {
            return false;
        }

        return $user->hasAnyRole(['Superadmin', 'Admin', 'Manager']);
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
            ->where('document_type', SppdCost::class)
            ->whereIn('document_id', SppdCost::select('id'))
            ->with(['document.sppd.employee.position', 'document.sppd.businessTrip', 'steps.policyStep'])
            ->latest('id');

        $user = \Filament\Facades\Filament::auth()->user() ?? auth()->user();
        if (! $user || $user->hasAnyRole(['Superadmin', 'Admin'])) {
            return $query;
        }

        $employee = $user->employee;
        if ($employee && $employee->work_area_id) {
            $query->whereHasMorph('document', [SppdCost::class], function (Builder $costQuery) use ($employee) {
                $costQuery->whereHas('sppd.employee', function (Builder $empQuery) use ($employee) {
                    $empQuery->where('work_area_id', $employee->work_area_id);
                });
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
                    Tables\Columns\TextColumn::make('sppd_number')
                        ->label('Nomor SPPD')
                        ->state(fn (ApprovalRequest $record): string => $record->document?->sppd?->sppd_number ?: 'SPPD #' . ($record->document?->sppd_id ?? '-'))
                        ->weight('bold'),
                    Tables\Columns\TextColumn::make('employee_name')
                        ->label('Pegawai')
                        ->state(fn (ApprovalRequest $record): string => $record->document?->sppd?->employee?->name ?? '-'),
                    Tables\Columns\TextColumn::make('total_cost')
                        ->label('Total Biaya')
                        ->state(fn (ApprovalRequest $record): string => 'Rp ' . number_format((float) ($record->document?->grand_total ?: $record->document?->total_cost ?: 0), 0, ',', '.'))
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
                Tables\Actions\Action::make('review')
                    ->label('Proses')
                    ->icon('heroicon-o-play-circle')
                    ->color('warning')
                    ->button()
                    ->size('sm')
                    ->url(fn (ApprovalRequest $record): string => static::getUrl('view', ['record' => $record]))
                    ->visible(function (ApprovalRequest $record): bool {
                        $employee = (\Filament\Facades\Filament::auth()->user() ?? auth()->user())?->employee;

                        return $employee && $record->status === 'pending' && (new ApprovalResolver())->canApprove($record, $employee);
                    }),

                Tables\Actions\Action::make('view_detail')
                    ->label('Lihat')
                    ->icon('heroicon-o-eye')
                    ->color('primary')
                    ->button()
                    ->size('sm')
                    ->url(fn (ApprovalRequest $record): string => static::getUrl('view', ['record' => $record]))
                    ->visible(function (ApprovalRequest $record): bool {
                        $employee = (\Filament\Facades\Filament::auth()->user() ?? auth()->user())?->employee;

                        return ! ($employee && $record->status === 'pending' && (new ApprovalResolver())->canApprove($record, $employee));
                    }),

                Tables\Actions\Action::make('print')
                    ->label('Cetak')
                    ->icon('heroicon-o-printer')
                    ->color('info')
                    ->button()
                    ->size('sm')
                    ->url(fn (ApprovalRequest $record): string => route('sppd-costs.print', $record->document_id))
                    ->openUrlInNewTab(),
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

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Infolists\Components\Section::make('Ringkasan Pengajuan Biaya SPPD')
                    ->icon('heroicon-o-calculator')
                    ->iconColor('primary')
                    ->schema([
                        Infolists\Components\TextEntry::make('employee_name')
                            ->label('Pegawai yang Ditugaskan')
                            ->weight('bold')
                            ->state(function (ApprovalRequest $record): string {
                                $emp = $record->document?->sppd?->employee;
                                if (! $emp) {
                                    return '-';
                                }
                                $pos = $emp->position?->name;

                                return $pos ? "{$emp->name} ({$pos})" : $emp->name;
                            }),
                        Infolists\Components\TextEntry::make('memo_agenda')
                            ->label('Agenda / Penugasan')
                            ->weight('bold')
                            ->state(function (ApprovalRequest $record): string {
                                $sppd = $record->document?->sppd;
                                $memo = $sppd?->businessTrip?->memo;

                                return $memo?->agenda ?: ($sppd?->assignment_purpose ?: ($sppd?->businessTrip?->activity_name ?? '-'));
                            }),
                        Infolists\Components\TextEntry::make('memo_number')
                            ->label('Nomor Memo')
                            ->badge()
                            ->color('info')
                            ->state(fn (ApprovalRequest $record): string => $record->document?->sppd?->businessTrip?->memo?->nomor_memo ?? '-'),
                        Infolists\Components\TextEntry::make('destination')
                            ->label('Tujuan & Durasi')
                            ->state(function (ApprovalRequest $record): string {
                                $sppd = $record->document?->sppd;
                                if (! $sppd) {
                                    return '-';
                                }
                                $dest = $sppd->destination_location ?: ($sppd->businessTrip?->destination ?? '-');
                                $days = (int) ($record->document?->official_days ?? $sppd->total_days ?? 1);

                                return "{$dest} ({$days} Hari)";
                            }),
                        Infolists\Components\TextEntry::make('combined_total')
                            ->label('Total Biaya')
                            ->badge()
                            ->color('success')
                            ->state(fn (ApprovalRequest $record): string => 'Rp ' . number_format((float) ($record->document?->grand_total ?: $record->document?->total_cost ?: 0), 0, ',', '.')),
                        Infolists\Components\TextEntry::make('status')
                            ->label('Status')
                            ->badge()
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
                    ])
                    ->columns(['default' => 1, 'sm' => 3]),

                Infolists\Components\Section::make('Rincian Biaya Perjalanan & Evidence')
                    ->icon('heroicon-o-list-bullet')
                    ->iconColor('info')
                    ->schema([
                        Infolists\Components\TextEntry::make('sppd_number')
                            ->label('Nomor SPPD')
                            ->badge()
                            ->color('info')
                            ->state(fn (ApprovalRequest $record): string => $record->document?->sppd?->sppd_number ?? ('SPPD #' . ($record->document?->sppd_id ?? '-'))),
                        Infolists\Components\TextEntry::make('subtotal_transport')
                            ->label('Biaya Transport')
                            ->weight('bold')
                            ->state(fn (ApprovalRequest $record): string => 'Rp ' . number_format((float) ($record->document?->subtotal_transport ?? 0), 0, ',', '.')),
                        Infolists\Components\TextEntry::make('subtotal_per_diem')
                            ->label('Uang Harian / Hotel')
                            ->weight('bold')
                            ->state(fn (ApprovalRequest $record): string => 'Rp ' . number_format((float) ($record->document?->subtotal_per_diem ?? 0), 0, ',', '.')),
                        Infolists\Components\TextEntry::make('subtotal_other')
                            ->label('Biaya Lainnya')
                            ->weight('bold')
                            ->state(fn (ApprovalRequest $record): string => 'Rp ' . number_format((float) ($record->document?->subtotal_other ?? 0), 0, ',', '.')),
                        Infolists\Components\TextEntry::make('grand_total_detail')
                            ->label('Total Keseluruhan')
                            ->badge()
                            ->color('success')
                            ->state(fn (ApprovalRequest $record): string => 'Rp ' . number_format((float) ($record->document?->grand_total ?: $record->document?->total_cost ?: 0), 0, ',', '.')),
                        Infolists\Components\TextEntry::make('evidence_links')
                            ->label('Evidence / Lampiran')
                            ->html()
                            ->state(function (ApprovalRequest $record): string {
                                $c = $record->document;
                                $evidenceLinks = collect([
                                    'Evidence Transport' => $c?->transport_evidence,
                                    'Evidence Harian' => $c?->per_diem_evidence,
                                    'Evidence Lainnya' => $c?->other_evidence,
                                    'Evidence Umum' => $c?->general_evidence,
                                ])->flatMap(function ($files, $label) {
                                    if (empty($files)) {
                                        return [];
                                    }
                                    $items = is_array($files) ? $files : [$files];

                                    return collect($items)->filter()->values()->map(function ($path, $idx) use ($label) {
                                        $url = e(\Illuminate\Support\Facades\Storage::url($path));
                                        $suffix = $idx > 0 ? ' #' . ($idx + 1) : '';

                                        return '<a href="' . $url . '" target="_blank" style="display:inline-flex;align-items:center;gap:4px;padding:3px 8px;border-radius:6px;background:rgba(14,165,233,0.12);color:#0284c7;font-size:12px;font-weight:600;text-decoration:none;">'
                                            . e($label . $suffix) . ' &nearr;</a>';
                                    });
                                })->implode(' ');

                                return $evidenceLinks !== ''
                                    ? '<div style="display:flex;flex-wrap:wrap;gap:6px;">' . $evidenceLinks . '</div>'
                                    : '<span style="font-size:13px;color:#9ca3af;">Tidak ada file evidence terlampir.</span>';
                            }),
                    ])
                    ->columns(['default' => 1, 'sm' => 3]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSppdCostApprovals::route('/'),
            'view' => Pages\ViewSppdCostApproval::route('/{record}'),
        ];
    }
}
