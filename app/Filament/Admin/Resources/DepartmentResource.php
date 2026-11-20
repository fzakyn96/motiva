<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\DepartmentResource\Pages;
use App\Models\Department;
use App\Models\Organization;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class DepartmentResource extends Resource
{
    protected static ?string $model = Department::class;

    protected static ?string $navigationGroup = 'Data Pegawai';

    protected static ?string $navigationIcon = 'heroicon-o-building-office-2';
    protected static ?string $navigationLabel = 'Master Unit Kerja';
    protected static ?string $modelLabel = 'Unit Kerja';
    protected static ?string $pluralModelLabel = 'Unit Kerja';
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
        return 'info';
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return 'Total Unit Kerja';
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Informasi Unit Kerja')
                    ->icon('heroicon-o-building-office-2')
                    ->iconColor('primary')
                    ->description('Atur organisasi induk, kode unik, serta nama unit kerja / departemen.')
                    ->schema([
                        Forms\Components\Select::make('organization_id')
                            ->label('Organisasi Induk')
                            ->prefixIcon('heroicon-m-building-library')
                            ->relationship('organization', 'name')
                            ->default(fn () => Organization::first()?->id)
                            ->required()
                            ->searchable()
                            ->preload()
                            ->columnSpanFull(),
                        Forms\Components\TextInput::make('code')
                            ->label('Kode Unit Kerja')
                            ->prefixIcon('heroicon-m-hashtag')
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->maxLength(50)
                            ->placeholder('Contoh: MESI, REG1'),
                        Forms\Components\TextInput::make('name')
                            ->label('Nama Unit Kerja')
                            ->prefixIcon('heroicon-m-building-office-2')
                            ->required()
                            ->maxLength(255)
                            ->placeholder('Contoh: MESI, Regional 1'),
                    ])
                    ->columns(['default' => 1, 'md' => 2]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->contentGrid(['default' => 1, 'xl' => 2])
            ->columns([
                Tables\Columns\Layout\Split::make([
                    Tables\Columns\TextColumn::make('code')
                        ->label('Kode Unit')
                        ->searchable()
                        ->sortable()
                        ->weight('bold')
                        ->grow(false),
                    Tables\Columns\TextColumn::make('name')
                        ->label('Nama Unit Kerja')
                        ->searchable()
                        ->sortable(),
                    Tables\Columns\TextColumn::make('employees_count')
                        ->label('Jumlah Pegawai')
                        ->counts('employees')
                        ->badge()
                        ->color('info')
                        ->formatStateUsing(fn ($state) => "{$state} Pegawai")
                        ->sortable()
                        ->grow(false),
                ])->from('md'),

                Tables\Columns\Layout\Panel::make([
                    Tables\Columns\Layout\Grid::make(['default' => 1, 'sm' => 2])
                        ->schema([
                            Tables\Columns\TextColumn::make('organization.name')
                                ->description('Organisasi', position: 'above')
                                ->placeholder('-')
                                ->searchable()
                                ->sortable(),
                            Tables\Columns\TextColumn::make('created_at')
                                ->description('Dibuat Pada', position: 'above')
                                ->dateTime('d/m/Y H:i')
                                ->sortable(),
                        ]),
                ])->collapsible(),
            ])
            ->filters([
                //
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
            'index' => Pages\ManageDepartments::route('/'),
        ];
    }
}
