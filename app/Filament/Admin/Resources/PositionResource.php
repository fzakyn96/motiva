<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Clusters\DataPegawai;
use App\Filament\Admin\Resources\PositionResource\Pages;
use App\Models\Position;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Pages\SubNavigationPosition;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class PositionResource extends Resource
{
    protected static ?string $model = Position::class;

    protected static ?string $cluster = DataPegawai::class;
    protected static SubNavigationPosition $subNavigationPosition = SubNavigationPosition::Top;

    protected static ?string $navigationIcon = 'heroicon-o-briefcase';
    protected static ?string $navigationLabel = 'Master Jabatan';
    protected static ?string $modelLabel = 'Jabatan';
    protected static ?string $pluralModelLabel = 'Jabatan';
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
        return 'gray';
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return 'Total Jabatan';
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Informasi Jabatan & Hirarki')
                    ->icon('heroicon-o-briefcase')
                    ->iconColor('primary')
                    ->description('Tentukan kode jabatan, nama jabatan, serta level hirarki otorisasi struktural.')
                    ->schema([
                        Forms\Components\TextInput::make('code')
                            ->label('Kode Jabatan')
                            ->prefixIcon('heroicon-m-hashtag')
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->maxLength(50)
                            ->placeholder('Contoh: MGR, SPV, HR, STF'),
                        Forms\Components\TextInput::make('name')
                            ->label('Nama Jabatan')
                            ->prefixIcon('heroicon-m-briefcase')
                            ->required()
                            ->maxLength(255)
                            ->placeholder('Contoh: Manager, Supervisor, HR Specialist, Staff'),
                        Forms\Components\Select::make('level')
                            ->label('Level / Tingkat Hirarki')
                            ->prefixIcon('heroicon-m-bars-3-bottom-left')
                            ->native(false)
                            ->options([
                                1 => 'Level 1 — Manager / Kepala Unit',
                                2 => 'Level 2 — Supervisor / Pengawas',
                                3 => 'Level 3 — HR / Spesialis',
                                4 => 'Level 4 — Staff / Pelaksana',
                            ])
                            ->helperText('Level hirarki menentukan wewenang persetujuan struktural dalam alur dokumen.')
                            ->required()
                            ->default(4)
                            ->columnSpanFull(),
                    ])
                    ->columns(['default' => 1, 'md' => 2]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('code')
                    ->label('Kode Jabatan')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),
                Tables\Columns\TextColumn::make('name')
                    ->label('Nama Jabatan')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('level')
                    ->label('Level Hirarki')
                    ->badge()
                    ->formatStateUsing(fn (int $state): string => match ($state) {
                        1 => 'Level 1 (Manager)',
                        2 => 'Level 2 (Supervisor)',
                        3 => 'Level 3 (HR)',
                        4 => 'Level 4 (Staff)',
                        default => "Level {$state}",
                    })
                    ->color(fn (int $state): string => match ($state) {
                        1 => 'danger',
                        2 => 'warning',
                        3 => 'info',
                        4 => 'gray',
                        default => 'gray',
                    })
                    ->sortable(),
                Tables\Columns\TextColumn::make('employees_count')
                    ->label('Jumlah Pegawai')
                    ->counts('employees')
                    ->badge()
                    ->color('primary')
                    ->sortable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Dibuat Pada')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
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
            'index' => Pages\ManagePositions::route('/'),
        ];
    }
}
