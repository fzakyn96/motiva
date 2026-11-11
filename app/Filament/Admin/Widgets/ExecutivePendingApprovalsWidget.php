<?php

namespace App\Filament\Admin\Widgets;

use App\Filament\Admin\Resources\MemoResource;
use App\Filament\Admin\Resources\ReimbursementResource;
use App\Filament\Admin\Resources\SppdResource;
use App\Models\ApprovalRequest;
use App\Models\Memo;
use App\Models\Reimbursement;
use App\Models\Sppd;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Database\Eloquent\Builder;

class ExecutivePendingApprovalsWidget extends BaseWidget
{
    protected static ?string $heading = 'Antrian Persetujuan';
    protected static ?int $sort = 4;

    protected int | string | array $columnSpan = 'full';

    public static function canView(): bool
    {
        $user = \Filament\Facades\Filament::auth()->user() ?? auth()->user();
        if (! $user) return false;
        return $user->hasAnyRole(['Manager', 'Supervisor', 'Superadmin', 'Admin', 'HR']);
    }

    public function table(Table $table): Table
    {
        $user = \Filament\Facades\Filament::auth()->user() ?? auth()->user();
        $employee = $user?->employee;
        $isAllAccess = ! $user || $user->hasRole(['Superadmin', 'Admin', 'HR']);

        $query = ApprovalRequest::query()
            ->where('status', 'pending')
            ->latest('id');

        if (! $isAllAccess && $employee) {
            $isSupervisorOnly = $user?->hasRole('Supervisor') && ! $user->hasAnyRole(['Manager', 'Superadmin', 'Admin', 'HR']);

            if ($isSupervisorOnly) {
                // Supervisor only approves SPPD
                $query->where('document_type', Sppd::class)
                    ->whereHasMorph('document', [Sppd::class], function (Builder $sq) use ($employee) {
                        $sq->whereHas('employee', function ($eq) use ($employee) {
                            $eq->where('department_id', $employee->department_id)
                                ->orWhere('manager_id', $employee->id)
                                ->orWhere('id', $employee->id);
                        });
                    });
            } else {
                $query->where(function (Builder $q) use ($employee) {
                    // 1. Memo
                    $q->where(function (Builder $sub) use ($employee) {
                        $sub->where('document_type', Memo::class)
                            ->whereHasMorph('document', [Memo::class], function (Builder $mq) use ($employee) {
                                $mq->where('department_id', $employee->department_id)
                                    ->orWhere('creator_employee_id', $employee->id)
                                    ->orWhereHas('creator', fn ($cq) => $cq->where('manager_id', $employee->id));
                            });
                    })
                    // 2. SPPD
                    ->orWhere(function (Builder $sub) use ($employee) {
                        $sub->where('document_type', Sppd::class)
                            ->whereHasMorph('document', [Sppd::class], function (Builder $sq) use ($employee) {
                                $sq->whereHas('employee', function ($eq) use ($employee) {
                                    $eq->where('department_id', $employee->department_id)
                                        ->orWhere('manager_id', $employee->id)
                                        ->orWhere('id', $employee->id);
                                });
                            });
                    })
                    // 3. Reimbursement
                    ->orWhere(function (Builder $sub) use ($employee) {
                        $sub->where('document_type', Reimbursement::class)
                            ->whereHasMorph('document', [Reimbursement::class], function (Builder $rq) use ($employee) {
                                $rq->whereHas('employee', function ($eq) use ($employee) {
                                    $eq->where('department_id', $employee->department_id)
                                        ->orWhere('manager_id', $employee->id)
                                        ->orWhere('id', $employee->id);
                                });
                            });
                    });
                });
            }
        }

        return $table
            ->query($query)
            ->columns([
                Tables\Columns\TextColumn::make('document_type')
                    ->label('Dokumen')
                    ->badge()
                    ->formatStateUsing(function (string $state): string {
                        return match ($state) {
                            Memo::class => 'MEMO',
                            Sppd::class => 'SPPD',
                            Reimbursement::class => 'REIMBURSEMENT',
                            default => class_basename($state),
                        };
                    })
                    ->color(function (string $state): string {
                        return match ($state) {
                            Memo::class => 'primary',
                            Sppd::class => 'warning',
                            Reimbursement::class => 'success',
                            default => 'gray',
                        };
                    }),

                Tables\Columns\TextColumn::make('document_info')
                    ->label('Nomor & Keperluan')
                    ->state(function (ApprovalRequest $record) {
                        $doc = $record->document;
                        if (! $doc) return '-';

                        if ($doc instanceof Memo) {
                            return ($doc->nomor_memo ?: 'Memo Draf') . ' • ' . ($doc->title ?: $doc->agenda);
                        }

                        if ($doc instanceof Sppd) {
                            $dest = $doc->businessTrip?->destination ?? ($doc->destination_location ?? '-');
                            return ($doc->sppd_number ?: 'SPPD') . ' • Ke ' . $dest;
                        }

                        if ($doc instanceof Reimbursement) {
                            return ($doc->reimbursement_number ?: 'Reimbursement') . ' • ' . ($doc->title ?: 'Klaim Biaya');
                        }

                        return '-';
                    })
                    ->weight('bold')
                    ->wrap(),

                Tables\Columns\TextColumn::make('requester.name')
                    ->label('Pengaju')
                    ->formatStateUsing(function (ApprovalRequest $record) {
                        $req = $record->requester;
                        if (! $req) return '-';
                        $pos = $req->position?->name;
                        $dept = $req->department?->code ?? $req->department?->name;
                        $extra = array_filter([$pos, $dept]);
                        return ! empty($extra) ? "{$req->name} (" . implode(', ', $extra) . ")" : $req->name;
                    }),

                Tables\Columns\TextColumn::make('current_step')
                    ->label('Tahap Verifikasi')
                    ->badge()
                    ->formatStateUsing(function (ApprovalRequest $record): string {
                        if ($record->document_type === Sppd::class) {
                            return ((int) $record->current_step <= 1)
                                ? 'Tahap 1 • Supervisor'
                                : 'Tahap 2 • Manajer';
                        }

                        return 'Otorisasi Manajer';
                    })
                    ->color(function (ApprovalRequest $record): string {
                        if ($record->document_type === Sppd::class && (int) $record->current_step <= 1) {
                            return 'info';
                        }

                        return 'warning';
                    })
                    ->icon('heroicon-m-shield-check'),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Diajukan')
                    ->dateTime('d/m/Y H:i')
                    ->description(fn (ApprovalRequest $record) => $record->created_at->diffForHumans()),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('document_type')
                    ->label('Jenis Dokumen')
                    ->options([
                        Memo::class => 'Memo Dinas',
                        Sppd::class => 'SPPD',
                        Reimbursement::class => 'Reimbursement',
                    ]),
            ])
            ->actions([
                Tables\Actions\Action::make('review')
                    ->label('Proses')
                    ->icon('heroicon-o-play-circle')
                    ->button()
                    ->size('sm')
                    ->color('warning')
                    ->url(function (ApprovalRequest $record) {
                        $doc = $record->document;
                        if ($doc instanceof Memo) {
                            return MemoResource::getUrl('review', ['record' => $doc->id, 'from' => 'approvals']);
                        }
                        if ($doc instanceof Sppd) {
                            return SppdResource::getUrl('review', ['record' => $doc->id, 'from' => 'approvals']);
                        }
                        if ($doc instanceof Reimbursement) {
                            return ReimbursementResource::getUrl('review', ['record' => $doc->id, 'from' => 'approvals']);
                        }
                        return '#';
                    }),
            ])
            ->emptyStateHeading('Semua Beres! Tidak Ada Antrian Persetujuan')
            ->emptyStateDescription('Seluruh pengajuan memo dinas, SPPD, dan klaim reimbursement di bawah wewenang Anda telah selesai ditinjau.')
            ->emptyStateIcon('heroicon-o-check-badge')
            ->paginated([5, 10]);
    }
}
