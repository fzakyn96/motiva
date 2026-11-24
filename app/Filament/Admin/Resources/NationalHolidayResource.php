<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\NationalHolidayResource\Pages;
use App\Models\NationalHoliday;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class NationalHolidayResource extends Resource
{
    protected static ?string $model = NationalHoliday::class;

    protected static ?string $navigationIcon = 'heroicon-o-calendar-days';

    protected static ?string $navigationGroup = 'Pengaturan';

    protected static ?string $navigationLabel = 'Hari Libur Nasional';

    protected static ?string $slug = 'national-holidays';

    protected static ?string $modelLabel = 'Hari Libur Nasional';

    protected static ?string $pluralModelLabel = 'Master Hari Libur Nasional';

    protected static ?int $navigationSort = 5;

    public static function canViewAny(): bool
    {
        $user = (\Filament\Facades\Filament::getCurrentPanel() ? \Filament\Facades\Filament::auth()->user() : null) ?? auth()->user();

        return (bool) $user?->hasAnyRole(['Superadmin', 'Admin', 'HR']);
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Detail Hari Libur Nasional')
                    ->schema([
                        Forms\Components\DatePicker::make('holiday_date')
                            ->label('Tanggal Libur')
                            ->native(false)
                            ->displayFormat('d F Y')
                            ->required()
                            ->unique(ignoreRecord: true),
                        Forms\Components\TextInput::make('name')
                            ->label('Nama Hari Libur')
                            ->placeholder('Contoh: Wafat Yesus Kristus / Hari Raya Idul Fitri')
                            ->required()
                            ->maxLength(255),
                        Forms\Components\Toggle::make('is_active')
                            ->label('Aktif untuk Perhitungan Lembur (L)')
                            ->default(true),
                        Forms\Components\Textarea::make('notes')
                            ->label('Keterangan')
                            ->rows(2)
                            ->columnSpanFull(),
                    ])
                    ->columns(['default' => 1, 'md' => 2]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->contentGrid(['default' => 1, 'xl' => 1])
            ->defaultSort('holiday_date', 'asc')
            ->columns([
                Tables\Columns\Layout\Split::make([
                    Tables\Columns\TextColumn::make('holiday_date')
                        ->label('Tanggal')
                        ->date('d M Y')
                        ->badge()
                        ->color('info')
                        ->sortable()
                        ->grow(false),
                    Tables\Columns\TextColumn::make('name')
                        ->label('Nama Hari Libur')
                        ->searchable()
                        ->weight('bold'),
                    Tables\Columns\TextColumn::make('is_active')
                        ->label('Status')
                        ->badge()
                        ->formatStateUsing(fn (bool $state): string => $state ? 'Aktif (L)' : 'Nonaktif')
                        ->color(fn (bool $state): string => $state ? 'success' : 'gray')
                        ->grow(false),
                ])->from('md'),

                Tables\Columns\Layout\Panel::make([
                    Tables\Columns\Layout\Grid::make(['default' => 1, 'sm' => 2])
                        ->schema([
                            Tables\Columns\TextColumn::make('notes')
                                ->description('Keterangan / Sumber', position: 'above')
                                ->placeholder('-'),
                            Tables\Columns\TextColumn::make('created_at')
                                ->description('Ditambahkan Pada', position: 'above')
                                ->dateTime('d/m/Y H:i'),
                        ]),
                ])->collapsible(),
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->button()
                    ->size('sm')
                    ->color('primary'),
                Tables\Actions\DeleteAction::make()
                    ->button()
                    ->size('sm'),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ManageNationalHolidays::route('/'),
        ];
    }
}
