<?php

namespace App\Filament\Admin\Widgets;

use App\Filament\Admin\Resources\MemoResource;
use App\Filament\Admin\Resources\OfficialTravelRecapResource;
use App\Filament\Admin\Resources\SppdCostApprovalResource;
use App\Filament\Admin\Resources\SppdResource;
use App\Filament\Admin\Resources\TransportAccommodationRecapResource;
use App\Models\ApprovalRequest;
use App\Models\Memo;
use App\Models\OfficialTravelRecap;
use App\Models\Sppd;
use App\Models\SppdCost;
use App\Models\TransportAccommodationRecap;
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
        return $user->hasAnyRole(['Division Head', 'Manager', 'Supervisor', 'Superadmin', 'Admin', 'HR']);
    }

    public function table(Table $table): Table
    {
        $user = \Filament\Facades\Filament::auth()->user() ?? auth()->user();
        $employee = $user?->employee;
        $isBusinessDivisionHead = (bool) $employee?->isBusinessDivisionHead();
        $isAllAccess = ! $user || $user->hasRole(['Superadmin', 'Admin', 'HR']);
        $isDivisionHeadOnly = (($user?->hasRole('Division Head') ?? false) || $isBusinessDivisionHead) && ! $user?->hasAnyRole(['Superadmin', 'Admin', 'HR']);

        $query = ApprovalRequest::query()
            ->with(['document', 'requester.position', 'requester.workArea', 'requester.department', 'steps.policyStep'])
            ->where('status', 'pending')
            ->latest('id');

        if ($isDivisionHeadOnly) {
            $query->whereIn('document_type', [TransportAccommodationRecap::class, OfficialTravelRecap::class]);
        } elseif (! $isAllAccess && $employee) {
            $isSupervisorOnly = $user?->hasRole('Supervisor') && ! $user->hasAnyRole(['Manager', 'Division Head', 'Superadmin', 'Admin', 'HR']);

            if ($isSupervisorOnly) {
                // Supervisor only approves SPPD at Step 1 (Supervisor step still pending)
                $query->where('document_type', Sppd::class);
                \App\Filament\Admin\Resources\SppdApprovalResource::applyActionablePendingScope($query, $user);
                $query->whereHasMorph('document', [Sppd::class], function (Builder $sq) use ($employee) {
                    $sq->whereHas('employee', function ($eq) use ($employee) {
                        if ($employee->work_area_id) {
                            $eq->where('work_area_id', $employee->work_area_id);
                        } else {
                            $eq->where('department_id', $employee->department_id)
                                ->orWhere('manager_id', $employee->id)
                                ->orWhere('id', $employee->id);
                        }
                    });
                });
            } else {
                $query->where(function (Builder $q) use ($employee) {
                    // 1. Memo
                    $q->where(function (Builder $sub) use ($employee) {
                        $sub->where('document_type', Memo::class)
                            ->whereHasMorph('document', [Memo::class], function (Builder $mq) use ($employee) {
                                if ($employee->work_area_id) {
                                    $mq->whereHas('creator', fn ($cq) => $cq->where('work_area_id', $employee->work_area_id));
                                } else {
                                    $mq->where('department_id', $employee->department_id)
                                        ->orWhere('creator_employee_id', $employee->id)
                                        ->orWhereHas('creator', fn ($cq) => $cq->where('manager_id', $employee->id));
                                }
                            });
                    })
                    // 2. SPPD
                    ->orWhere(function (Builder $sub) use ($employee) {
                        $sub->where('document_type', Sppd::class)
                            ->whereHasMorph('document', [Sppd::class], function (Builder $sq) use ($employee) {
                                $sq->whereHas('employee', function ($eq) use ($employee) {
                                    if ($employee->work_area_id) {
                                        $eq->where('work_area_id', $employee->work_area_id);
                                    } else {
                                        $eq->where('department_id', $employee->department_id)
                                            ->orWhere('manager_id', $employee->id)
                                            ->orWhere('id', $employee->id);
                                    }
                                });
                            });
                    })
                    // 3. Biaya SPPD
                    ->orWhere(function (Builder $sub) use ($employee) {
                        $sub->where('document_type', SppdCost::class)
                            ->whereHasMorph('document', [SppdCost::class], function (Builder $cq) use ($employee) {
                                $cq->whereHas('sppd.employee', function ($eq) use ($employee) {
                                    if ($employee->work_area_id) {
                                        $eq->where('work_area_id', $employee->work_area_id);
                                    } else {
                                        $eq->where('department_id', $employee->department_id)
                                            ->orWhere('manager_id', $employee->id)
                                            ->orWhere('id', $employee->id);
                                    }
                                });
                            });
                    })
                    // 4. Rekap Tiket & Akomodasi
                    ->orWhere(function (Builder $sub) use ($employee) {
                        $sub->where('document_type', TransportAccommodationRecap::class)
                            ->when($employee->work_area_id, function (Builder $q2) use ($employee) {
                                $q2->where(function (Builder $wq) use ($employee) {
                                    $wq->whereHas('requester', fn (Builder $rq) => $rq->where('work_area_id', $employee->work_area_id))
                                        ->orWhereHasMorph('document', [TransportAccommodationRecap::class], fn (Builder $dq) => $dq->whereHas('businessTrip.sppds.employee', fn (Builder $eq) => $eq->where('work_area_id', $employee->work_area_id)));
                                });
                            }, fn (Builder $q2) => $q2->when($employee->department_id, fn (Builder $q3) => $q3->whereHasMorph('document', [TransportAccommodationRecap::class], fn (Builder $rq) => $rq->where('department_id', $employee->department_id))));
                    })
                    // 5. Rekap Perjalanan Dinas
                    ->orWhere(function (Builder $sub) use ($employee) {
                        $sub->where('document_type', OfficialTravelRecap::class)
                            ->when($employee->work_area_id, function (Builder $q2) use ($employee) {
                                $q2->where(function (Builder $wq) use ($employee) {
                                    $wq->whereHas('requester', fn (Builder $rq) => $rq->where('work_area_id', $employee->work_area_id))
                                        ->orWhereHasMorph('document', [OfficialTravelRecap::class], fn (Builder $dq) => $dq->whereHas('businessTrip.sppds.employee', fn (Builder $eq) => $eq->where('work_area_id', $employee->work_area_id)));
                                });
                            }, fn (Builder $q2) => $q2->when($employee->department_id, fn (Builder $q3) => $q3->whereHasMorph('document', [OfficialTravelRecap::class], fn (Builder $rq) => $rq->where('department_id', $employee->department_id))));
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
                            SppdCost::class => 'BIAYA SPPD',
                            TransportAccommodationRecap::class => 'REKAP TIKET',
                            OfficialTravelRecap::class => 'REKAP DINAS',
                            default => class_basename($state),
                        };
                    })
                    ->color(function (string $state): string {
                        return match ($state) {
                            Memo::class => 'primary',
                            Sppd::class => 'warning',
                            SppdCost::class => 'danger',
                            TransportAccommodationRecap::class => 'success',
                            OfficialTravelRecap::class => 'info',
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

                        if ($doc instanceof SppdCost) {
                            $sppd = $doc->sppd;
                            $empName = $sppd?->employee?->name ?? '-';
                            $total = 'Rp ' . number_format((float) $doc->total_cost, 0, ',', '.');
                            return ($sppd?->sppd_number ?: 'Biaya SPPD') . " • {$empName} ({$total})";
                        }

                        if ($doc instanceof TransportAccommodationRecap) {
                            return ($doc->period_label ?: 'Rekap Tiket') . ' • ' . ($doc->work_name ?: 'Rekap Tiket & Akomodasi');
                        }

                        if ($doc instanceof OfficialTravelRecap) {
                            return ($doc->period_label ?: 'Rekap Dinas') . ' • ' . ($doc->work_name ?: 'Rekap Perjalanan Dinas');
                        }

                        return '-';
                    })
                    ->weight('bold')
                    ->wrap(),

                Tables\Columns\TextColumn::make('requester.name')
                    ->label('Pengaju & Area Kerja')
                    ->formatStateUsing(function (ApprovalRequest $record) {
                        $req = $record->requester;
                        if (! $req) return '-';
                        $area = $req->workArea?->name ? ('Area ' . $req->workArea->name) : ($req->department?->code ?? $req->department?->name);
                        $pos = $req->position?->name;
                        $extra = array_filter([$pos, $area]);
                        return ! empty($extra) ? "{$req->name} (" . implode(' • ', $extra) . ")" : $req->name;
                    }),

                Tables\Columns\TextColumn::make('current_step')
                    ->label('Tahap Verifikasi')
                    ->badge()
                    ->state(function (ApprovalRequest $record): string {
                        $resolverClass = $record->currentStep()?->policyStep?->resolver_class;

                        if ($resolverClass === \App\Services\Approval\Resolvers\SupervisorAuthorityResolver::class) {
                            return 'Tahap 1 • Supervisor';
                        }
                        if ($resolverClass === \App\Services\Approval\Resolvers\BusinessDivisionHeadAuthorityResolver::class) {
                            return 'Tahap 2 • Division Head Perkasa';
                        }
                        if ($resolverClass === \App\Services\Approval\Resolvers\DivisionHeadAuthorityResolver::class) {
                            return 'Tahap 3 • Division Head PGN';
                        }
                        if (in_array($record->document_type, [TransportAccommodationRecap::class, OfficialTravelRecap::class], true)) {
                            return 'Tahap 1 • Pemeriksaan Manajer';
                        }
                        if ($record->document_type === Sppd::class) {
                            return 'Tahap 2 • Manajer';
                        }

                        return 'Otorisasi Manajer';
                    })
                    ->color(function (ApprovalRequest $record): string {
                        $resolverClass = $record->currentStep()?->policyStep?->resolver_class;
                        if ($resolverClass === \App\Services\Approval\Resolvers\SupervisorAuthorityResolver::class) {
                            return 'info';
                        }
                        if ($resolverClass === \App\Services\Approval\Resolvers\DivisionHeadAuthorityResolver::class) {
                            return 'success';
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
                        SppdCost::class => 'Biaya SPPD',
                        TransportAccommodationRecap::class => 'Rekap Tiket & Akomodasi',
                        OfficialTravelRecap::class => 'Rekap Perjalanan Dinas',
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
                        if ($doc instanceof SppdCost) {
                            return SppdCostApprovalResource::getUrl('index');
                        }
                        if ($doc instanceof TransportAccommodationRecap) {
                            return TransportAccommodationRecapResource::getUrl('view', ['record' => $doc->id]);
                        }
                        if ($doc instanceof OfficialTravelRecap) {
                            return OfficialTravelRecapResource::getUrl('view', ['record' => $doc->id]);
                        }
                        return '#';
                    }),
            ])
            ->emptyStateHeading('Semua Beres! Tidak Ada Antrian Persetujuan')
            ->emptyStateDescription('Seluruh pengajuan memo dinas, SPPD, dan rekap perjalanan dinas di bawah wewenang Anda telah selesai ditinjau.')
            ->emptyStateIcon('heroicon-o-check-badge')
            ->paginated([5, 10]);
    }
}
