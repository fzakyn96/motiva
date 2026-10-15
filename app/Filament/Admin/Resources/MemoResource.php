<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\MemoResource\Pages;
use App\Filament\Admin\Resources\MemoResource\RelationManagers;
use App\Models\Memo;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class MemoResource extends Resource
{
    protected static ?string $model = Memo::class;

    protected static ?string $navigationIcon = 'heroicon-o-document-text';
    protected static ?string $navigationGroup = 'Alur Dokumen';
    protected static ?string $modelLabel = 'Memo';
    protected static ?string $pluralModelLabel = 'Memo';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Detail Memo')
                    ->description('Lengkapi rincian memo internal.')
                    ->schema([
                        Forms\Components\TextInput::make('nomor_memo')
                            ->label('Nomor Memo / Nota Dinas')
                            ->disabled()
                            ->dehydrated(false)
                            ->visible(fn (string $context): bool => $context === 'edit'),
                        Forms\Components\TextInput::make('to_recipient')
                            ->label('Kepada')
                            ->required()
                            ->maxLength(255),
                        Forms\Components\DatePicker::make('date')
                            ->label('Tanggal Memo Dibuat')
                            ->native(false)
                            ->displayFormat('d/m/Y')
                            ->closeOnDateSelection()
                            ->required()
                            ->default(now()),
                        Forms\Components\RichEditor::make('content')
                            ->label('Isi Memo')
                            ->required()
                            ->columnSpanFull(),
                    ])
                    ->columns(['default' => 1, 'md' => 2]),

                Forms\Components\Section::make('Detail Penugasan')
                    ->description('Isi bagian ini jika memo berisi penugasan.')
                    ->schema([
                        Forms\Components\DatePicker::make('assignment_start_date')
                            ->label('Tanggal Mulai Penugasan')
                            ->native(false)
                            ->displayFormat('d/m/Y')
                            ->closeOnDateSelection(),
                        Forms\Components\DatePicker::make('assignment_end_date')
                            ->label('Tanggal Selesai Penugasan')
                            ->native(false)
                            ->displayFormat('d/m/Y')
                            ->closeOnDateSelection(),
                        Forms\Components\TextInput::make('agenda')
                            ->label('Agenda')
                            ->maxLength(255),
                        Forms\Components\TextInput::make('location')
                            ->label('Tempat / Lokasi')
                            ->maxLength(255),
                        Forms\Components\Select::make('participants')
                            ->label('Peserta Penugasan')
                            ->multiple()
                            ->searchable()
                            ->preload()
                            ->options(function () {
                                $user = auth()->user();

                                if ($user && ($user->hasRole('Superadmin') || $user->hasRole('Admin'))) {
                                    return \App\Models\Employee::with('department')
                                        ->get()
                                        ->mapWithKeys(function ($emp) {
                                            $unit = $emp->department?->name ?? 'Tanpa Unit';
                                            return [$emp->id => "{$emp->name} ({$emp->identity_number}) - {$unit}"];
                                        });
                                }

                                $myEmployee = $user?->employee;
                                $departmentId = $myEmployee?->department_id;

                                if (! $departmentId) {
                                    return \App\Models\Employee::with('department')
                                        ->get()
                                        ->mapWithKeys(fn ($e) => [$e->id => "{$e->name} ({$e->identity_number})"]);
                                }

                                return \App\Models\Employee::where('department_id', $departmentId)
                                    ->get()
                                    ->mapWithKeys(function ($emp) {
                                        return [$emp->id => "{$emp->name} ({$emp->identity_number})"];
                                    });
                            })
                            ->helperText(function () {
                                $user = auth()->user();
                                if ($user && ($user->hasRole('Superadmin') || $user->hasRole('Admin'))) {
                                    return 'Super Admin dapat memilih peserta dari semua unit kerja.';
                                }
                                $unitName = $user?->employee?->department?->name;
                                return $unitName
                                    ? "HR Unit {$unitName}: Hanya dapat memilih peserta dari unit {$unitName}."
                                    : 'Pilih pegawai yang ditugaskan.';
                            })
                            ->columnSpanFull(),
                        Forms\Components\Hidden::make('status')
                            ->default('pending'),
                    ])
                    ->columns(['default' => 1, 'md' => 2]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('nomor_memo')
                    ->label('Nomor Memo')
                    ->sortable()
                    ->searchable()
                    ->weight('bold'),
                Tables\Columns\TextColumn::make('department.name')
                    ->label('Departemen')
                    ->sortable()
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('to_recipient')
                    ->label('Kepada')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('creator.name')
                    ->label('Dari')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('agenda')
                    ->label('Agenda')
                    ->searchable()
                    ->weight('bold')
                    ->wrap(),
                Tables\Columns\TextColumn::make('date')
                    ->label('Tanggal')
                    ->date('d/m/Y')
                    ->sortable(),
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
                Tables\Actions\Action::make('print')
                    ->label('Cetak PDF')
                    ->icon('heroicon-o-printer')
                    ->color('info')
                    ->url(fn (Memo $record) => route('memos.print', $record))
                    ->openUrlInNewTab()
                    ->visible(fn (Memo $record) => $record->status === 'approved'),
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
            'index' => Pages\ListMemos::route('/'),
            'create' => Pages\CreateMemo::route('/create'),
            'edit' => Pages\EditMemo::route('/{record}/edit'),
        ];
    }
}
