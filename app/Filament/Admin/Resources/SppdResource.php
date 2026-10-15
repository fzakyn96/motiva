<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\SppdResource\Pages;
use App\Models\Sppd;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class SppdResource extends Resource
{
    protected static ?string $model = Sppd::class;

    protected static ?string $navigationIcon = 'heroicon-o-paper-airplane';
    protected static ?string $navigationGroup = 'Alur Dokumen';
    protected static ?string $navigationLabel = 'SPPD';
    protected static ?string $modelLabel = 'SPPD';
    protected static ?string $pluralModelLabel = 'SPPD';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Detail SPPD')
                    ->description('Detail Surat Perintah Perjalanan Dinas')
                    ->schema([
                        Forms\Components\Select::make('business_trip_id')
                            ->relationship('businessTrip', 'activity_name')
                            ->label('Kegiatan Perjalanan Dinas')
                            ->required()
                            ->searchable(),
                        Forms\Components\Select::make('employee_id')
                            ->relationship('employee', 'name')
                            ->label('Pegawai yang Ditugaskan')
                            ->required()
                            ->searchable(),
                        Forms\Components\TextInput::make('sppd_number')
                            ->label('Nomor SPPD')
                            ->required()
                            ->maxLength(255)
                            ->unique(ignoreRecord: true),
                        Forms\Components\Select::make('status')
                            ->label('Status')
                            ->options([
                                'draft' => 'Draf',
                                'pending' => 'Menunggu Persetujuan',
                                'approved' => 'Disetujui',
                                'rejected' => 'Ditolak',
                            ])
                            ->required()
                            ->default('draft'),
                    ])
                    ->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('sppd_number')
                    ->label('Nomor SPPD')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),
                Tables\Columns\TextColumn::make('businessTrip.activity_name')
                    ->label('Nama Kegiatan')
                    ->sortable()
                    ->searchable()
                    ->wrap(),
                Tables\Columns\TextColumn::make('employee.name')
                    ->label('Pegawai')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'draft' => 'Draf',
                        'pending' => 'Menunggu Persetujuan',
                        'approved' => 'Disetujui',
                        'rejected' => 'Ditolak',
                        default => ucfirst($state),
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'draft' => 'gray',
                        'pending' => 'warning',
                        'approved' => 'success',
                        'rejected' => 'danger',
                        default => 'gray',
                    }),
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
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->label('Hapus Terpilih'),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSppds::route('/'),
            'create' => Pages\CreateSppd::route('/create'),
            'edit' => Pages\EditSppd::route('/{record}/edit'),
        ];
    }
}
