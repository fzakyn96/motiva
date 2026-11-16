<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\UserResource\Pages;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Hash;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static ?string $navigationGroup = 'Hak Akses & Pengguna';

    protected static ?string $navigationIcon = 'heroicon-o-users';
    protected static ?string $navigationLabel = 'Pengguna';
    protected static ?string $modelLabel = 'Pengguna';
    protected static ?string $pluralModelLabel = 'Pengguna';
    protected static ?int $navigationSort = 1;

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
        return 'primary';
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return 'Total Pengguna';
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Informasi Akun & Kredensial Pengguna')
                    ->icon('heroicon-o-user-circle')
                    ->iconColor('primary')
                    ->description('Kelola identitas login pengguna, peran akses (role), dan kata sandi keamanan.')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->label('Nama Lengkap Pengguna')
                            ->prefixIcon('heroicon-m-user')
                            ->placeholder('Masukkan nama lengkap pengguna')
                            ->required()
                            ->maxLength(255),
                        Forms\Components\TextInput::make('username')
                            ->label('Username Login')
                            ->prefixIcon('heroicon-m-at-symbol')
                            ->placeholder('contoh: budi.santoso')
                            ->helperText('Dapat digunakan untuk login selain menggunakan alamat email.')
                            ->regex('/^[A-Za-z0-9._-]+$/')
                            ->unique(ignoreRecord: true)
                            ->maxLength(100),
                        Forms\Components\TextInput::make('email')
                            ->label('Alamat Email Login')
                            ->prefixIcon('heroicon-m-envelope')
                            ->placeholder('nama@perusahaan.co.id')
                            ->email()
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->maxLength(255),
                        Forms\Components\Select::make('roles')
                            ->label('Peran Sistem (Role)')
                            ->prefixIcon('heroicon-m-shield-check')
                            ->multiple()
                            ->relationship('roles', 'name')
                            ->preload()
                            ->helperText('Pilih satu atau lebih peran untuk menentukan hak akses menu pengguna.'),
                        Forms\Components\TextInput::make('password')
                            ->label('Kata Sandi')
                            ->prefixIcon('heroicon-m-lock-closed')
                            ->password()
                            ->revealable()
                            ->placeholder(fn (string $context): string => $context === 'edit' ? 'Kosongkan bila tidak diubah' : 'Masukkan kata sandi baru')
                            ->helperText(fn (string $context): string => $context === 'edit' ? 'Biarkan kosong jika tidak ingin mengganti kata sandi saat ini.' : 'Gunakan kata sandi yang kuat untuk keamanan akun.')
                            ->dehydrateStateUsing(fn ($state) => Hash::make($state))
                            ->dehydrated(fn ($state) => filled($state))
                            ->required(fn (string $context): bool => $context === 'create')
                            ->maxLength(255),
                    ])
                    ->columns(['default' => 1, 'md' => 2]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\Layout\Split::make([
                    Tables\Columns\TextColumn::make('name')
                        ->label('Nama')
                        ->searchable()
                        ->weight('bold'),
                    Tables\Columns\TextColumn::make('username')
                        ->label('Username')
                        ->placeholder('-')
                        ->searchable()
                        ->sortable()
                        ->color('gray'),
                    Tables\Columns\TextColumn::make('roles.name')
                        ->label('Peran')
                        ->badge()
                        ->searchable()
                        ->grow(false),
                ])->from('md'),

                Tables\Columns\Layout\Panel::make([
                    Tables\Columns\Layout\Grid::make(['default' => 1, 'sm' => 3])
                        ->schema([
                            Tables\Columns\TextColumn::make('email')
                                ->description('Email', position: 'above')
                                ->searchable(),
                            Tables\Columns\TextColumn::make('created_at')
                                ->description('Dibuat Pada', position: 'above')
                                ->dateTime('d/m/Y H:i')
                                ->sortable(),
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
            'index' => Pages\ManageUsers::route('/'),
        ];
    }
}
