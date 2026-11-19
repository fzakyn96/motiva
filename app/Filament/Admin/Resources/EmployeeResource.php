<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\EmployeeResource\Pages;
use App\Models\Employee;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class EmployeeResource extends Resource
{
    protected static ?string $model = Employee::class;

    protected static ?string $navigationGroup = 'Data Pegawai';

    protected static ?string $navigationIcon = 'heroicon-o-identification';
    protected static ?string $navigationLabel = 'Data Pegawai';
    protected static ?string $modelLabel = 'Pegawai';
    protected static ?string $pluralModelLabel = 'Pegawai';
    protected static ?int $navigationSort = 1;

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
        return 'primary';
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return 'Total Pegawai';
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Identitas & Penempatan Pegawai')
                    ->icon('heroicon-o-identification')
                    ->iconColor('primary')
                    ->description('Lengkapi data identitas personal pegawai, struktur unit kerja, jabatan, serta akun pengguna terkait.')
                    ->schema([
                        Forms\Components\Fieldset::make('Identitas Personal')
                            ->schema([
                                Forms\Components\TextInput::make('identity_number')
                                    ->label('NIP / Nomor Identitas Pegawai')
                                    ->prefixIcon('heroicon-m-identification')
                                    ->placeholder('Contoh: PKJ-2026001')
                                    ->required()
                                    ->unique(ignoreRecord: true)
                                    ->maxLength(255),
                                Forms\Components\TextInput::make('name')
                                    ->label('Nama Lengkap Pegawai')
                                    ->prefixIcon('heroicon-m-user')
                                    ->placeholder('Masukkan nama lengkap beserta gelar jika ada')
                                    ->required()
                                    ->maxLength(255),
                            ])
                            ->columns(['default' => 1, 'md' => 2]),

                        Forms\Components\Fieldset::make('Penempatan Organisasi & Akun Sistem')
                            ->schema([
                                Forms\Components\Select::make('organization_id')
                                    ->label('Perusahaan (PGN / Perkasa)')
                                    ->prefixIcon('heroicon-m-building-library')
                                    ->relationship('organization', 'name')
                                    ->default(fn () => \App\Models\Organization::where('code', 'PKJ')->value('id') ?? \App\Models\Organization::first()?->id)
                                    ->required()
                                    ->searchable()
                                    ->preload(),
                                Forms\Components\Select::make('department_id')
                                    ->label('Unit Kerja / Departemen')
                                    ->prefixIcon('heroicon-m-building-office-2')
                                    ->relationship('department', 'name')
                                    ->default(fn () => \App\Models\Department::where('code', 'MESI')->value('id') ?? \App\Models\Department::first()?->id)
                                    ->required()
                                    ->searchable()
                                    ->preload(),
                                Forms\Components\Select::make('work_area_id')
                                    ->label('Area Kerja')
                                    ->prefixIcon('heroicon-m-map-pin')
                                    ->relationship('workArea', 'name')
                                    ->placeholder('Pilih Area Kerja')
                                    ->searchable()
                                    ->preload(),
                                Forms\Components\Select::make('position_id')
                                    ->label('Jabatan Struktural / Fungsional')
                                    ->prefixIcon('heroicon-m-briefcase')
                                    ->relationship('position', 'name')
                                    ->required()
                                    ->searchable()
                                    ->preload(),
                                Forms\Components\Select::make('manager_id')
                                    ->label('Atasan Langsung (Manager / Supervisor)')
                                    ->prefixIcon('heroicon-m-user-circle')
                                    ->relationship('manager', 'name')
                                    ->placeholder('Pilih atasan langsung')
                                    ->searchable()
                                    ->preload(),
                                Forms\Components\Select::make('user_id')
                                    ->label('Akun Pengguna Terkait')
                                    ->prefixIcon('heroicon-m-envelope')
                                    ->relationship('user', 'email')
                                    ->placeholder('Pilih akun login terkait')
                                    ->searchable()
                                    ->preload(),
                            ])
                            ->columns(['default' => 1, 'md' => 2]),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\Layout\Split::make([
                    Tables\Columns\TextColumn::make('name')
                        ->label('Nama Pegawai')
                        ->searchable()
                        ->sortable()
                        ->weight('bold'),
                    Tables\Columns\TextColumn::make('identity_number')
                        ->label('NIP')
                        ->searchable()
                        ->sortable()
                        ->color('gray'),
                    Tables\Columns\TextColumn::make('workArea.name')
                        ->label('Area Kerja')
                        ->placeholder('MESI')
                        ->badge()
                        ->color(fn (?string $state): string => match ($state) {
                            'Jakarta' => 'success',
                            'Regional 1' => 'info',
                            'Regional 2' => 'warning',
                            'Regional 3' => 'primary',
                            'Subholding Gas (SHG)' => 'danger',
                            default => 'gray',
                        })
                        ->searchable()
                        ->sortable()
                        ->grow(false),
                ])->from('md'),

                Tables\Columns\Layout\Panel::make([
                    Tables\Columns\Layout\Grid::make(['default' => 1, 'sm' => 2, 'md' => 3])
                        ->schema([
                            Tables\Columns\TextColumn::make('organization.name')
                                ->description('Perusahaan', position: 'above')
                                ->placeholder('-')
                                ->searchable()
                                ->sortable(),
                            Tables\Columns\TextColumn::make('department.name')
                                ->description('Unit Kerja', position: 'above')
                                ->placeholder('-')
                                ->searchable()
                                ->sortable(),
                            Tables\Columns\TextColumn::make('position.name')
                                ->description('Jabatan', position: 'above')
                                ->badge()
                                ->color('gray')
                                ->searchable()
                                ->sortable(),
                            Tables\Columns\TextColumn::make('manager.name')
                                ->description('Atasan Langsung', position: 'above')
                                ->placeholder('-')
                                ->searchable()
                                ->sortable(),
                            Tables\Columns\TextColumn::make('workArea.coordinator.name')
                                ->description('Koordinator Area Kerja', position: 'above')
                                ->placeholder('-')
                                ->searchable(),
                            Tables\Columns\TextColumn::make('user.email')
                                ->description('Email Akun', position: 'above')
                                ->placeholder('-')
                                ->searchable()
                                ->sortable(),
                            Tables\Columns\TextColumn::make('user.roles.name')
                                ->description('Peran (Role)', position: 'above')
                                ->badge()
                                ->color('primary')
                                ->searchable(),
                        ]),
                ])->collapsible(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('organization_id')
                    ->relationship('organization', 'name')
                    ->label('Perusahaan'),
                Tables\Filters\SelectFilter::make('department_id')
                    ->relationship('department', 'name')
                    ->label('Unit Kerja'),
                Tables\Filters\SelectFilter::make('work_area_id')
                    ->relationship('workArea', 'name')
                    ->label('Area Kerja'),
                Tables\Filters\SelectFilter::make('position_id')
                    ->relationship('position', 'name')
                    ->label('Jabatan'),
            ])
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
                    ->size('sm')
                    ->color('danger'),
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
            'index' => Pages\ManageEmployees::route('/'),
        ];
    }
}
