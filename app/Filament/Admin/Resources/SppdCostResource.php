<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\SppdCostResource\Pages;
use App\Models\Sppd;
use App\Models\SppdCost;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class SppdCostResource extends Resource
{
    protected static ?string $model = Sppd::class;

    protected static ?string $navigationIcon = 'heroicon-o-calculator';
    protected static ?string $navigationGroup = 'Keuangan';
    protected static ?string $navigationLabel = 'Biaya SPPD';
    protected static ?string $slug = 'sppd-costs';
    protected static ?string $modelLabel = 'Perhitungan Biaya SPPD';
    protected static ?string $pluralModelLabel = 'Perhitungan Biaya SPPD';
    protected static ?int $navigationSort = 2;

    public static function canViewAny(): bool
    {
        $user = \Filament\Facades\Filament::auth()->user() ?? auth()->user();
        if (! $user) return false;
        return $user->hasAnyRole(['Superadmin', 'Admin', 'HR']);
    }

    public static function getNavigationBadge(): ?string
    {
        $count = static::getEloquentQuery()->count();
        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return static::getEloquentQuery()->whereDoesntHave('cost')->exists()
            ? 'warning'
            : 'success';
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return 'Total SPPD Disetujui untuk Perhitungan Biaya';
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        $user = \Filament\Facades\Filament::auth()->user() ?? auth()->user();
        if (! $user) return false;
        if (! $user->hasAnyRole(['Superadmin', 'Admin', 'HR'])) {
            return false;
        }
        if ($user->hasRole('HR') && ! $user->hasAnyRole(['Superadmin', 'Admin'])) {
            $areaId = $user->employee?->work_area_id;
            if ($areaId && $record instanceof Sppd && $record->employee?->work_area_id) {
                return (int) $areaId === (int) $record->employee->work_area_id;
            }
        }

        return true;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()
            ->where('status', 'approved')
            ->with(['employee.position', 'employee.department', 'cost', 'businessTrip.memo', 'businessTrip.sppds']);

        $user = \Filament\Facades\Filament::auth()->user() ?? auth()->user();
        if ($user && ! $user->hasAnyRole(['Superadmin', 'Admin']) && $user->employee?->work_area_id) {
            $areaId = (int) $user->employee->work_area_id;
            $query->whereHas('employee', fn (Builder $eq) => $eq->where('work_area_id', $areaId));
        }

        return $query;
    }

    public static function calculateAllTotals(Forms\Set $set, Forms\Get $get): void
    {
        // 1. Transport
        $tTerminal = (float) ($get('transport_terminal_qty') ?: 0) * (float) ($get('transport_terminal_rate') ?: 0);
        $set('transport_terminal_total', $tTerminal);

        $tTicket = (float) ($get('transport_ticket_qty') ?: 0) * (float) ($get('transport_ticket_rate') ?: 0);
        $set('transport_ticket_total', $tTicket);

        $tLocal = (float) ($get('transport_local_qty') ?: 0) * (float) ($get('transport_local_rate') ?: 0);
        $set('transport_local_total', $tLocal);

        $subtotalTransport = $tTerminal + $tTicket + $tLocal;
        $set('subtotal_transport', $subtotalTransport);

        // 2. Jenis Biaya (Harian)
        $tHotel = (float) ($get('hotel_qty') ?: 0) * (float) ($get('hotel_rate') ?: 0);
        $set('hotel_total', $tHotel);

        $tMeal = (float) ($get('meal_qty') ?: 0) * (float) ($get('meal_rate') ?: 0);
        $set('meal_total', $tMeal);

        $tMealReimburse = (float) ($get('meal_reimbursement_qty') ?: 0) * (float) ($get('meal_reimbursement_rate') ?: 0);
        $set('meal_reimbursement_total', $tMealReimburse);

        $tTransit = (float) ($get('local_transit_qty') ?: 0) * (float) ($get('local_transit_rate') ?: 0);
        $set('local_transit_total', $tTransit);

        $tPocket = (float) ($get('pocket_money_qty') ?: 0) * (float) ($get('pocket_money_rate') ?: 0);
        $set('pocket_money_total', $tPocket);

        $overseas = (float) ($get('overseas_daily_allowance') ?: 0) +
            (float) ($get('overseas_hotel_meal') ?: 0) +
            (float) ($get('overseas_transport') ?: 0) +
            (float) ($get('overseas_pocket_money') ?: 0) +
            (float) ($get('overseas_monthly_allowance') ?: 0);

        $subtotalPerDiem = $tHotel + $tMeal + $tMealReimburse + $tTransit + $tPocket + $overseas;
        $set('subtotal_per_diem', $subtotalPerDiem);

        // 3. Biaya Lain
        $subtotalOther = (float) ($get('other_airport_tax') ?: 0) +
            (float) ($get('other_laundry') ?: 0) +
            (float) ($get('other_equipment') ?: 0) +
            (float) ($get('other_book_paper') ?: 0) +
            (float) ($get('other_assessor') ?: 0) +
            (float) ($get('other_car_rental') ?: 0) +
            (float) ($get('other_baggage') ?: 0) +
            (float) ($get('other_toll_fuel_parking') ?: 0);
        $set('subtotal_other', $subtotalOther);

        // Grand Total
        $totalCost = $subtotalTransport + $subtotalPerDiem + $subtotalOther;
        $set('total_cost', $totalCost);
    }

    public static function getCostFormSchema(): array
    {
        return [
            Forms\Components\Section::make('Ketentuan Tarif Standar (ST-JTK-05)')
                ->icon('heroicon-o-adjustments-horizontal')
                ->iconColor('primary')
                ->description('Pilih kategori ketentuan standar biaya perjalanan dinas dan durasi hari dinas.')
                ->collapsible()
                ->schema([
                    Forms\Components\Fieldset::make('Kategori Standar & Durasi Penugasan')
                        ->schema([
                            Forms\Components\Radio::make('base_type')
                                ->label('Kategori Standar Perhitungan Tarif')
                                ->options(fn () => \App\Models\SppdRateStandard::getCategoryOptions())
                                ->default('office_base_stay')
                                ->live()
                                ->afterStateUpdated(function ($state, Forms\Set $set, Forms\Get $get) {
                                    $days = (int) ($get('official_days') ?: 1);
                                    $defaults = SppdCost::getDefaultRates($state ?: 'office_base_stay', $days);
                                    foreach ($defaults as $key => $val) {
                                        $set($key, $val);
                                    }
                                    static::calculateAllTotals($set, $get);
                                })
                                ->columnSpan(['default' => 1, 'md' => 2]),

                            Forms\Components\TextInput::make('official_days')
                                ->label('Durasi Hari Dinas')
                                ->prefixIcon('heroicon-m-calendar-days')
                                ->suffix('Hari')
                                ->helperText('Mengubah durasi hari otomatis menyesuaikan frekuensi komponen harian.')
                                ->numeric()
                                ->default(1)
                                ->minValue(1)
                                ->required()
                                ->live(onBlur: true)
                                ->afterStateUpdated(function ($state, Forms\Set $set, Forms\Get $get) {
                                    $days = max(1, (int) $state);
                                    $baseType = $get('base_type') ?: 'office_base_stay';
                                    $defaults = SppdCost::getDefaultRates($baseType, $days);
                                    foreach (['meal_qty', 'pocket_money_qty', 'hotel_qty', 'transport_terminal_qty', 'local_transit_qty'] as $qtyKey) {
                                        if (isset($defaults[$qtyKey])) {
                                            $set($qtyKey, $defaults[$qtyKey]);
                                        }
                                    }
                                    static::calculateAllTotals($set, $get);
                                })
                                ->columnSpan(1),
                        ])
                        ->columns(['default' => 1, 'md' => 3]),
                ]),

            // 1. BIAYA TRANSPORT
            Forms\Components\Section::make('1. Biaya Transportasi')
                ->icon('heroicon-o-truck')
                ->iconColor('info')
                ->description('Rincian transportasi dari/ke terminal/bandara, sarana transportasi utama, dan di tempat tujuan.')
                ->collapsible()
                ->schema([
                    Forms\Components\Fieldset::make('Komponen Transportasi')
                        ->schema([
                            Forms\Components\Grid::make(['default' => 1, 'md' => 3])
                                ->schema([
                                    Forms\Components\TextInput::make('transport_terminal_qty')
                                        ->label('a. Terminal / Bandara (Qty)')
                                        ->suffix('Kali')
                                        ->numeric()
                                        ->default(2)
                                        ->live(onBlur: true)
                                        ->afterStateUpdated(fn ($state, Forms\Set $set, Forms\Get $get) => static::calculateAllTotals($set, $get)),
                                    Forms\Components\TextInput::make('transport_terminal_rate')
                                        ->label('Tarif Satuan')
                                        ->numeric()
                                        ->prefix('Rp')
                                        ->default(100000)
                                        ->live(onBlur: true)
                                        ->afterStateUpdated(fn ($state, Forms\Set $set, Forms\Get $get) => static::calculateAllTotals($set, $get)),
                                    Forms\Components\TextInput::make('transport_terminal_total')
                                        ->label('Subtotal Terminal / Bandara')
                                        ->numeric()
                                        ->prefix('Rp')
                                        ->disabled()
                                        ->dehydrated(),
                                ]),

                            Forms\Components\Grid::make(['default' => 1, 'md' => 3])
                                ->schema([
                                    Forms\Components\TextInput::make('transport_ticket_qty')
                                        ->label('b. Sarana Transportasi Utama (Qty)')
                                        ->suffix('Tiket')
                                        ->numeric()
                                        ->default(0)
                                        ->live(onBlur: true)
                                        ->afterStateUpdated(fn ($state, Forms\Set $set, Forms\Get $get) => static::calculateAllTotals($set, $get)),
                                    Forms\Components\TextInput::make('transport_ticket_rate')
                                        ->label('Tarif Tiket / Biaya')
                                        ->numeric()
                                        ->prefix('Rp')
                                        ->default(0)
                                        ->live(onBlur: true)
                                        ->afterStateUpdated(fn ($state, Forms\Set $set, Forms\Get $get) => static::calculateAllTotals($set, $get)),
                                    Forms\Components\TextInput::make('transport_ticket_total')
                                        ->label('Subtotal Sarana Transportasi')
                                        ->numeric()
                                        ->prefix('Rp')
                                        ->disabled()
                                        ->dehydrated(),
                                ]),

                            Forms\Components\Grid::make(['default' => 1, 'md' => 3])
                                ->schema([
                                    Forms\Components\TextInput::make('transport_local_qty')
                                        ->label('c. Transport di Tempat Tujuan (Qty)')
                                        ->suffix('Kali')
                                        ->numeric()
                                        ->default(0)
                                        ->live(onBlur: true)
                                        ->afterStateUpdated(fn ($state, Forms\Set $set, Forms\Get $get) => static::calculateAllTotals($set, $get)),
                                    Forms\Components\TextInput::make('transport_local_rate')
                                        ->label('Tarif Satuan')
                                        ->numeric()
                                        ->prefix('Rp')
                                        ->default(0)
                                        ->live(onBlur: true)
                                        ->afterStateUpdated(fn ($state, Forms\Set $set, Forms\Get $get) => static::calculateAllTotals($set, $get)),
                                    Forms\Components\TextInput::make('transport_local_total')
                                        ->label('Subtotal di Tempat Tujuan')
                                        ->numeric()
                                        ->prefix('Rp')
                                        ->disabled()
                                        ->dehydrated(),
                                ]),
                        ])
                        ->columns(1),

                    Forms\Components\TextInput::make('subtotal_transport')
                        ->label('Jumlah Jenis Biaya Transportasi')
                        ->prefixIcon('heroicon-m-calculator')
                        ->numeric()
                        ->prefix('Rp')
                        ->disabled()
                        ->dehydrated()
                        ->extraAttributes(['class' => 'font-bold']),
                ]),

            // 2. JENIS BIAYA (HARIAN)
            Forms\Components\Section::make('2. Jenis Biaya (Biaya Harian Dinas)')
                ->icon('heroicon-o-calendar-days')
                ->iconColor('primary')
                ->description('Rincian biaya penginapan, uang makan, uang saku, dan angkutan setempat.')
                ->collapsible()
                ->schema([
                    Forms\Components\Fieldset::make('Komponen Biaya Harian Dalam Negeri')
                        ->schema([
                            Forms\Components\Grid::make(['default' => 1, 'md' => 3])
                                ->schema([
                                    Forms\Components\TextInput::make('hotel_qty')
                                        ->label('a.1 Hotel / Penginapan')
                                        ->suffix('Malam')
                                        ->numeric()
                                        ->default(0)
                                        ->live(onBlur: true)
                                        ->afterStateUpdated(fn ($state, Forms\Set $set, Forms\Get $get) => static::calculateAllTotals($set, $get)),
                                    Forms\Components\TextInput::make('hotel_rate')
                                        ->label('Tarif Hotel per Malam')
                                        ->numeric()
                                        ->prefix('Rp')
                                        ->default(0)
                                        ->live(onBlur: true)
                                        ->afterStateUpdated(fn ($state, Forms\Set $set, Forms\Get $get) => static::calculateAllTotals($set, $get)),
                                    Forms\Components\TextInput::make('hotel_total')
                                        ->label('Subtotal Hotel')
                                        ->numeric()
                                        ->prefix('Rp')
                                        ->disabled()
                                        ->dehydrated(),
                                ]),

                            Forms\Components\Grid::make(['default' => 1, 'md' => 3])
                                ->schema([
                                    Forms\Components\TextInput::make('meal_qty')
                                        ->label('a.2 Uang Makan')
                                        ->suffix('Hari')
                                        ->numeric()
                                        ->default(1)
                                        ->live(onBlur: true)
                                        ->afterStateUpdated(fn ($state, Forms\Set $set, Forms\Get $get) => static::calculateAllTotals($set, $get)),
                                    Forms\Components\TextInput::make('meal_rate')
                                        ->label('Tarif Uang Makan')
                                        ->numeric()
                                        ->prefix('Rp')
                                        ->default(145000)
                                        ->live(onBlur: true)
                                        ->afterStateUpdated(fn ($state, Forms\Set $set, Forms\Get $get) => static::calculateAllTotals($set, $get)),
                                    Forms\Components\TextInput::make('meal_total')
                                        ->label('Subtotal Uang Makan')
                                        ->numeric()
                                        ->prefix('Rp')
                                        ->disabled()
                                        ->dehydrated(),
                                ]),

                            Forms\Components\Grid::make(['default' => 1, 'md' => 3])
                                ->schema([
                                    Forms\Components\TextInput::make('meal_reimbursement_qty')
                                        ->label('a.3 Penggantian Makan (Kuitansi)')
                                        ->suffix('Kali')
                                        ->numeric()
                                        ->default(0)
                                        ->live(onBlur: true)
                                        ->afterStateUpdated(fn ($state, Forms\Set $set, Forms\Get $get) => static::calculateAllTotals($set, $get)),
                                    Forms\Components\TextInput::make('meal_reimbursement_rate')
                                        ->label('Tarif Penggantian')
                                        ->numeric()
                                        ->prefix('Rp')
                                        ->default(0)
                                        ->live(onBlur: true)
                                        ->afterStateUpdated(fn ($state, Forms\Set $set, Forms\Get $get) => static::calculateAllTotals($set, $get)),
                                    Forms\Components\TextInput::make('meal_reimbursement_total')
                                        ->label('Subtotal Penggantian Makan')
                                        ->numeric()
                                        ->prefix('Rp')
                                        ->disabled()
                                        ->dehydrated(),
                                ]),

                            Forms\Components\Grid::make(['default' => 1, 'md' => 3])
                                ->schema([
                                    Forms\Components\TextInput::make('local_transit_qty')
                                        ->label('a.4 Uang Angkutan Setempat')
                                        ->suffix('Kali')
                                        ->numeric()
                                        ->default(1)
                                        ->live(onBlur: true)
                                        ->afterStateUpdated(fn ($state, Forms\Set $set, Forms\Get $get) => static::calculateAllTotals($set, $get)),
                                    Forms\Components\TextInput::make('local_transit_rate')
                                        ->label('Tarif Angkutan')
                                        ->numeric()
                                        ->prefix('Rp')
                                        ->default(75000)
                                        ->live(onBlur: true)
                                        ->afterStateUpdated(fn ($state, Forms\Set $set, Forms\Get $get) => static::calculateAllTotals($set, $get)),
                                    Forms\Components\TextInput::make('local_transit_total')
                                        ->label('Subtotal Angkutan Setempat')
                                        ->numeric()
                                        ->prefix('Rp')
                                        ->disabled()
                                        ->dehydrated(),
                                ]),

                            Forms\Components\Grid::make(['default' => 1, 'md' => 3])
                                ->schema([
                                    Forms\Components\TextInput::make('pocket_money_qty')
                                        ->label('a.5 Uang Saku / Lumpsum')
                                        ->suffix('Hari')
                                        ->numeric()
                                        ->default(1)
                                        ->live(onBlur: true)
                                        ->afterStateUpdated(fn ($state, Forms\Set $set, Forms\Get $get) => static::calculateAllTotals($set, $get)),
                                    Forms\Components\TextInput::make('pocket_money_rate')
                                        ->label('Tarif Uang Saku')
                                        ->numeric()
                                        ->prefix('Rp')
                                        ->default(120000)
                                        ->live(onBlur: true)
                                        ->afterStateUpdated(fn ($state, Forms\Set $set, Forms\Get $get) => static::calculateAllTotals($set, $get)),
                                    Forms\Components\TextInput::make('pocket_money_total')
                                        ->label('Subtotal Uang Saku')
                                        ->numeric()
                                        ->prefix('Rp')
                                        ->disabled()
                                        ->dehydrated(),
                                ]),
                        ])
                        ->columns(1),

                    Forms\Components\Section::make('Komponen Luar Negeri (Jika Ada)')
                        ->icon('heroicon-o-globe-alt')
                        ->iconColor('warning')
                        ->description('Isi bagian ini hanya untuk penugasan perjalanan dinas luar negeri.')
                        ->schema([
                            Forms\Components\TextInput::make('overseas_daily_allowance')
                                ->label('b. Daily Allowance LN')
                                ->numeric()->prefix('Rp')->default(0)->live(onBlur: true)
                                ->afterStateUpdated(fn ($state, Forms\Set $set, Forms\Get $get) => static::calculateAllTotals($set, $get)),
                            Forms\Components\TextInput::make('overseas_hotel_meal')
                                ->label('c.1 Penginapan & Makan LN')
                                ->numeric()->prefix('Rp')->default(0)->live(onBlur: true)
                                ->afterStateUpdated(fn ($state, Forms\Set $set, Forms\Get $get) => static::calculateAllTotals($set, $get)),
                            Forms\Components\TextInput::make('overseas_transport')
                                ->label('c.2 Transport Lokal LN')
                                ->numeric()->prefix('Rp')->default(0)->live(onBlur: true)
                                ->afterStateUpdated(fn ($state, Forms\Set $set, Forms\Get $get) => static::calculateAllTotals($set, $get)),
                            Forms\Components\TextInput::make('overseas_pocket_money')
                                ->label('c.3 Uang Saku LN')
                                ->numeric()->prefix('Rp')->default(0)->live(onBlur: true)
                                ->afterStateUpdated(fn ($state, Forms\Set $set, Forms\Get $get) => static::calculateAllTotals($set, $get)),
                            Forms\Components\TextInput::make('overseas_monthly_allowance')
                                ->label('d. Monthly Allowance LN')
                                ->numeric()->prefix('Rp')->default(0)->live(onBlur: true)
                                ->afterStateUpdated(fn ($state, Forms\Set $set, Forms\Get $get) => static::calculateAllTotals($set, $get)),
                        ])
                        ->columns(['default' => 1, 'md' => 2])
                        ->collapsed(),

                    Forms\Components\TextInput::make('subtotal_per_diem')
                        ->label('Jumlah Jenis Biaya Harian')
                        ->prefixIcon('heroicon-m-calculator')
                        ->numeric()
                        ->prefix('Rp')
                        ->disabled()
                        ->dehydrated()
                        ->extraAttributes(['class' => 'font-bold']),
                ]),

            // 3. BIAYA LAIN
            Forms\Components\Section::make('3. Biaya Lain-Lain')
                ->icon('heroicon-o-receipt-percent')
                ->iconColor('warning')
                ->description('Biaya pendukung lain yang sah dan dapat dipertanggungjawabkan.')
                ->collapsible()
                ->schema([
                    Forms\Components\Fieldset::make('Rincian Komponen Biaya Lain')
                        ->schema([
                            Forms\Components\TextInput::make('other_airport_tax')
                                ->label('a. Airport Tax')
                                ->numeric()->prefix('Rp')->default(0)->live(onBlur: true)
                                ->afterStateUpdated(fn ($state, Forms\Set $set, Forms\Get $get) => static::calculateAllTotals($set, $get)),
                            Forms\Components\TextInput::make('other_laundry')
                                ->label('b. Cuci Pakaian (Laundry)')
                                ->numeric()->prefix('Rp')->default(0)->live(onBlur: true)
                                ->afterStateUpdated(fn ($state, Forms\Set $set, Forms\Get $get) => static::calculateAllTotals($set, $get)),
                            Forms\Components\TextInput::make('other_equipment')
                                ->label('c. Tunjangan Perlengkapan LN')
                                ->numeric()->prefix('Rp')->default(0)->live(onBlur: true)
                                ->afterStateUpdated(fn ($state, Forms\Set $set, Forms\Get $get) => static::calculateAllTotals($set, $get)),
                            Forms\Components\TextInput::make('other_book_paper')
                                ->label('d. Uang Buku & Karya Tulis')
                                ->numeric()->prefix('Rp')->default(0)->live(onBlur: true)
                                ->afterStateUpdated(fn ($state, Forms\Set $set, Forms\Get $get) => static::calculateAllTotals($set, $get)),
                            Forms\Components\TextInput::make('other_assessor')
                                ->label('e. Jamuan untuk Tim Assesor')
                                ->numeric()->prefix('Rp')->default(0)->live(onBlur: true)
                                ->afterStateUpdated(fn ($state, Forms\Set $set, Forms\Get $get) => static::calculateAllTotals($set, $get)),
                            Forms\Components\TextInput::make('other_car_rental')
                                ->label('f. Sewa Mobil')
                                ->numeric()->prefix('Rp')->default(0)->live(onBlur: true)
                                ->afterStateUpdated(fn ($state, Forms\Set $set, Forms\Get $get) => static::calculateAllTotals($set, $get)),
                            Forms\Components\TextInput::make('other_baggage')
                                ->label('g. Over Baggage')
                                ->numeric()->prefix('Rp')->default(0)->live(onBlur: true)
                                ->afterStateUpdated(fn ($state, Forms\Set $set, Forms\Get $get) => static::calculateAllTotals($set, $get)),
                            Forms\Components\TextInput::make('other_toll_fuel_parking')
                                ->label('h. Tol, BBM dan Parkir')
                                ->numeric()->prefix('Rp')->default(0)->live(onBlur: true)
                                ->afterStateUpdated(fn ($state, Forms\Set $set, Forms\Get $get) => static::calculateAllTotals($set, $get)),
                        ])
                        ->columns(['default' => 1, 'md' => 2]),

                    Forms\Components\TextInput::make('subtotal_other')
                        ->label('Jumlah Biaya Lain-Lain')
                        ->prefixIcon('heroicon-m-calculator')
                        ->numeric()
                        ->prefix('Rp')
                        ->disabled()
                        ->dehydrated()
                        ->extraAttributes(['class' => 'font-bold']),
                ]),

            // 4. RINGKASAN TOTAL
            Forms\Components\Section::make('Ringkasan Akhir & Total Biaya')
                ->icon('heroicon-o-check-badge')
                ->iconColor('success')
                ->description('Total keseluruhan biaya perjalanan dinas (Transport + Biaya Harian + Biaya Lain) serta catatan tambahan.')
                ->schema([
                    Forms\Components\TextInput::make('total_cost')
                        ->label('Total Biaya')
                        ->prefixIcon('heroicon-m-banknotes')
                        ->numeric()
                        ->prefix('Rp')
                        ->disabled()
                        ->dehydrated()
                        ->required()
                        ->extraAttributes(['class' => 'text-lg font-bold text-success-600 dark:text-success-400']),
                    Forms\Components\Textarea::make('notes')
                        ->label('Catatan Tambahan')
                        ->placeholder('Tambahkan catatan atau keterangan khusus perhitungan ini...')
                        ->rows(3),
                    static::evidenceUpload(
                        'general_evidence',
                        'Bukti / Evidence Biaya SPPD (Foto atau PDF)',
                        'sppd-cost-evidences/general',
                        'total_cost',
                        'Bukti / evidence Biaya SPPD (foto/PDF) wajib diunggah jika total biaya lebih dari Rp 0.'
                    )->columnSpanFull(),
                ])
                ->columns(['default' => 1, 'md' => 2]),
        ];
    }

    protected static function evidenceUpload(
        string $field,
        string $label,
        string $directory,
        string $amountField,
        string $requiredMessage
    ): Forms\Components\FileUpload {
        return Forms\Components\FileUpload::make($field)
            ->label($label)
            ->multiple()
            ->disk('public')
            ->directory($directory)
            ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp', 'application/pdf'])
            ->maxSize(5120)
            ->openable()
            ->downloadable()
            ->helperText('Unggah foto (JPG/PNG) atau file PDF maksimal 5 MB per file. Wajib diisi apabila nominal komponen > Rp 0.')
            ->required(fn (Forms\Get $get): bool => (float) ($get($amountField) ?? 0) > 0)
            ->validationMessages([
                'required' => $requiredMessage,
            ]);
    }

    public static function form(Form $form): Form
    {
        return $form->schema(static::getCostFormSchema());
    }

    public static function table(Table $table): Table
    {
        return $table
            ->contentGrid(['default' => 1, 'xl' => 1])
            ->columns([
                Tables\Columns\Layout\Split::make([
                    Tables\Columns\TextColumn::make('employee.name')
                        ->label('Pegawai')
                        ->weight('bold')
                        ->description(function (Sppd $record) {
                            $pos = $record->employee?->position?->name ?? 'Pegawai';
                            $nip = $record->employee?->identity_number;
                            return $nip ? "{$pos} • {$nip}" : $pos;
                        })
                        ->searchable(),
                    Tables\Columns\TextColumn::make('businessTrip.memo.nomor_memo')
                        ->label('Nomor Memo')
                        ->badge()
                        ->color('gray')
                        ->placeholder('Tanpa Memo')
                        ->searchable()
                        ->grow(false),
                    Tables\Columns\TextColumn::make('destination_location')
                        ->label('Tujuan')
                        ->icon('heroicon-m-map-pin')
                        ->placeholder('-')
                        ->searchable(),
                    Tables\Columns\TextColumn::make('cost_status')
                        ->label('Status Biaya')
                        ->badge()
                        ->grow(false)
                        ->state(function (Sppd $record): string {
                            if (! $record->cost) {
                                return 'Belum Dihitung';
                            }

                            return match ($record->cost->status) {
                                'approved' => 'Disetujui Manajer',
                                'pending' => 'Menunggu Manajer',
                                'rejected' => 'Ditolak',
                                default => 'Draf',
                            };
                        })
                        ->color(function (Sppd $record): string {
                            if (! $record->cost) {
                                return 'warning';
                            }

                            return match ($record->cost->status) {
                                'approved' => 'success',
                                'pending' => 'info',
                                'rejected' => 'danger',
                                default => 'gray',
                            };
                        })
                        ->icon(function (Sppd $record): string {
                            if (! $record->cost) {
                                return 'heroicon-m-clock';
                            }

                            return match ($record->cost->status) {
                                'approved' => 'heroicon-m-check-badge',
                                'pending' => 'heroicon-m-clock',
                                'rejected' => 'heroicon-m-x-circle',
                                default => 'heroicon-m-document',
                            };
                        }),
                    Tables\Columns\TextColumn::make('total_cost')
                        ->label('Total Biaya')
                        ->weight('bold')
                        ->grow(false)
                        ->alignEnd()
                        ->state(fn (Sppd $record) => $record->cost ? 'Rp ' . number_format($record->cost->total_cost, 0, ',', '.') : '-'),
                ])->from('md'),

                Tables\Columns\Layout\Panel::make([
                    Tables\Columns\Layout\Grid::make(['default' => 1, 'sm' => 2, 'md' => 4])
                        ->schema([
                            Tables\Columns\TextColumn::make('sppd_number')
                                ->description('Nomor SPPD', position: 'above')
                                ->weight('medium')
                                ->placeholder('-'),
                            Tables\Columns\TextColumn::make('agenda')
                                ->description('Agenda Penugasan', position: 'above')
                                ->state(fn (Sppd $record) => $record->assignment_purpose ?: ($record->businessTrip?->memo?->agenda ?? '-')),
                            Tables\Columns\TextColumn::make('period')
                                ->description('Jadwal Dinas', position: 'above')
                                ->state(function (Sppd $record) {
                                    $start = $record->departure_date ? \Carbon\Carbon::parse($record->departure_date)->format('d M Y') : '-';
                                    $end = $record->return_date ? \Carbon\Carbon::parse($record->return_date)->format('d M Y') : '-';
                                    $days = $record->total_days ?? 1;
                                    return "{$start} s/d {$end} ({$days} Hari)";
                                }),
                            Tables\Columns\TextColumn::make('cost_breakdown')
                                ->description('Rincian Biaya (Transport • Harian • Lain)', position: 'above')
                                ->state(function (Sppd $record) {
                                    if (! $record->cost) {
                                        return 'Belum dihitung';
                                    }
                                    $t = number_format($record->cost->subtotal_transport, 0, ',', '.');
                                    $h = number_format($record->cost->subtotal_per_diem, 0, ',', '.');
                                    $l = number_format($record->cost->subtotal_other, 0, ',', '.');
                                    return "Rp {$t} • Rp {$h} • Rp {$l}";
                                }),
                        ]),
                ])->collapsible(),
            ])
            ->defaultSort('id', 'desc')
            ->groups([
                Tables\Grouping\Group::make('businessTrip.memo.nomor_memo')
                    ->label('Nomor Memo')
                    ->collapsible(),
            ])
            ->filters([
                \App\Filament\Admin\Support\RecapTableSupport::monthYearFilter('departure_date'),
                Tables\Filters\SelectFilter::make('business_trip_id')
                    ->label('Nomor Memo')
                    ->options(function (): array {
                        $user = \Filament\Facades\Filament::auth()->user() ?? auth()->user();
                        $areaId = ($user && ! $user->hasAnyRole(['Superadmin', 'Admin'])) ? $user->employee?->work_area_id : null;

                        return \App\Models\BusinessTrip::whereHas('sppds', function (Builder $q) use ($areaId) {
                            $q->where('status', 'approved');
                            if ($areaId) {
                                $q->whereHas('employee', fn (Builder $eq) => $eq->where('work_area_id', $areaId));
                            }
                        })
                            ->with(['memo'])
                            ->withCount(['sppds as approved_sppds_count' => function (Builder $q) use ($areaId) {
                                $q->where('status', 'approved');
                                if ($areaId) {
                                    $q->whereHas('employee', fn (Builder $eq) => $eq->where('work_area_id', $areaId));
                                }
                            }])
                            ->orderByDesc('id')
                            ->get()
                            ->mapWithKeys(function (\App\Models\BusinessTrip $trip): array {
                                $noMemo = $trip->memo?->nomor_memo ?: ('Trip #' . $trip->id);
                                $agenda = $trip->memo?->agenda ?: ($trip->activity_name ?: '-');
                                return [$trip->id => "{$noMemo} — {$agenda} ({$trip->approved_sppds_count} Peserta)"];
                            })
                            ->all();
                    })
                    ->searchable(),
                Tables\Filters\SelectFilter::make('base_type')
                    ->label('Kategori Tarif')
                    ->options(fn () => \App\Models\SppdRateStandard::getCategoryNames())
                    ->query(fn (Builder $query, array $data) => ! empty($data['value']) ? $query->whereHas('cost', fn ($q) => $q->where('base_type', $data['value'])) : $query),
            ])
            ->actions([
                Tables\Actions\Action::make('calculate')
                    ->label('Hitung Biaya')
                    ->icon('heroicon-o-calculator')
                    ->button()
                    ->size('sm')
                    ->color('primary')
                    ->visible(fn (Sppd $record) => $record->cost === null)
                    ->url(fn (Sppd $record) => static::getUrl('calculate', ['record' => $record])),
                Tables\Actions\Action::make('edit_cost')
                    ->label('Ubah Biaya')
                    ->icon('heroicon-o-pencil-square')
                    ->button()
                    ->size('sm')
                    ->color('warning')
                    ->visible(fn (Sppd $record) => $record->cost !== null)
                    ->url(fn (Sppd $record) => static::getUrl('calculate', ['record' => $record])),
                Tables\Actions\Action::make('bulk_memo_calculate')
                    ->label('Bulk per Memo')
                    ->icon('heroicon-o-squares-plus')
                    ->button()
                    ->size('sm')
                    ->color('success')
                    ->tooltip('Isi rincian biaya & evidence sekaligus untuk peserta dalam Nomor Memo yang sama')
                    ->visible(function (Sppd $record): bool {
                        if (! $record->business_trip_id) {
                            return false;
                        }
                        if ($record->relationLoaded('businessTrip') && $record->businessTrip && $record->businessTrip->relationLoaded('sppds')) {
                            return $record->businessTrip->sppds->where('status', 'approved')->count() > 1;
                        }

                        return Sppd::where('business_trip_id', $record->business_trip_id)
                            ->where('status', 'approved')
                            ->count() > 1;
                    })
                    ->url(fn (Sppd $record) => static::getUrl('calculate', ['record' => $record, 'bulk_memo' => 1])),
                Tables\Actions\Action::make('print')
                    ->label('Cetak')
                    ->icon('heroicon-o-printer')
                    ->button()
                    ->size('sm')
                    ->color('info')
                    ->visible(fn (Sppd $record) => $record->cost !== null)
                    ->url(fn (Sppd $record) => route('sppd-costs.print', $record->cost))
                    ->openUrlInNewTab(),
            ])
            ->bulkActions([]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSppdCosts::route('/'),
            'calculate' => Pages\CalculateSppdCost::route('/{record}/calculate'),
        ];
    }
}
