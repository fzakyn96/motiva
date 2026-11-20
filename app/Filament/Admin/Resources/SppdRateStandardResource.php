<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\SppdRateStandardResource\Pages;
use App\Models\SppdRateStandard;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class SppdRateStandardResource extends Resource
{
    protected static ?string $model = SppdRateStandard::class;

    protected static ?string $navigationIcon = 'heroicon-o-adjustments-horizontal';
    protected static ?string $navigationGroup = 'Keuangan';
    protected static ?string $navigationLabel = 'Tarif Standar SPPD';
    protected static ?string $slug = 'sppd-rate-standards';
    protected static ?string $modelLabel = 'Tarif Standar SPPD';
    protected static ?string $pluralModelLabel = 'Master Tarif Standar SPPD';
    protected static ?int $navigationSort = 3;

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
        return 'info';
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return 'Total Kategori Tarif Standar';
    }

    public static function canCreate(): bool
    {
        $user = \Filament\Facades\Filament::auth()->user() ?? auth()->user();
        if (! $user) return false;
        return $user->hasAnyRole(['Superadmin', 'Admin', 'HR']);
    }

    public static function canEdit(Model $record): bool
    {
        $user = \Filament\Facades\Filament::auth()->user() ?? auth()->user();
        if (! $user) return false;
        return $user->hasAnyRole(['Superadmin', 'Admin', 'HR']);
    }

    public static function canDelete(Model $record): bool
    {
        $user = \Filament\Facades\Filament::auth()->user() ?? auth()->user();
        if (! $user) return false;
        return $user->hasAnyRole(['Superadmin', 'Admin', 'HR']);
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Informasi Ketentuan & Regulasi')
                    ->icon('heroicon-o-document-check')
                    ->iconColor('primary')
                    ->description('Kode regulasi surat keputusan dan identitas kategori standar tarif.')
                    ->collapsible()
                    ->schema([
                        Forms\Components\Grid::make(['default' => 1, 'md' => 2])
                            ->schema([
                                Forms\Components\TextInput::make('regulation_code')
                                    ->label('Dasar Regulasi / SK')
                                    ->prefixIcon('heroicon-m-document-text')
                                    ->required()
                                    ->default('ST-JTK-05')
                                    ->placeholder('Contoh: ST-JTK-05')
                                    ->maxLength(50),
                                Forms\Components\TextInput::make('category_code')
                                    ->label('Kode Unik Kategori')
                                    ->prefixIcon('heroicon-m-hashtag')
                                    ->required()
                                    ->unique(ignoreRecord: true)
                                    ->placeholder('Contoh: office_base_stay, shift_base_stay')
                                    ->maxLength(50),
                            ]),

                        Forms\Components\TextInput::make('category_name')
                            ->label('Nama Kategori Standar')
                            ->prefixIcon('heroicon-m-tag')
                            ->required()
                            ->placeholder('Contoh: Office Base (≥ 80 km & Menginap)')
                            ->maxLength(255),

                        Forms\Components\Textarea::make('description')
                            ->label('Deskripsi / Cakupan Ketentuan')
                            ->placeholder('Tuliskan ringkasan ketentuan penugasan...')
                            ->rows(2),

                        Forms\Components\Grid::make(['default' => 1, 'md' => 2])
                            ->schema([
                                Forms\Components\Toggle::make('is_active')
                                    ->label('Status Aktif')
                                    ->helperText('Jika aktif, kategori ini akan muncul sebagai pilihan saat perhitungan biaya.')
                                    ->inline(false)
                                    ->default(true),
                                Forms\Components\TextInput::make('order_column')
                                    ->label('Urutan Tampilan')
                                    ->prefixIcon('heroicon-m-arrows-up-down')
                                    ->numeric()
                                    ->default(1),
                            ]),
                    ]),

                Forms\Components\Section::make('Komponen Biaya Harian')
                    ->icon('heroicon-o-banknotes')
                    ->iconColor('success')
                    ->description('Tarif uang makan, uang saku / lumpsum harian, uang angkutan setempat, dan penginapan.')
                    ->collapsible()
                    ->schema([
                        Forms\Components\Fieldset::make('Uang Makan & Uang Saku Harian')
                            ->schema([
                                Forms\Components\TextInput::make('meal_rate')
                                    ->label('Tarif Uang Makan / Hari')
                                    ->numeric()
                                    ->prefix('Rp')
                                    ->default(0)
                                    ->required(),
                                Forms\Components\Select::make('meal_default_rule')
                                    ->label('Aturan Jumlah Makan')
                                    ->prefixIcon('heroicon-m-calculator')
                                    ->options([
                                        'same_as_days' => 'Sesuai Jumlah Hari Dinas (1 x Hari)',
                                        'zero' => '0 / Termasuk dalam Lumpsum',
                                    ])
                                    ->default('same_as_days')
                                    ->required(),

                                Forms\Components\TextInput::make('pocket_money_rate')
                                    ->label('Tarif Uang Saku / Lumpsum / Hari')
                                    ->numeric()
                                    ->prefix('Rp')
                                    ->default(0)
                                    ->required(),
                                Forms\Components\Select::make('pocket_money_default_rule')
                                    ->label('Aturan Jumlah Uang Saku')
                                    ->prefixIcon('heroicon-m-calculator')
                                    ->options([
                                        'same_as_days' => 'Sesuai Jumlah Hari Dinas (1 x Hari)',
                                        'zero' => '0 / Tidak Disediakan',
                                    ])
                                    ->default('same_as_days')
                                    ->required(),
                            ])
                            ->columns(['default' => 1, 'md' => 2]),

                        Forms\Components\Fieldset::make('Angkutan Setempat & Akomodasi Penginapan')
                            ->schema([
                                Forms\Components\TextInput::make('local_transit_rate')
                                    ->label('Tarif Angkutan Setempat')
                                    ->numeric()
                                    ->prefix('Rp')
                                    ->default(0)
                                    ->required(),
                                Forms\Components\TextInput::make('local_transit_default_qty')
                                    ->label('Default Frekuensi Angkutan')
                                    ->suffix('Kali')
                                    ->numeric()
                                    ->default(1)
                                    ->required(),

                                Forms\Components\TextInput::make('hotel_rate')
                                    ->label('Plafon / Tarif Hotel per Malam')
                                    ->numeric()
                                    ->prefix('Rp')
                                    ->default(0)
                                    ->helperText('Isi 0 jika tarif hotel ditagihkan riil sesuai kuitansi penginapan.'),
                                Forms\Components\Select::make('hotel_default_rule')
                                    ->label('Aturan Jumlah Malam Hotel')
                                    ->prefixIcon('heroicon-m-building-office')
                                    ->options([
                                        'days_minus_one' => 'Hari Dinas - 1 (Menginap)',
                                        'same_as_days' => 'Sama dengan Jumlah Hari Dinas',
                                        'zero' => '0 / Sesuai Pengajuan Nyata',
                                    ])
                                    ->default('days_minus_one')
                                    ->required(),
                            ])
                            ->columns(['default' => 1, 'md' => 2]),
                    ]),

                Forms\Components\Section::make('Komponen Biaya Transportasi')
                    ->icon('heroicon-o-truck')
                    ->iconColor('info')
                    ->description('Tarif standar transportasi ke terminal/bandara dan sarana perjalanan.')
                    ->collapsible()
                    ->schema([
                        Forms\Components\Fieldset::make('Tarif Standar Transportasi')
                            ->schema([
                                Forms\Components\TextInput::make('transport_terminal_rate')
                                    ->label('Tarif Satuan Terminal / Bandara')
                                    ->numeric()
                                    ->prefix('Rp')
                                    ->default(0)
                                    ->required(),
                                Forms\Components\TextInput::make('transport_terminal_default_qty')
                                    ->label('Default Qty Terminal / Bandara')
                                    ->suffix('Kali')
                                    ->numeric()
                                    ->default(2)
                                    ->helperText('Default 2 untuk perjalanan pulang-pergi (PP).')
                                    ->required(),

                                Forms\Components\TextInput::make('transport_ticket_rate')
                                    ->label('Default Tarif Tiket Transportasi')
                                    ->numeric()
                                    ->prefix('Rp')
                                    ->default(0),
                                Forms\Components\TextInput::make('transport_local_rate')
                                    ->label('Default Tarif Transport di Tempat Tujuan')
                                    ->numeric()
                                    ->prefix('Rp')
                                    ->default(0),
                            ])
                            ->columns(['default' => 1, 'md' => 2]),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->contentGrid(['default' => 1, 'xl' => 1])
            ->columns([
                Tables\Columns\Layout\Split::make([
                    Tables\Columns\TextColumn::make('category_name')
                        ->label('Kategori Standar')
                        ->weight('bold')
                        ->description(fn (SppdRateStandard $record) => "SK: {$record->regulation_code} • Kode: {$record->category_code}")
                        ->searchable(),
                    Tables\Columns\TextColumn::make('rates_summary')
                        ->label('Ringkasan Tarif')
                        ->state(function (SppdRateStandard $record) {
                            $parts = [];
                            if ($record->meal_rate > 0) $parts[] = 'Makan: Rp ' . number_format($record->meal_rate, 0, ',', '.');
                            if ($record->pocket_money_rate > 0) $parts[] = 'Saku: Rp ' . number_format($record->pocket_money_rate, 0, ',', '.');
                            if ($record->local_transit_rate > 0) $parts[] = 'Angkutan: Rp ' . number_format($record->local_transit_rate, 0, ',', '.');
                            if ($record->transport_terminal_rate > 0) $parts[] = 'Terminal: Rp ' . number_format($record->transport_terminal_rate, 0, ',', '.');
                            return ! empty($parts) ? implode(' • ', $parts) : 'Fleksibel / Sesuai Rincian Riil';
                        })
                        ->grow(false),
                    Tables\Columns\IconColumn::make('is_active')
                        ->label('Status')
                        ->boolean()
                        ->grow(false),
                ])->from('md'),

                Tables\Columns\Layout\Panel::make([
                    Tables\Columns\Layout\Grid::make(['default' => 1, 'sm' => 2, 'md' => 4])
                        ->schema([
                            Tables\Columns\TextColumn::make('transport_terminal_info')
                                ->description('Transport Terminal/Bandara', position: 'above')
                                ->state(fn (SppdRateStandard $record) => 'Rp ' . number_format($record->transport_terminal_rate, 0, ',', '.') . " ({$record->transport_terminal_default_qty}x)"),
                            Tables\Columns\TextColumn::make('meal_info')
                                ->description('Uang Makan per Hari', position: 'above')
                                ->state(fn (SppdRateStandard $record) => 'Rp ' . number_format($record->meal_rate, 0, ',', '.')),
                            Tables\Columns\TextColumn::make('pocket_money_info')
                                ->description('Uang Saku / Lumpsum', position: 'above')
                                ->state(fn (SppdRateStandard $record) => 'Rp ' . number_format($record->pocket_money_rate, 0, ',', '.')),
                            Tables\Columns\TextColumn::make('hotel_info')
                                ->description('Plafon Penginapan / Malam', position: 'above')
                                ->state(fn (SppdRateStandard $record) => $record->hotel_rate > 0 ? 'Rp ' . number_format($record->hotel_rate, 0, ',', '.') : 'Sesuai Kuitansi Riil'),
                        ]),
                ])->collapsible(),
            ])
            ->defaultSort('order_column')
            ->actions([
                Tables\Actions\EditAction::make()
                    ->label('Ubah')
                    ->icon('heroicon-o-pencil-square')
                    ->button()
                    ->size('sm')
                    ->color('warning'),
                Tables\Actions\DeleteAction::make()
                    ->label('Hapus')
                    ->icon('heroicon-o-trash')
                    ->button()
                    ->size('sm'),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->label('Hapus Terpilih')
                        ->icon('heroicon-o-trash'),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSppdRateStandards::route('/'),
            'create' => Pages\CreateSppdRateStandard::route('/create'),
            'edit' => Pages\EditSppdRateStandard::route('/{record}/edit'),
        ];
    }
}
