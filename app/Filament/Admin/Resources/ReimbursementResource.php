<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\ReimbursementResource\Pages;
use App\Models\ApprovalRequest;
use App\Models\Employee;
use App\Models\Expense;
use App\Models\Reimbursement;
use App\Models\Sppd;
use App\Observers\ReimbursementObserver;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class ReimbursementResource extends Resource
{
    protected static ?string $model = Reimbursement::class;

    protected static ?string $navigationIcon = 'heroicon-o-banknotes';
    protected static ?string $navigationGroup = 'Keuangan';
    protected static ?string $navigationLabel = 'Reimbursement';
    protected static ?string $slug = 'reimbursements';
    protected static ?string $modelLabel = 'Reimbursement';
    protected static ?string $pluralModelLabel = 'Pengajuan Reimbursement';
    protected static ?int $navigationSort = 1;

    public static function shouldRegisterNavigation(): bool
    {
        $user = \Filament\Facades\Filament::auth()->user() ?? auth()->user();
        if (! $user) {
            return false;
        }

        // Managers and Supervisors only need "Persetujuan Reimbursement", not the master "Reimbursement" menu
        if ($user->hasAnyRole(['Manager', 'Supervisor']) && ! $user->hasAnyRole(['Superadmin', 'Admin', 'HR'])) {
            return false;
        }

        return true;
    }

    public static function canViewAny(): bool
    {
        $user = \Filament\Facades\Filament::auth()->user() ?? auth()->user();
        if (! $user) return false;

        // Managers and Supervisors only access Persetujuan Reimbursement
        if ($user->hasAnyRole(['Manager', 'Supervisor']) && ! $user->hasAnyRole(['Superadmin', 'Admin', 'HR'])) {
            return false;
        }

        return $user->hasAnyRole(['Superadmin', 'Admin', 'HR', 'Staff']);
    }

    public static function getNavigationBadge(): ?string
    {
        $count = static::getEloquentQuery()->count();
        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'primary';
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return 'Total Pengajuan Reimbursement';
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
                    if (request()->routeIs('filament.admin.resources.reimbursements.review') && (request()->query('from') === 'approvals' || auth()->user()?->hasRole('Manager'))) {
                        return false;
                    }
                    return request()->routeIs(static::getRouteBaseName() . '.*');
                })
                ->badge(static::getNavigationBadge(), color: static::getNavigationBadgeColor())
                ->badgeTooltip(static::getNavigationBadgeTooltip())
                ->sort(static::getNavigationSort())
                ->url(static::getNavigationUrl()),
        ];
    }

    public static function canView(Model $record): bool
    {
        $user = \Filament\Facades\Filament::auth()->user() ?? auth()->user();
        if (! $user) return false;

        if ($user->hasAnyRole(['Superadmin', 'Admin', 'HR'])) {
            return true;
        }

        // Managers and Supervisors can view reimbursement in their department or for their subordinates
        if ($user->hasAnyRole(['Manager', 'Supervisor'])) {
            $employee = $user->employee;
            if (! $employee) return false;

            return $record->employee?->department_id === $employee->department_id
                || $record->employee?->manager_id === $employee->id
                || (int) $record->employee_id === (int) $employee->id;
        }

        return (int) $record->employee_id === (int) ($user->employee?->id ?? 0);
    }

    public static function canCreate(): bool
    {
        $user = \Filament\Facades\Filament::auth()->user() ?? auth()->user();
        if (! $user) return false;
        return $user->hasAnyRole(['Superadmin', 'Admin', 'HR', 'Staff', 'Manager', 'Supervisor']);
    }

    public static function canEdit(Model $record): bool
    {
        $user = \Filament\Facades\Filament::auth()->user() ?? auth()->user();
        if (! $user) return false;

        if ($user->hasRole(['Superadmin', 'Admin'])) {
            return true;
        }

        // Boleh diedit jika statusnya draft atau rejected
        if (in_array($record->status, ['draft', 'rejected'])) {
            if ($user->hasRole('HR')) {
                return true;
            }
            return (int) $record->employee_id === (int) $user->employee?->id;
        }

        return false;
    }

    public static function canDelete(Model $record): bool
    {
        $user = \Filament\Facades\Filament::auth()->user() ?? auth()->user();
        if (! $user) return false;

        if ($user->hasRole(['Superadmin', 'Admin'])) {
            return true;
        }

        if ($record->status === 'draft') {
            if ($user->hasRole('HR')) {
                return true;
            }
            return (int) $record->employee_id === (int) $user->employee?->id;
        }

        return false;
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()->with(['employee.position', 'employee.department', 'sppd.businessTrip', 'expenses']);
        $user = \Filament\Facades\Filament::auth()->user() ?? auth()->user();

        if (! $user || $user->hasRole(['Superadmin', 'Admin', 'HR'])) {
            return $query;
        }

        $employee = $user->employee;
        if (! $employee) {
            return $query->whereRaw('1 = 0');
        }

        // Manajer dapat melihat permohonan bawahannya dan miliknya sendiri
        if ($user->hasRole('Manager')) {
            return $query->where(function (Builder $q) use ($employee) {
                $q->where('employee_id', $employee->id)
                    ->orWhereHas('employee', function (Builder $empQ) use ($employee) {
                        $empQ->where('department_id', $employee->department_id)
                            ->orWhere('manager_id', $employee->id);
                    });
            });
        }

        // Supervisor dapat melihat permohonan timnya dan miliknya sendiri
        if ($user->hasRole('Supervisor')) {
            return $query->where(function (Builder $q) use ($employee) {
                $q->where('employee_id', $employee->id)
                    ->orWhereHas('employee', function (Builder $empQ) use ($employee) {
                        $empQ->where('manager_id', $employee->id);
                    });
            });
        }

        // Staff hanya melihat miliknya sendiri
        return $query->where('employee_id', $employee->id);
    }

    public static function form(Form $form): Form
    {
        $user = \Filament\Facades\Filament::auth()->user() ?? auth()->user();
        $isHrOrAdmin = $user && $user->hasRole(['Superadmin', 'Admin', 'HR']);

        return $form
            ->schema([
                Forms\Components\Section::make('Informasi Pengajuan & SPPD')
                    ->icon('heroicon-o-document-currency-dollar')
                    ->iconColor('primary')
                    ->description('Pilih pegawai pengaju dan surat tugas SPPD terkait yang telah disetujui.')
                    ->collapsible()
                    ->schema([
                        $isHrOrAdmin
                            ? Forms\Components\Select::make('employee_id')
                                ->label('Pegawai Pengaju')
                                ->prefixIcon('heroicon-m-user')
                                ->relationship('employee', 'name')
                                ->searchable()
                                ->preload()
                                ->required()
                                ->live()
                                ->default(fn () => $user->employee?->id)
                                ->afterStateUpdated(fn (Forms\Set $set) => $set('sppd_id', null))
                                ->helperText('Sebagai HR/Admin, Anda dapat memilih pegawai yang dibantu proses pengajuannya.')
                            : Forms\Components\Hidden::make('employee_id')
                                ->default(fn () => $user?->employee?->id)
                                ->required(),

                        ! $isHrOrAdmin
                            ? Forms\Components\TextInput::make('employee_name_display')
                                ->label('Pegawai Pengaju')
                                ->prefixIcon('heroicon-m-user')
                                ->default(fn () => $user?->employee ? "{$user->employee->name} ({$user->employee->identity_number})" : '-')
                                ->disabled()
                                ->dehydrated(false)
                            : Forms\Components\Placeholder::make('spacer')->hidden(),

                        Forms\Components\Select::make('sppd_id')
                            ->label('Terkait SPPD (Surat Perintah Perjalanan Dinas)')
                            ->prefixIcon('heroicon-m-paper-airplane')
                            ->required()
                            ->searchable()
                            ->preload()
                            ->options(function (Forms\Get $get) use ($user, $isHrOrAdmin) {
                                $empId = $isHrOrAdmin ? $get('employee_id') : ($user?->employee?->id);
                                if (! $empId) {
                                    return [];
                                }

                                return Sppd::where('employee_id', $empId)
                                    ->where('status', 'approved')
                                    ->with('businessTrip')
                                    ->latest('id')
                                    ->get()
                                    ->mapWithKeys(function (Sppd $s) {
                                        $dest = $s->businessTrip?->destination ?? ($s->destination_location ?? 'Tujuan Dinas');
                                        $dates = '';
                                        if ($s->businessTrip?->start_date && $s->businessTrip?->end_date) {
                                            $dates = ' (' . $s->businessTrip->start_date->format('d/m/Y') . ' - ' . $s->businessTrip->end_date->format('d/m/Y') . ')';
                                        }
                                        return [$s->id => "{$s->sppd_number} • {$dest}{$dates}"];
                                    })
                                    ->toArray();
                            })
                            ->helperText('Hanya menampilkan daftar SPPD yang telah berstatus Approved/Disetujui.'),

                        Forms\Components\TextInput::make('title')
                            ->label('Judul / Keperluan Reimbursement')
                            ->prefixIcon('heroicon-m-document-text')
                            ->placeholder('Contoh: Reimbursement Penggantian BBM & Tol Site Visit Surabaya')
                            ->required()
                            ->maxLength(255)
                            ->columnSpanFull(),

                        Forms\Components\Textarea::make('notes')
                            ->label('Catatan Tambahan Pengaju')
                            ->placeholder('Tuliskan catatan tambahan mengenai rincian pengeluaran atau alasan reimbursement jika ada...')
                            ->rows(3)
                            ->columnSpanFull(),
                    ])
                    ->columns(['default' => 1, 'md' => 2]),

                Forms\Components\Section::make('Rincian Pengeluaran & Bukti Kuitansi')
                    ->icon('heroicon-o-receipt-percent')
                    ->iconColor('info')
                    ->description('Masukkan rincian biaya yang dikeluarkan serta unggah foto atau file PDF struk/kuitansi sebagai bukti.')
                    ->collapsible()
                    ->schema([
                        Forms\Components\Repeater::make('expenses')
                            ->relationship('expenses')
                            ->label('Daftar Biaya Pengeluaran')
                            ->addActionLabel('Tambah Item Pengeluaran')
                            ->addAction(fn (\Filament\Forms\Components\Actions\Action $action) => $action->icon('heroicon-o-plus'))
                            ->deleteAction(fn (\Filament\Forms\Components\Actions\Action $action) => $action->icon('heroicon-o-trash'))
                            ->itemLabel(fn (array $state): ?string => ($state['expense_type'] ?? 'Item Pengeluaran') . ' — Rp ' . number_format((float) ($state['amount'] ?? 0), 0, ',', '.') . (! empty($state['is_company_paid']) ? ' (Dibayar Kantor)' : ' (Klaim Pegawai)'))
                            ->collapsible()
                            ->defaultItems(0)
                            ->schema([
                                Forms\Components\Grid::make(['default' => 1, 'md' => 3])
                                    ->schema([
                                        Forms\Components\Select::make('expense_type')
                                            ->label('Jenis Pengeluaran')
                                            ->prefixIcon('heroicon-m-tag')
                                            ->options([
                                                'Bahan Bakar & Tol' => 'Bahan Bakar & Tol (BBM / Toll)',
                                                'Tiket Transportasi' => 'Tiket Transportasi (Pesawat / Kereta / Bus)',
                                                'Transportasi Lokal' => 'Transportasi Lokal (Taksi / Sewa Mobil)',
                                                'Penginapan / Hotel' => 'Penginapan / Hotel',
                                                'Konsumsi / Makan' => 'Konsumsi / Makan',
                                                'Parkir & Retribusi' => 'Parkir & Retribusi',
                                                'Perlengkapan Proyek' => 'Perlengkapan Proyek / ATK Lapangan',
                                                'Lain-lain' => 'Lain-lain / Keperluan Darurat',
                                            ])
                                            ->required()
                                            ->searchable(),

                                        Forms\Components\DatePicker::make('transaction_date')
                                            ->label('Tanggal Transaksi / Kwitansi')
                                            ->prefixIcon('heroicon-m-calendar-days')
                                            ->native(false)
                                            ->displayFormat('d/m/Y')
                                            ->closeOnDateSelection()
                                            ->default(now())
                                            ->required(),

                                        Forms\Components\TextInput::make('amount')
                                            ->label('Nominal Biaya')
                                            ->numeric()
                                            ->prefix('Rp')
                                            ->minValue(1)
                                            ->placeholder('0')
                                            ->required(),
                                    ]),

                                Forms\Components\Grid::make(['default' => 1, 'md' => 3])
                                    ->schema([
                                        Forms\Components\TextInput::make('description')
                                            ->label('Keterangan Pengeluaran')
                                            ->prefixIcon('heroicon-m-pencil-square')
                                            ->placeholder('Contoh: Isi BBM Pertamax di Rest Area KM 57')
                                            ->maxLength(500)
                                            ->columnSpan(['default' => 1, 'md' => 2]),

                                        Forms\Components\Toggle::make('is_company_paid')
                                            ->label('Ditalangi / Dibayar Perusahaan?')
                                            ->helperText('Aktifkan jika dibayar langsung oleh kantor.')
                                            ->inline(false)
                                            ->default(false)
                                            ->columnSpan(1),
                                    ]),

                                Forms\Components\FileUpload::make('receipt_path')
                                    ->label('Bukti Kwitansi / Struk (Foto atau PDF)')
                                    ->directory('reimbursement-receipts')
                                    ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/jpg', 'image/webp', 'application/pdf'])
                                    ->maxSize(5120)
                                    ->openable()
                                    ->downloadable()
                                    ->previewable()
                                    ->required()
                                    ->helperText('Unggah foto kuitansi/struk pembayaran yang jelas atau dokumen file PDF (Maks. 5 MB).')
                                    ->columnSpanFull(),
                            ])
                            ->columns(1),
                    ]),
            ]);
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Infolists\Components\Section::make('Informasi Pengajuan Reimbursement')
                    ->icon('heroicon-o-document-currency-dollar')
                    ->iconColor('primary')
                    ->schema([
                        Infolists\Components\TextEntry::make('reimbursement_number')
                            ->label('Nomor Pengajuan')
                            ->weight('bold')
                            ->color('primary')
                            ->copyable(),

                        Infolists\Components\TextEntry::make('status')
                            ->label('Status Dokumen')
                            ->badge()
                            ->formatStateUsing(fn (string $state): string => match ($state) {
                                'draft' => 'Draf (Belum Diajukan)',
                                'pending' => 'Menunggu Persetujuan Manajer',
                                'approved' => 'Disetujui Manajer',
                                'rejected' => 'Ditolak Manajer',
                                'paid' => 'Telah Dibayarkan',
                                default => ucfirst($state),
                            })
                            ->color(fn (string $state): string => match ($state) {
                                'draft' => 'gray',
                                'pending' => 'warning',
                                'approved' => 'success',
                                'rejected' => 'danger',
                                'paid' => 'info',
                                default => 'gray',
                            }),

                        Infolists\Components\TextEntry::make('employee.name')
                            ->label('Pegawai Pengaju')
                            ->weight('bold')
                            ->helperText(fn (Reimbursement $record) => $record->employee?->position?->name . ' • ' . $record->employee?->department?->name),

                        Infolists\Components\TextEntry::make('sppd.sppd_number')
                            ->label('Terkait SPPD')
                            ->weight('medium')
                            ->helperText(fn (Reimbursement $record) => 'Tujuan: ' . ($record->sppd?->businessTrip?->destination ?? ($record->sppd?->destination_location ?? '-'))),

                        Infolists\Components\TextEntry::make('title')
                            ->label('Judul / Keperluan')
                            ->columnSpan(2),

                        Infolists\Components\TextEntry::make('notes')
                            ->label('Catatan Tambahan Pengaju')
                            ->placeholder('-')
                            ->columnSpanFull(),

                        Infolists\Components\TextEntry::make('rejection_note')
                            ->label('Alasan Penolakan')
                            ->visible(fn (Reimbursement $record) => $record->status === 'rejected' && ! empty($record->rejection_note))
                            ->color('danger')
                            ->columnSpanFull(),
                    ])
                    ->columns(2),

                Infolists\Components\Section::make('Rincian Pengeluaran & Bukti Dokumen')
                    ->icon('heroicon-o-receipt-percent')
                    ->iconColor('info')
                    ->schema([
                        Infolists\Components\RepeatableEntry::make('expenses')
                            ->label('Daftar Biaya & Kwitansi Terlampir')
                            ->schema([
                                Infolists\Components\TextEntry::make('expense_type')
                                    ->label('Jenis Pengeluaran')
                                    ->weight('bold'),

                                Infolists\Components\TextEntry::make('transaction_date')
                                    ->label('Tanggal Kwitansi')
                                    ->date('d/m/Y'),

                                Infolists\Components\TextEntry::make('amount')
                                    ->label('Nominal Biaya')
                                    ->money('IDR', locale: 'id')
                                    ->weight('bold')
                                    ->color('primary'),

                                Infolists\Components\TextEntry::make('is_company_paid')
                                    ->label('Status Biaya')
                                    ->badge()
                                    ->formatStateUsing(fn (bool $state): string => $state ? 'Ditalangi Kantor' : 'Klaim Pegawai')
                                    ->color(fn (bool $state): string => $state ? 'info' : 'warning'),

                                Infolists\Components\TextEntry::make('description')
                                    ->label('Keterangan')
                                    ->placeholder('-')
                                    ->columnSpan(2),

                                Infolists\Components\TextEntry::make('receipt_path')
                                    ->label('File Bukti Struk/Kwitansi')
                                    ->formatStateUsing(function ($state, Expense $record) {
                                        if (! $state) {
                                            return 'Tidak ada lampiran';
                                        }
                                        $isPdf = $record->isPdfReceipt();
                                        return $isPdf ? '📄 Dokumen PDF Kuitansi' : '🖼️ Foto Bukti Kuitansi';
                                    })
                                    ->suffixAction(
                                        Infolists\Components\Actions\Action::make('open_receipt')
                                            ->label('Buka Bukti')
                                            ->icon('heroicon-o-arrow-top-right-on-square')
                                            ->color('primary')
                                            ->url(fn (Expense $record) => $record->receipt_url, shouldOpenInNewTab: true)
                                            ->visible(fn (Expense $record) => ! empty($record->receipt_path))
                                    )
                                    ->columnSpan(2),
                            ])
                            ->columns(2),
                    ]),

                Infolists\Components\Section::make('Ringkasan Biaya Penggantian')
                    ->icon('heroicon-o-banknotes')
                    ->iconColor('success')
                    ->schema([
                        Infolists\Components\TextEntry::make('total_claim_amount')
                            ->label('Total Yang Diganti ke Pegawai (Klaim Pegawai)')
                            ->money('IDR', locale: 'id')
                            ->weight('bold')
                            ->size('lg')
                            ->color('warning'),

                        Infolists\Components\TextEntry::make('total_amount')
                            ->label('Total Seluruh Pengeluaran Termasuk Kantor')
                            ->money('IDR', locale: 'id')
                            ->weight('bold')
                            ->size('lg')
                            ->color('gray'),
                    ])
                    ->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\Layout\Split::make([
                    Tables\Columns\Layout\Stack::make([
                        Tables\Columns\TextColumn::make('reimbursement_number')
                            ->label('Nomor Reimbursement')
                            ->searchable()
                            ->sortable()
                            ->weight('bold')
                            ->color('primary')
                            ->copyable(),
                        Tables\Columns\TextColumn::make('title')
                            ->label('Keperluan')
                            ->searchable()
                            ->limit(40)
                            ->color('gray'),
                        Tables\Columns\TextColumn::make('employee.name')
                            ->label('Pegawai Pengaju')
                            ->searchable()
                            ->sortable()
                            ->icon('heroicon-o-user')
                            ->description(fn (Reimbursement $record) => $record->employee?->position?->name . ' • ' . $record->employee?->department?->name),
                    ])->space(1),

                    Tables\Columns\Layout\Stack::make([
                        Tables\Columns\TextColumn::make('sppd.sppd_number')
                            ->label('Nomor SPPD')
                            ->badge()
                            ->color('gray')
                            ->formatStateUsing(fn ($state, Reimbursement $record) => $state ? "SPPD: {$state}" : '-'),
                        Tables\Columns\TextColumn::make('sppd.businessTrip.destination')
                            ->label('Tujuan Dinas')
                            ->icon('heroicon-o-map-pin')
                            ->color('gray')
                            ->placeholder('-'),
                        Tables\Columns\TextColumn::make('status')
                            ->badge()
                            ->formatStateUsing(fn (string $state): string => match ($state) {
                                'draft' => 'Draf',
                                'pending' => 'Menunggu Persetujuan Manajer',
                                'approved' => 'Disetujui Manajer',
                                'rejected' => 'Ditolak Manajer',
                                'paid' => 'Dibayarkan',
                                default => ucfirst($state),
                            })
                            ->color(fn (string $state): string => match ($state) {
                                'draft' => 'gray',
                                'pending' => 'warning',
                                'approved' => 'success',
                                'rejected' => 'danger',
                                'paid' => 'info',
                                default => 'gray',
                            }),
                    ])->space(1),

                    Tables\Columns\Layout\Stack::make([
                        Tables\Columns\TextColumn::make('total_claim_amount')
                            ->label('Total Klaim')
                            ->money('IDR', locale: 'id')
                            ->weight('bold')
                            ->color('warning')
                            ->description('Klaim Yang Diganti', position: 'above'),
                        Tables\Columns\TextColumn::make('created_at')
                            ->label('Tanggal Input')
                            ->date('d/m/Y H:i')
                            ->color('gray'),
                    ])->space(1)->alignment('right'),
                ])->from('md'),

                Tables\Columns\Layout\Panel::make([
                    Tables\Columns\Layout\Grid::make(['default' => 1, 'sm' => 2, 'md' => 3])
                        ->schema([
                            Tables\Columns\TextColumn::make('expenses_count')
                                ->label('Item Pengeluaran')
                                ->state(fn (Reimbursement $record) => $record->expenses()->count() . ' Item Kwitansi Terlampir')
                                ->icon('heroicon-o-receipt-percent'),
                            Tables\Columns\TextColumn::make('total_amount')
                                ->label('Total Biaya Keseluruhan')
                                ->money('IDR', locale: 'id')
                                ->description('Total Termasuk Kantor', position: 'above'),
                            Tables\Columns\TextColumn::make('notes')
                                ->label('Catatan Pengaju')
                                ->placeholder('-')
                                ->limit(60)
                                ->description('Catatan Pengaju', position: 'above'),
                        ]),
                ])->collapsible(),
            ])
            ->defaultSort('id', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label('Status Reimbursement')
                    ->options([
                        'draft' => 'Draf',
                        'pending' => 'Menunggu Persetujuan Manajer',
                        'approved' => 'Disetujui Manajer',
                        'rejected' => 'Ditolak Manajer',
                        'paid' => 'Dibayarkan',
                    ]),
                Tables\Filters\SelectFilter::make('employee_id')
                    ->label('Pegawai Pengaju')
                    ->relationship('employee', 'name')
                    ->searchable()
                    ->preload(),
            ])
            ->actions([
                Tables\Actions\Action::make('submit')
                    ->label('Ajukan ke Manajer')
                    ->icon('heroicon-o-paper-airplane')
                    ->button()
                    ->size('sm')
                    ->color('warning')
                    ->visible(function (Reimbursement $record) {
                        if (! in_array($record->status, ['draft', 'rejected'])) {
                            return false;
                        }
                        $user = \Filament\Facades\Filament::auth()->user() ?? auth()->user();
                        if (! $user) return false;
                        if ($user->hasRole(['Superadmin', 'Admin', 'HR'])) return true;
                        return (int) $record->employee_id === (int) $user->employee?->id;
                    })
                    ->requiresConfirmation()
                    ->modalHeading('Ajukan & Tandatangani Reimbursement')
                    ->modalDescription(function (Reimbursement $record) {
                        $user = \Filament\Facades\Filament::auth()->user() ?? auth()->user();
                        if (! $user?->hasConfiguredSignature()) {
                            return 'PERHATIAN: Anda belum mengatur tanda tangan. Silakan atur tanda tangan Anda terlebih dahulu (cukup 1 kali) di menu Tanda Tangan sebelum mengajukan reimbursement ini.';
                        }
                        return 'Apakah Anda yakin ingin mengajukan reimbursement ' . $record->reimbursement_number . ' dengan total klaim Rp ' . number_format($record->total_claim_amount, 0, ',', '.') . ' ke Manajer? Tanda tangan Anda akan otomatis dibubuhkan sebagai Pegawai Pengaju.';
                    })
                    ->modalSubmitActionLabel('Ya, Ajukan & Tandatangani')
                    ->action(function (Reimbursement $record) {
                        $user = \Filament\Facades\Filament::auth()->user() ?? auth()->user();
                        $sigService = app(\App\Services\Signature\SignatureService::class);
                        if (! $sigService->ensureUserCanSign($user)) {
                            return;
                        }

                        if ($record->expenses()->count() === 0) {
                            Notification::make()
                                ->title('Tidak dapat mengajukan')
                                ->body('Harap masukkan minimal 1 item pengeluaran beserta bukti kwitansi sebelum mengajukan.')
                                ->danger()
                                ->send();
                            return;
                        }

                        $record->recalculateTotals();
                        $record->update([
                            'status' => 'pending',
                            'submitted_at' => now(),
                        ]);

                        (new ReimbursementObserver())->ensureApprovalRequestExists($record);

                        $sigService->signDocument(
                            $record,
                            $record->employee ?? $user?->employee,
                            'requester',
                            'Diajukan oleh Pegawai',
                            $user
                        );

                        Notification::make()
                            ->title('Reimbursement Berhasil Diajukan & Ditandatangani')
                            ->body('Tanda tangan Anda telah dibubuhkan dan permohonan diteruskan ke Manajer untuk persetujuan.')
                            ->success()
                            ->send();
                    }),

                Tables\Actions\Action::make('review')
                    ->label('Tinjau')
                    ->icon('heroicon-o-play-circle')
                    ->button()
                    ->size('sm')
                    ->color('primary')
                    ->url(fn (Reimbursement $record) => static::getUrl('review', ['record' => $record]))
                    ->visible(fn (Reimbursement $record) => $record->status === 'pending'),

                Tables\Actions\Action::make('view_detail')
                    ->label('Lihat')
                    ->icon('heroicon-o-eye')
                    ->button()
                    ->size('sm')
                    ->color('gray')
                    ->url(fn (Reimbursement $record) => static::getUrl('review', ['record' => $record]))
                    ->visible(fn (Reimbursement $record) => $record->status !== 'pending'),

                Tables\Actions\Action::make('print')
                    ->label('Cetak')
                    ->icon('heroicon-o-printer')
                    ->button()
                    ->size('sm')
                    ->color('info')
                    ->url(fn (Reimbursement $record) => route('reimbursements.print', $record))
                    ->openUrlInNewTab()
                    ->visible(fn (Reimbursement $record) => in_array($record->status, ['approved', 'pending', 'paid'])),

                Tables\Actions\EditAction::make()
                    ->label('Ubah')
                    ->icon('heroicon-o-pencil-square')
                    ->button()
                    ->size('sm')
                    ->visible(fn (Reimbursement $record) => static::canEdit($record)),

                Tables\Actions\DeleteAction::make()
                    ->label('Hapus')
                    ->icon('heroicon-o-trash')
                    ->button()
                    ->size('sm')
                    ->visible(fn (Reimbursement $record) => static::canDelete($record)),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->label('Hapus Terpilih')
                        ->icon('heroicon-o-trash'),
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
            'index' => Pages\ListReimbursements::route('/'),
            'create' => Pages\CreateReimbursement::route('/create'),
            'edit' => Pages\EditReimbursement::route('/{record}/edit'),
            'review' => Pages\ReviewReimbursement::route('/{record}/review'),
        ];
    }
}
