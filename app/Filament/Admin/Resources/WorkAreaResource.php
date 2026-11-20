<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\WorkAreaResource\Pages;
use App\Models\Department;
use App\Models\Employee;
use App\Models\WorkArea;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class WorkAreaResource extends Resource
{
    protected static ?string $model = WorkArea::class;

    protected static ?string $navigationGroup = 'Data Pegawai';

    protected static ?string $navigationIcon = 'heroicon-o-map-pin';
    protected static ?string $navigationLabel = 'Master Area Kerja';
    protected static ?string $modelLabel = 'Area Kerja';
    protected static ?string $pluralModelLabel = 'Area Kerja';
    protected static ?int $navigationSort = 3;

    public static function canViewAny(): bool
    {
        $user = \Filament\Facades\Filament::auth()->user() ?? auth()->user();
        if (! $user) {
            return false;
        }

        return $user->hasAnyRole(['Superadmin', 'Admin', 'HR']);
    }

    public static function getNavigationBadge(): ?string
    {
        if (! \Illuminate\Support\Facades\Schema::hasTable('work_areas')) {
            return null;
        }

        $count = static::getEloquentQuery()->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'info';
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return 'Total Area Kerja';
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Informasi Area Kerja & Koordinator')
                    ->icon('heroicon-o-map-pin')
                    ->iconColor('primary')
                    ->description('Atur area kerja di dalam Unit Kerja MESI beserta Koordinator Area Kerja (setara Manajer).')
                    ->schema([
                        Forms\Components\Select::make('department_id')
                            ->label('Unit Kerja / Departemen Induk')
                            ->prefixIcon('heroicon-m-building-office-2')
                            ->relationship('department', 'name')
                            ->default(fn () => Department::where('code', 'MESI')->value('id') ?? Department::first()?->id)
                            ->required()
                            ->searchable()
                            ->preload(),
                        Forms\Components\Select::make('coordinator_employee_id')
                            ->label('Koordinator Area Kerja (Setara Manajer)')
                            ->prefixIcon('heroicon-m-user-circle')
                            ->relationship(
                                'coordinator',
                                'name',
                                fn (Builder $query) => $query->orderBy('name')
                            )
                            ->getOptionLabelFromRecordUsing(fn (Employee $record) => $record->name . ($record->position ? ' — ' . $record->position->name : ''))
                            ->placeholder('Pilih Koordinator Area Kerja')
                            ->searchable()
                            ->preload(),
                        Forms\Components\TextInput::make('code')
                            ->label('Kode Area Kerja')
                            ->prefixIcon('heroicon-m-hashtag')
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->maxLength(50)
                            ->placeholder('Contoh: JKT, REG1, REG2, REG3, SHG'),
                        Forms\Components\TextInput::make('name')
                            ->label('Nama Area Kerja')
                            ->prefixIcon('heroicon-m-map-pin')
                            ->required()
                            ->maxLength(255)
                            ->placeholder('Contoh: Jakarta, Regional 1, Regional 2, Subholding Gas (SHG)'),
                        Forms\Components\Textarea::make('description')
                            ->label('Keterangan Area Kerja (Opsional)')
                            ->rows(2)
                            ->maxLength(500)
                            ->columnSpanFull(),
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
                        ->label('Kode Area')
                        ->searchable()
                        ->sortable()
                        ->badge()
                        ->color('primary')
                        ->grow(false),
                    Tables\Columns\TextColumn::make('name')
                        ->label('Nama Area Kerja')
                        ->searchable()
                        ->sortable()
                        ->weight('bold'),
                    Tables\Columns\TextColumn::make('coordinator.name')
                        ->label('Koordinator (Setara Manajer)')
                        ->placeholder('Belum ditentukan')
                        ->icon('heroicon-m-user-circle')
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
                    Tables\Columns\Layout\Grid::make(['default' => 1, 'sm' => 3])
                        ->schema([
                            Tables\Columns\TextColumn::make('department.name')
                                ->description('Unit Kerja Induk', position: 'above')
                                ->placeholder('-'),
                            Tables\Columns\TextColumn::make('coordinator.position.name')
                                ->description('Jabatan Koordinator', position: 'above')
                                ->placeholder('-'),
                            Tables\Columns\TextColumn::make('description')
                                ->description('Keterangan', position: 'above')
                                ->placeholder('-'),
                        ]),
                ])->collapsible(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('department_id')
                    ->relationship('department', 'name')
                    ->label('Unit Kerja'),
                Tables\Filters\SelectFilter::make('coordinator_employee_id')
                    ->relationship('coordinator', 'name')
                    ->label('Koordinator Area Kerja'),
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
            'index' => Pages\ManageWorkAreas::route('/'),
        ];
    }
}
