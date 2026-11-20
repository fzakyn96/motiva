<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\RoleResource\Pages;
use Spatie\Permission\Models\Role;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class RoleResource extends Resource
{
    protected static ?string $model = Role::class;

    protected static ?string $navigationGroup = 'Hak Akses & Pengguna';

    protected static ?string $navigationIcon = 'heroicon-o-shield-check';
    protected static ?string $navigationLabel = 'Peran (Role)';
    protected static ?string $modelLabel = 'Peran';
    protected static ?string $pluralModelLabel = 'Peran';
    protected static ?int $navigationSort = 2;

    public static function canViewAny(): bool
    {
        $user = \Filament\Facades\Filament::auth()->user() ?? auth()->user();
        if (! $user) return false;
        return $user->hasAnyRole(['Superadmin', 'Admin']);
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
        return 'Total Peran (Role)';
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Konfigurasi Peran & Izin Akses')
                    ->icon('heroicon-o-shield-check')
                    ->iconColor('primary')
                    ->description('Tentukan nama peran (role) serta daftar izin akses (permissions) yang diberikan pada peran ini.')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->label('Nama Peran (Role)')
                            ->prefixIcon('heroicon-m-shield-check')
                            ->placeholder('Contoh: Manager, Supervisor, HR, Staff')
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->maxLength(255),
                        Forms\Components\Select::make('permissions')
                            ->label('Daftar Izin Akses (Permissions)')
                            ->prefixIcon('heroicon-m-key')
                            ->multiple()
                            ->searchable()
                            ->relationship('permissions', 'name')
                            ->preload()
                            ->helperText('Pilih satu atau lebih izin akses operasional untuk peran ini.'),
                    ])
                    ->columns(1),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->contentGrid(['default' => 1, 'xl' => 2])
            ->columns([
                Tables\Columns\Layout\Split::make([
                    Tables\Columns\TextColumn::make('name')
                        ->label('Nama Peran')
                        ->searchable()
                        ->weight('bold'),
                    Tables\Columns\TextColumn::make('permissions_count')
                        ->label('Jumlah Izin')
                        ->counts('permissions')
                        ->badge()
                        ->color('primary')
                        ->formatStateUsing(fn ($state) => "{$state} Izin")
                        ->grow(false),
                ])->from('md'),

                Tables\Columns\Layout\Panel::make([
                    Tables\Columns\Layout\Grid::make(['default' => 1])
                        ->schema([
                            Tables\Columns\TextColumn::make('permissions.name')
                                ->description('Daftar Izin Akses', position: 'above')
                                ->badge()
                                ->searchable(),
                            Tables\Columns\TextColumn::make('updated_at')
                                ->description('Diperbarui Pada', position: 'above')
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
            'index' => Pages\ManageRoles::route('/'),
        ];
    }
}
