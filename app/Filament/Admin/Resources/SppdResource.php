<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\SppdResource\Pages;
use App\Filament\Admin\Resources\SppdResource\RelationManagers;
use App\Models\Sppd;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class SppdResource extends Resource
{
    protected static ?string $model = Sppd::class;

    protected static ?string $navigationIcon = 'heroicon-o-paper-airplane';
    protected static ?string $navigationGroup = 'Document Workflow';
    protected static ?string $navigationLabel = 'SPPD';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('SPPD Details')
                    ->description('Surat Perintah Perjalanan Dinas Details')
                    ->schema([
                        Forms\Components\Select::make('business_trip_id')
                            ->relationship('businessTrip', 'activity_name')
                            ->required()
                            ->searchable(),
                        Forms\Components\Select::make('employee_id')
                            ->relationship('employee', 'name')
                            ->label('Traveler')
                            ->required()
                            ->searchable(),
                        Forms\Components\TextInput::make('sppd_number')
                            ->required()
                            ->maxLength(255)
                            ->unique(ignoreRecord: true),
                        Forms\Components\Select::make('status')
                            ->options([
                                'draft' => 'Draft',
                                'pending' => 'Pending Approval',
                                'approved' => 'Approved',
                                'rejected' => 'Rejected',
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
                    ->searchable()
                    ->weight('bold'),
                Tables\Columns\TextColumn::make('businessTrip.activity_name')
                    ->label('Activity')
                    ->sortable()
                    ->searchable()
                    ->wrap(),
                Tables\Columns\TextColumn::make('employee.name')
                    ->label('Traveler')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'draft' => 'gray',
                        'pending' => 'warning',
                        'approved' => 'success',
                        'rejected' => 'danger',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
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
