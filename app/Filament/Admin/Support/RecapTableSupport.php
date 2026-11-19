<?php

namespace App\Filament\Admin\Support;

use App\Models\ApprovalRequest;
use App\Models\BusinessTrip;
use App\Models\OfficialTravelRecap;
use App\Models\TransportAccommodationRecap;
use App\Services\Approval\ApprovalResolver;
use App\Services\Approval\Resolvers\BusinessDivisionHeadAuthorityResolver;
use App\Services\Approval\Resolvers\DivisionHeadAuthorityResolver;
use App\Services\Signature\SignatureService;
use Filament\Actions;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class RecapTableSupport
{
    public static function latestApproval(Model $record): ?ApprovalRequest
    {
        return $record->approvalRequests()->with(['steps.policyStep'])->latest('id')->first();
    }

    public static function statusColumn(): Tables\Columns\TextColumn
    {
        return Tables\Columns\TextColumn::make('recap_approval_status')
            ->label('Status Persetujuan')
            ->badge()
            ->state(function (Model $record): string {
                $req = static::latestApproval($record);
                if (! $req) {
                    return 'Belum Diajukan';
                }

                if ($req->status === 'approved') {
                    return 'Disetujui';
                }

                if ($req->status === 'rejected') {
                    return 'Ditolak';
                }

                $current = $req->currentStep();
                if ($current?->policyStep?->resolver_class === BusinessDivisionHeadAuthorityResolver::class) {
                    return 'Menunggu Division Head Perkasa';
                }
                if ($current?->policyStep?->resolver_class === DivisionHeadAuthorityResolver::class) {
                    return 'Menunggu Division Head';
                }

                return 'Menunggu Pemeriksaan Manajer';
            })
            ->color(fn (string $state): string => match ($state) {
                'Disetujui' => 'success',
                'Ditolak' => 'danger',
                'Menunggu Division Head Perkasa', 'Menunggu Pemeriksaan Manajer', 'Menunggu Division Head' => 'warning',
                default => 'gray',
            });
    }

    /**
     * @return array<int, Tables\Actions\Action>
     */
    public static function actions(string $printRouteName, bool $includeCrud = true): array
    {
        $actions = [
            Tables\Actions\Action::make('submit_recap')
                ->label('Ajukan')
                ->icon('heroicon-o-paper-airplane')
                ->color('primary')
                ->button()
                ->size('sm')
                ->requiresConfirmation()
                ->modalHeading('Ajukan Persetujuan Rekap')
                ->modalDescription('Rekap akan diajukan untuk diperiksa terlebih dahulu oleh Manajer, kemudian disetujui oleh Division Head Perkasa dan Division Head PGN.')
                ->visible(function (Model $record): bool {
                    $user = \Filament\Facades\Filament::auth()->user() ?? auth()->user();
                    if (! $user || ! $user->hasAnyRole(['Superadmin', 'Admin', 'HR'])) {
                        return false;
                    }

                    return ! $record->approvalRequests()->exists();
                })
                ->action(function (Model $record): void {
                    $user = \Filament\Facades\Filament::auth()->user() ?? auth()->user();
                    $req = method_exists($record, 'ensureApprovalRequestExists')
                        ? $record->ensureApprovalRequestExists($user?->employee)
                        : $record->ensureRecapApprovalRequestExists($user?->employee);

                    if ($user?->employee && $user->hasConfiguredSignature()) {
                        $trip = $record instanceof BusinessTrip ? $record : $record->businessTrip;
                        $preparedBy = $trip?->resolveRecapSignatories()['prepared_by'] ?? null;
                        if ($preparedBy && (int) $preparedBy->id === (int) $user->employee->id) {
                            app(SignatureService::class)->signDocument(
                                $record,
                                $user->employee,
                                'requester',
                                'Menyusun rekapitulasi biaya reimbursement',
                                $user
                            );
                        }
                    }

                    Notification::make()
                        ->title('Rekap Diajukan')
                        ->body("Pengajuan review rekap (#{$req->id}) berhasil dibuat.")
                        ->success()
                        ->send();
                }),

            Tables\Actions\ViewAction::make()
                ->label(function (Model $record): string {
                    $user = \Filament\Facades\Filament::auth()->user() ?? auth()->user();
                    $employee = $user?->employee;
                    $req = static::latestApproval($record);
                    if ($employee && $req && $req->status === 'pending' && (new ApprovalResolver())->canApprove($req, $employee)) {
                        return 'Review';
                    }

                    return 'Lihat';
                })
                ->icon('heroicon-o-eye')
                ->color(function (Model $record): string {
                    $user = \Filament\Facades\Filament::auth()->user() ?? auth()->user();
                    $employee = $user?->employee;
                    $req = static::latestApproval($record);

                    return ($employee && $req && $req->status === 'pending' && (new ApprovalResolver())->canApprove($req, $employee))
                        ? 'warning'
                        : 'gray';
                })
                ->button()
                ->size('sm'),

            Tables\Actions\Action::make('print')
                ->label('Cetak')
                ->icon('heroicon-o-printer')
                ->color('info')
                ->button()
                ->size('sm')
                ->url(fn (Model $record) => route($printRouteName, $record))
                ->openUrlInNewTab(),
        ];

        if ($includeCrud) {
            $actions[] = Tables\Actions\EditAction::make()
                ->label('Ubah')
                ->icon('heroicon-o-pencil-square')
                ->color('warning')
                ->button()
                ->size('sm')
                ->visible(fn (Model $record): bool => ($record instanceof TransportAccommodationRecap || $record instanceof OfficialTravelRecap)
                    && (static::latestApproval($record)?->status !== 'approved' || (auth()->user()?->hasAnyRole(['Superadmin', 'Admin']) ?? false))
                    && (auth()->user()?->hasAnyRole(['Superadmin', 'Admin', 'HR']) ?? false));

            $actions[] = Tables\Actions\DeleteAction::make()
                ->label('Hapus')
                ->icon('heroicon-o-trash')
                ->color('danger')
                ->button()
                ->size('sm')
                ->visible(fn (Model $record): bool => ($record instanceof TransportAccommodationRecap || $record instanceof OfficialTravelRecap)
                    && (static::latestApproval($record)?->status !== 'approved' || (auth()->user()?->hasAnyRole(['Superadmin', 'Admin']) ?? false))
                    && (auth()->user()?->hasAnyRole(['Superadmin', 'Admin', 'HR']) ?? false));
        }

        return $actions;
    }

    /**
     * @return array<int, Actions\Action>
     */
    public static function viewHeaderActions(Model $record, string $indexUrl, string $printRouteName): array
    {
        return [
            Actions\Action::make('back')
                ->label('Kembali')
                ->icon('heroicon-o-arrow-left')
                ->color('gray')
                ->url($indexUrl),

            Actions\Action::make('approve_recap')
                ->label(function () use ($record): string {
                    $req = static::latestApproval($record);
                    $step = $req?->currentStep();

                    return in_array($step?->policyStep?->resolver_class, [
                        BusinessDivisionHeadAuthorityResolver::class,
                        DivisionHeadAuthorityResolver::class,
                    ], true)
                        ? 'Setujui & Tandatangani'
                        : 'Periksa & Tandatangani';
                })
                ->icon('heroicon-o-check-badge')
                ->color('success')
                ->form([
                    Textarea::make('notes')
                        ->label('Catatan Persetujuan (Opsional)')
                        ->rows(2),
                ])
                ->modalHeading('Konfirmasi Persetujuan & Tanda Tangan Rekap')
                ->modalSubmitActionLabel('Ya, Setujui & Tandatangani')
                ->visible(function () use ($record): bool {
                    $user = \Filament\Facades\Filament::auth()->user() ?? auth()->user();
                    $employee = $user?->employee;
                    if (! $employee) {
                        return false;
                    }

                    $req = static::latestApproval($record);
                    if (! $req || $req->status !== 'pending') {
                        return false;
                    }

                    return (new ApprovalResolver())->canApprove($req, $employee);
                })
                ->action(function (array $data) use ($record): void {
                    $user = \Filament\Facades\Filament::auth()->user() ?? auth()->user();
                    $employee = $user?->employee;
                    if (! $employee || ! app(SignatureService::class)->ensureUserCanSign($user)) {
                        return;
                    }

                    $req = static::latestApproval($record);
                    if (! $req) {
                        return;
                    }

                    (new ApprovalResolver())->approve($req, $employee, $data['notes'] ?? null);

                    Notification::make()
                        ->title('Rekap Disetujui & Ditandatangani')
                        ->body('Persetujuan dan tanda tangan digital Anda telah tercatat.')
                        ->success()
                        ->send();
                }),

            Actions\Action::make('reject_recap')
                ->label('Tolak')
                ->icon('heroicon-o-x-circle')
                ->color('danger')
                ->form([
                    Textarea::make('notes')
                        ->label('Alasan Penolakan')
                        ->required()
                        ->rows(2),
                ])
                ->modalHeading('Tolak Rekap Reimbursement')
                ->modalSubmitActionLabel('Tolak Rekap')
                ->visible(function () use ($record): bool {
                    $user = \Filament\Facades\Filament::auth()->user() ?? auth()->user();
                    $employee = $user?->employee;
                    if (! $employee) {
                        return false;
                    }

                    $req = static::latestApproval($record);
                    if (! $req || $req->status !== 'pending') {
                        return false;
                    }

                    return (new ApprovalResolver())->canApprove($req, $employee);
                })
                ->action(function (array $data) use ($record): void {
                    $user = \Filament\Facades\Filament::auth()->user() ?? auth()->user();
                    $employee = $user?->employee;
                    $req = static::latestApproval($record);
                    if (! $employee || ! $req) {
                        return;
                    }

                    (new ApprovalResolver())->reject($req, $employee, $data['notes']);

                    Notification::make()
                        ->title('Rekap Ditolak')
                        ->body('Status rekap telah diperbarui menjadi ditolak.')
                        ->warning()
                        ->send();
                }),

            Actions\Action::make('print')
                ->label('Cetak')
                ->icon('heroicon-o-printer')
                ->color('info')
                ->url(fn () => route($printRouteName, $record))
                ->openUrlInNewTab(),
        ];
    }

    public static function monthYearFilter(string $dateColumn = 'created_at', ?string $textColumn = null): Tables\Filters\Filter
    {
        $months = [
            1 => 'Januari',
            2 => 'Februari',
            3 => 'Maret',
            4 => 'April',
            5 => 'Mei',
            6 => 'Juni',
            7 => 'Juli',
            8 => 'Agustus',
            9 => 'September',
            10 => 'Oktober',
            11 => 'November',
            12 => 'Desember',
        ];

        $currentYear = (int) now()->format('Y');
        $years = [];
        for ($y = $currentYear - 2; $y <= $currentYear + 2; $y++) {
            $years[$y] = (string) $y;
        }

        return Tables\Filters\Filter::make('periode_bulan')
            ->label('Filter Periode Bulan')
            ->form([
                Select::make('month')
                    ->label('Bulan')
                    ->options($months)
                    ->placeholder('Semua Bulan'),
                Select::make('year')
                    ->label('Tahun')
                    ->options($years)
                    ->placeholder('Semua Tahun'),
            ])
            ->columns(['default' => 1, 'sm' => 2])
            ->query(function (Builder $query, array $data) use ($dateColumn, $textColumn, $months): Builder {
                $month = ! empty($data['month']) ? (int) $data['month'] : null;
                $year = ! empty($data['year']) ? (int) $data['year'] : null;

                if (! $month && ! $year) {
                    return $query;
                }

                if ($textColumn) {
                    return $query->where(function (Builder $q) use ($month, $year, $dateColumn, $textColumn, $months): void {
                        if ($month && $year) {
                            $label = $months[$month] . ' ' . $year;
                            $q->where($textColumn, 'like', "%{$label}%")
                                ->orWhere(function (Builder $dq) use ($month, $year, $dateColumn): void {
                                    $dq->whereMonth($dateColumn, $month)->whereYear($dateColumn, $year);
                                });
                        } elseif ($month) {
                            $q->where($textColumn, 'like', "%{$months[$month]}%")
                                ->orWhereMonth($dateColumn, $month);
                        } else {
                            $q->where($textColumn, 'like', "%{$year}%")
                                ->orWhereYear($dateColumn, $year);
                        }
                    });
                }

                return $query
                    ->when($month, fn (Builder $q) => $q->whereMonth($dateColumn, $month))
                    ->when($year, fn (Builder $q) => $q->whereYear($dateColumn, $year));
            })
            ->indicateUsing(function (array $data) use ($months): ?string {
                $parts = [];
                if (! empty($data['month'])) {
                    $parts[] = $months[(int) $data['month']] ?? '';
                }
                if (! empty($data['year'])) {
                    $parts[] = (string) $data['year'];
                }

                return ! empty($parts) ? 'Periode: ' . implode(' ', $parts) : null;
            });
    }

    public static function pendingApprovalBadge(string $documentClass): ?string
    {
        $count = ApprovalRequest::where('document_type', $documentClass)
            ->where('status', 'pending')
            ->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function signatureVerificationInfolistSection(): \Filament\Infolists\Components\Section
    {
        return \Filament\Infolists\Components\Section::make('Tanda Tangan Elektronik & Verifikasi Keaslian')
            ->icon('heroicon-o-shield-check')
            ->iconColor('success')
            ->description('Klik tombol "Verifikasi Tanda Tangan" di bawah (atau klik langsung gambar tanda tangan / QR Code di dalam file Cetak PDF) untuk membuka halaman verifikasi keaslian tanda tangan.')
            ->visible(fn (Model $record): bool => \Illuminate\Support\Facades\Schema::hasTable('document_signatures')
                && \App\Models\DocumentSignature::where('document_type', get_class($record))->where('document_id', $record->getKey())->exists())
            ->schema([
                \Filament\Infolists\Components\TextEntry::make('digital_signatures_list')
                    ->label('')
                    ->html()
                    ->state(function (Model $record): string {
                        $sigs = \App\Models\DocumentSignature::where('document_type', get_class($record))
                            ->where('document_id', $record->getKey())
                            ->orderBy('signed_at')
                            ->get();

                        if ($sigs->isEmpty()) {
                            return '<span class="text-sm text-gray-400">Belum ada tanda tangan elektronik yang tercatat.</span>';
                        }

                        return '<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));gap:12px;">'
                            . $sigs->map(function (\App\Models\DocumentSignature $sig): string {
                                $name = e($sig->signer_name);
                                $pos = e($sig->signer_position ?: $sig->role_label);
                                $role = e($sig->role_label);
                                $time = $sig->signed_at ? e($sig->signed_at->timezone('Asia/Jakarta')->translatedFormat('d M Y, H:i') . ' WIB') : '-';
                                $url = e($sig->verification_url);

                                return '<div style="border:1px solid rgba(16,185,129,0.3);border-radius:10px;padding:12px;background:rgba(16,185,129,0.05);display:flex;flex-direction:column;justify-content:space-between;gap:10px;">'
                                    . '<div>'
                                    . '<div style="font-size:11px;font-weight:700;text-transform:uppercase;color:#10b981;margin-bottom:2px;">✓ ' . $role . '</div>'
                                    . '<div style="font-size:14px;font-weight:700;">' . $name . '</div>'
                                    . '<div style="font-size:12px;color:#6b7280;">' . $pos . '</div>'
                                    . '<div style="font-size:11px;color:#9ca3af;margin-top:4px;">Ditandatangani: ' . $time . '</div>'
                                    . '</div>'
                                    . '<div>'
                                    . '<a href="' . $url . '" target="_blank" style="display:inline-flex;align-items:center;gap:6px;padding:5px 10px;border-radius:6px;background:#059669;color:#ffffff;font-size:12px;font-weight:600;text-decoration:none;">'
                                    . 'Verifikasi Tanda Tangan &nearr;'
                                    . '</a>'
                                    . '</div>'
                                    . '</div>';
                            })->implode('')
                            . '</div>';
                    })
                    ->columnSpanFull(),
            ]);
    }

    public static function approvalTable(Table $table, string $recapResourceClass, string $printRouteName): Table
    {
        return $table
            ->columns([
                Tables\Columns\Layout\Split::make([
                    Tables\Columns\TextColumn::make('document.period_label')
                        ->label('Periode')
                        ->placeholder('-')
                        ->weight('bold')
                        ->searchable(),
                    Tables\Columns\TextColumn::make('document.work_name')
                        ->label('Pekerjaan')
                        ->placeholder('-')
                        ->limit(50)
                        ->searchable(),
                    Tables\Columns\TextColumn::make('document.grand_total')
                        ->label('Total Biaya')
                        ->formatStateUsing(fn ($state): string => $state !== null ? 'Rp ' . number_format((float) $state, 0, ',', '.') : '-')
                        ->badge()
                        ->color('success')
                        ->grow(false),
                    Tables\Columns\TextColumn::make('status')
                        ->label('Status')
                        ->badge()
                        ->formatStateUsing(function (ApprovalRequest $record): string {
                            if ($record->status === 'approved') {
                                return 'Disetujui';
                            }
                            if ($record->status === 'rejected') {
                                return 'Ditolak';
                            }

                            $resolverClass = $record->currentStep()?->policyStep?->resolver_class;
                            if ($resolverClass === BusinessDivisionHeadAuthorityResolver::class) {
                                return 'Menunggu Division Head Perkasa';
                            }

                            return $resolverClass === DivisionHeadAuthorityResolver::class
                                ? 'Menunggu Division Head'
                                : 'Menunggu Pemeriksaan Manajer';
                        })
                        ->color(fn (ApprovalRequest $record): string => match ($record->status) {
                            'approved' => 'success',
                            'rejected' => 'danger',
                            default => 'warning',
                        })
                        ->grow(false),
                ])->from('md'),

                Tables\Columns\Layout\Panel::make([
                    Tables\Columns\Layout\Grid::make(['default' => 1, 'sm' => 3])
                        ->schema([
                            Tables\Columns\TextColumn::make('requester.name')
                                ->description('Diajukan Oleh', position: 'above')
                                ->placeholder('-'),
                            Tables\Columns\TextColumn::make('created_at')
                                ->description('Tanggal Pengajuan', position: 'above')
                                ->dateTime('d/m/Y H:i'),
                            Tables\Columns\TextColumn::make('updated_at')
                                ->description('Diperbarui Pada', position: 'above')
                                ->dateTime('d/m/Y H:i'),
                        ]),
                ])->collapsible(),
            ])
            ->filters([
                static::monthYearFilter('created_at'),
                Tables\Filters\SelectFilter::make('status')
                    ->label('Status Persetujuan')
                    ->options([
                        'pending' => 'Menunggu Persetujuan',
                        'approved' => 'Disetujui',
                        'rejected' => 'Ditolak',
                    ]),
            ])
            ->actions([
                Tables\Actions\Action::make('view_detail')
                    ->label(function (ApprovalRequest $record): string {
                        $employee = (\Filament\Facades\Filament::auth()->user() ?? auth()->user())?->employee;

                        return ($employee && $record->status === 'pending' && (new ApprovalResolver())->canApprove($record, $employee))
                            ? 'Review'
                            : 'Lihat';
                    })
                    ->icon('heroicon-o-eye')
                    ->color(function (ApprovalRequest $record): string {
                        $employee = (\Filament\Facades\Filament::auth()->user() ?? auth()->user())?->employee;

                        return ($employee && $record->status === 'pending' && (new ApprovalResolver())->canApprove($record, $employee))
                            ? 'warning'
                            : 'gray';
                    })
                    ->button()
                    ->size('sm')
                    ->url(fn (ApprovalRequest $record) => $recapResourceClass::getUrl('view', ['record' => $record->document_id])),

                Tables\Actions\Action::make('print')
                    ->label('Cetak')
                    ->icon('heroicon-o-printer')
                    ->color('info')
                    ->button()
                    ->size('sm')
                    ->url(fn (ApprovalRequest $record) => route($printRouteName, $record->document_id))
                    ->openUrlInNewTab(),
            ])
            ->defaultSort('created_at', 'desc');
    }
}
