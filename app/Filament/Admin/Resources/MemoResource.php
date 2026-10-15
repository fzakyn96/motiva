<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\MemoResource\Pages;
use App\Filament\Admin\Resources\MemoResource\RelationManagers;
use App\Models\Memo;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Infolists;
use Filament\Infolists\Infolist;
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

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Infolists\Components\Section::make('Status Dokumen')
                    ->icon('heroicon-o-shield-check')
                    ->schema([
                        Infolists\Components\TextEntry::make('status')
                            ->label('Status Pengajuan')
                            ->badge()
                            ->formatStateUsing(fn (string $state): string => match ($state) {
                                'pending' => 'Menunggu Persetujuan Manajer',
                                'approved' => 'Disetujui',
                                'rejected' => 'Ditolak',
                                default => ucfirst($state),
                            })
                            ->color(fn (string $state): string => match ($state) {
                                'pending' => 'warning',
                                'approved' => 'success',
                                'rejected' => 'danger',
                                default => 'gray',
                            }),
                        Infolists\Components\TextEntry::make('nomor_memo')
                            ->label('Nomor Memo')
                            ->badge()
                            ->color('gray')
                            ->copyable(),
                        Infolists\Components\TextEntry::make('date')
                            ->label('Tanggal Memo')
                            ->date('d F Y')
                            ->icon('heroicon-m-calendar'),
                        Infolists\Components\TextEntry::make('rejection_notes')
                            ->label('Alasan Penolakan')
                            ->state(function (Memo $record) {
                                $step = \App\Models\ApprovalRequest::where('document_type', Memo::class)
                                    ->where('document_id', $record->id)
                                    ->first()
                                    ?->steps()
                                    ->where('status', 'rejected')
                                    ->latest('updated_at')
                                    ->first();
                                return $step?->notes;
                            })
                            ->visible(fn (Memo $record) => $record->status === 'rejected')
                            ->color('danger')
                            ->columnSpanFull(),
                    ])
                    ->columns(['default' => 1, 'sm' => 3]),

                Infolists\Components\Section::make('Informasi MEMO')
                    ->icon('heroicon-o-document-text')
                    ->description('Detail penerima, pengaju, dan perihal memo.')
                    ->schema([
                        Infolists\Components\TextEntry::make('to_recipient')
                            ->label('Kepada (Yth)')
                            ->icon('heroicon-m-user')
                            ->weight('bold'),
                        Infolists\Components\TextEntry::make('creator.name')
                            ->label('Dari (Pengaju)')
                            ->icon('heroicon-m-user-circle')
                            ->weight('bold')
                            ->helperText(fn (Memo $record) => $record->creator?->position?->name ? 'Jabatan: ' . $record->creator->position->name : null),
                        Infolists\Components\TextEntry::make('department.name')
                            ->label('Unit Kerja / Departemen')
                            ->icon('heroicon-m-building-office-2'),
                        Infolists\Components\TextEntry::make('agenda')
                            ->label('Perihal / Agenda')
                            ->icon('heroicon-m-tag')
                            ->weight('bold')
                            ->color('primary'),
                        Infolists\Components\TextEntry::make('content')
                            ->label('Uraian Isi Memo')
                            ->html()
                            ->columnSpanFull(),
                    ])
                    ->columns(['default' => 1, 'sm' => 2]),

                Infolists\Components\Section::make('Rincian Penugasan & Peserta')
                    ->icon('heroicon-o-briefcase')
                    ->description('Informasi kegiatan tugas lapangan dan pegawai yang ditugaskan.')
                    ->visible(fn (Memo $record) => $record->assignment_start_date || $record->location || !empty($record->participants))
                    ->schema([
                        Infolists\Components\TextEntry::make('assignment_period')
                            ->label('Waktu Penugasan')
                            ->icon('heroicon-m-calendar-days')
                            ->state(function (Memo $record) {
                                if (!$record->assignment_start_date) return '-';
                                $start = \Carbon\Carbon::parse($record->assignment_start_date)->locale('id')->translatedFormat('d F Y');
                                if ($record->assignment_end_date && $record->assignment_start_date != $record->assignment_end_date) {
                                    $end = \Carbon\Carbon::parse($record->assignment_end_date)->locale('id')->translatedFormat('d F Y');
                                    return "{$start} s/d {$end}";
                                }
                                return $start;
                            }),
                        Infolists\Components\TextEntry::make('location')
                            ->label('Tempat / Lokasi Kegiatan')
                            ->icon('heroicon-m-map-pin'),
                        Infolists\Components\ViewEntry::make('participants_detail')
                            ->label('Daftar Pegawai yang Ditugaskan')
                            ->view('filament.infolists.memo-participants')
                            ->columnSpanFull(),
                    ])
                    ->columns(['default' => 1, 'sm' => 2]),

                Infolists\Components\Section::make('Riwayat & Jejak Persetujuan')
                    ->icon('heroicon-o-clipboard-document-check')
                    ->collapsible()
                    ->schema([
                        Infolists\Components\ViewEntry::make('approval_trail')
                            ->label('')
                            ->view('filament.infolists.memo-approval-trail')
                            ->columnSpanFull(),
                    ]),
            ]);
    }

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
                Tables\Actions\Action::make('review')
                    ->label('Review')
                    ->icon('heroicon-o-eye')
                    ->color('warning')
                    ->url(fn (Memo $record) => MemoResource::getUrl('review', ['record' => $record]))
                    ->visible(fn (Memo $record) => $record->status === 'pending'),
                Tables\Actions\Action::make('view_review')
                    ->label('Lihat Review')
                    ->icon('heroicon-o-document-magnifying-glass')
                    ->color('gray')
                    ->url(fn (Memo $record) => MemoResource::getUrl('review', ['record' => $record]))
                    ->visible(fn (Memo $record) => $record->status !== 'pending'),
                Tables\Actions\Action::make('print')
                    ->label('Cetak PDF')
                    ->icon('heroicon-o-printer')
                    ->color('info')
                    ->url(fn (Memo $record) => route('memos.print', $record))
                    ->openUrlInNewTab()
                    ->visible(fn (Memo $record) => $record->status === 'approved'),
                Tables\Actions\EditAction::make()
                    ->label('Ubah')
                    ->visible(fn (Memo $record) => $record->status === 'draft'),
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
            'review' => Pages\ReviewMemo::route('/{record}/review'),
            'edit' => Pages\EditMemo::route('/{record}/edit'),
        ];
    }
}
