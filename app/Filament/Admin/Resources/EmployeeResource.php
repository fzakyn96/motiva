<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\EmployeeResource\Pages;
use App\Models\Employee;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Pages\SubNavigationPosition;
use App\Filament\Admin\Clusters\DataPegawai;

class EmployeeResource extends Resource
{
    protected static ?string $model = Employee::class;

    protected static ?string $cluster = DataPegawai::class;
    protected static SubNavigationPosition $subNavigationPosition = SubNavigationPosition::Top;

    protected static ?string $navigationIcon = 'heroicon-o-identification';
    protected static ?string $navigationLabel = 'Data Pegawai';
    protected static ?string $modelLabel = 'Pegawai';
    protected static ?string $pluralModelLabel = 'Pegawai';
    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('identity_number')
                    ->label('NIP / Nomor Identitas Pegawai')
                    ->required()
                    ->unique(ignoreRecord: true)
                    ->maxLength(255),
                Forms\Components\TextInput::make('name')
                    ->label('Nama Lengkap')
                    ->required()
                    ->maxLength(255),
                Forms\Components\Select::make('department_id')
                    ->label('Unit Kerja / Departemen')
                    ->relationship('department', 'name')
                    ->required()
                    ->searchable()
                    ->preload(),
                Forms\Components\Select::make('position_id')
                    ->label('Jabatan')
                    ->relationship('position', 'name')
                    ->required()
                    ->searchable()
                    ->preload(),
                Forms\Components\Select::make('manager_id')
                    ->label('Atasan Langsung (Manager)')
                    ->relationship('manager', 'name')
                    ->searchable()
                    ->preload(),
                Forms\Components\Select::make('user_id')
                    ->label('Akun Pengguna Terkait')
                    ->relationship('user', 'email')
                    ->searchable()
                    ->preload(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('identity_number')
                    ->label('NIP')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),
                Tables\Columns\TextColumn::make('name')
                    ->label('Nama Pegawai')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('department.name')
                    ->label('Unit Kerja')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'MESI' => 'info',
                        'Regional 1' => 'success',
                        'Regional 2' => 'warning',
                        'Regional 3' => 'primary',
                        default => 'gray',
                    })
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('position.name')
                    ->label('Jabatan')
                    ->badge()
                    ->color('gray')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('manager.name')
                    ->label('Atasan Langsung')
                    ->placeholder('-')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('user.email')
                    ->label('Email Akun')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('user.roles.name')
                    ->label('Peran (Role)')
                    ->badge()
                    ->color('primary')
                    ->searchable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('department_id')
                    ->relationship('department', 'name')
                    ->label('Unit Kerja'),
                Tables\Filters\SelectFilter::make('position_id')
                    ->relationship('position', 'name')
                    ->label('Jabatan'),
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->label('Ubah'),
                Tables\Actions\DeleteAction::make()
                    ->label('Hapus'),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->label('Hapus Terpilih'),
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
