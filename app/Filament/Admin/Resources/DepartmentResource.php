<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Clusters\DataPegawai;
use App\Filament\Admin\Resources\DepartmentResource\Pages;
use App\Models\Department;
use App\Models\Organization;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Pages\SubNavigationPosition;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class DepartmentResource extends Resource
{
    protected static ?string $model = Department::class;

    protected static ?string $cluster = DataPegawai::class;
    protected static SubNavigationPosition $subNavigationPosition = SubNavigationPosition::Top;

    protected static ?string $navigationIcon = 'heroicon-o-building-office-2';
    protected static ?string $navigationLabel = 'Master Unit Kerja';
    protected static ?string $modelLabel = 'Unit Kerja';
    protected static ?string $pluralModelLabel = 'Unit Kerja';
    protected static ?int $navigationSort = 2;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('organization_id')
                    ->label('Organisasi')
                    ->relationship('organization', 'name')
                    ->default(fn () => Organization::first()?->id)
                    ->required()
                    ->searchable()
                    ->preload(),
                Forms\Components\TextInput::make('code')
                    ->label('Kode Unit Kerja')
                    ->required()
                    ->unique(ignoreRecord: true)
                    ->maxLength(50)
                    ->placeholder('Contoh: MESI, REG1'),
                Forms\Components\TextInput::make('name')
                    ->label('Nama Unit Kerja')
                    ->required()
                    ->maxLength(255)
                    ->placeholder('Contoh: MESI, Regional 1'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('code')
                    ->label('Kode Unit')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),
                Tables\Columns\TextColumn::make('name')
                    ->label('Nama Unit Kerja')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('organization.name')
                    ->label('Organisasi')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('employees_count')
                    ->label('Jumlah Pegawai')
                    ->counts('employees')
                    ->badge()
                    ->color('info')
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
            'index' => Pages\ManageDepartments::route('/'),
        ];
    }
}
