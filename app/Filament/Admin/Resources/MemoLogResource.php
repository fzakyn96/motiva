<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\MemoLogResource\Pages;
use App\Models\MemoLog;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Support\Enums\FontWeight;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class MemoLogResource extends Resource
{
    protected static ?string $model = MemoLog::class;

    protected static ?string $navigationIcon = 'heroicon-o-document-text';
    protected static ?string $navigationGroup = 'Log';
    protected static ?int $navigationSort = 1;
    protected static ?string $navigationLabel = 'Log Memo';
    protected static ?string $modelLabel = 'Log Memo';
    protected static ?string $pluralModelLabel = 'Log Audit Penghapusan Memo';

    public static function getNavigationBadge(): ?string
    {
        $user = \Filament\Facades\Filament::auth()->user() ?? auth()->user();
        if (! $user || ! $user->hasAnyRole(['Superadmin', 'Admin', 'Manager'])) {
            return null;
        }

        $count = MemoLog::count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): string | array | null
    {
        return 'danger';
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return 'Riwayat audit penghapusan Memo yang telah disetujui';
    }

    public static function shouldRegisterNavigation(): bool
    {
        $user = \Filament\Facades\Filament::auth()->user() ?? auth()->user();
        if (! $user) {
            return false;
        }

        // Hanya boleh diakses oleh Manajer dan Superadmin (tidak boleh HR, Supervisor, Staff)
        return $user->hasAnyRole(['Superadmin', 'Admin', 'Manager']);
    }

    public static function canViewAny(): bool
    {
        $user = \Filament\Facades\Filament::auth()->user() ?? auth()->user();
        if (! $user) {
            return false;
        }

        return $user->hasAnyRole(['Superadmin', 'Admin', 'Manager']);
    }

    public static function canView(Model $record): bool
    {
        $user = \Filament\Facades\Filament::auth()->user() ?? auth()->user();
        if (! $user) {
            return false;
        }

        return $user->hasAnyRole(['Superadmin', 'Admin', 'Manager']);
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function canDeleteAny(): bool
    {
        return false;
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Infolists\Components\Section::make('Audit Trail Penghapusan Memo')
                    ->description('Informasi alasan dan identitas pengguna yang menghapus Memo yang telah disetujui.')
                    ->icon('heroicon-o-shield-exclamation')
                    ->iconColor('danger')
                    ->columns(3)
                    ->schema([
                        Infolists\Components\TextEntry::make('deleted_by_name')
                            ->label('Dihapus Oleh')
                            ->weight(FontWeight::Bold)
                            ->icon('heroicon-m-user'),

                        Infolists\Components\TextEntry::make('deleted_by_role')
                            ->label('Peran (Role)')
                            ->badge()
                            ->color('warning')
                            ->placeholder('-'),

                        Infolists\Components\TextEntry::make('created_at')
                            ->label('Waktu Penghapusan')
                            ->dateTime('d F Y H:i:s')
                            ->icon('heroicon-m-clock'),

                        Infolists\Components\TextEntry::make('deletion_reason')
                            ->label('Alasan Penghapusan Memo')
                            ->weight(FontWeight::SemiBold)
                            ->color('danger')
                            ->columnSpanFull(),
                    ]),

                Infolists\Components\Section::make('Ringkasan Dokumen Memo yang Dihapus')
                    ->description('Snapshot data Memo saat dihapus dari sistem.')
                    ->icon('heroicon-o-document-text')
                    ->iconColor('primary')
                    ->columns(3)
                    ->schema([
                        Infolists\Components\TextEntry::make('nomor_memo')
                            ->label('Nomor Memo')
                            ->weight(FontWeight::Bold)
                            ->color('primary')
                            ->placeholder('-'),

                        Infolists\Components\TextEntry::make('memo_status')
                            ->label('Status Saat Dihapus')
                            ->badge()
                            ->formatStateUsing(fn (?string $state): string => match ($state) {
                                'draft' => 'Draf',
                                'pending' => 'Menunggu Persetujuan',
                                'approved' => 'Disetujui (Approved)',
                                'rejected' => 'Ditolak',
                                default => ucfirst((string) $state),
                            })
                            ->color(fn (?string $state): string => match ($state) {
                                'draft' => 'gray',
                                'pending' => 'warning',
                                'approved' => 'success',
                                'rejected' => 'danger',
                                default => 'gray',
                            }),

                        Infolists\Components\TextEntry::make('memo_date')
                            ->label('Tanggal Memo')
                            ->date('d F Y')
                            ->placeholder('-'),

                        Infolists\Components\TextEntry::make('agenda')
                            ->label('Perihal / Agenda')
                            ->weight(FontWeight::SemiBold)
                            ->columnSpan(['default' => 1, 'md' => 2])
                            ->placeholder('-'),

                        Infolists\Components\TextEntry::make('department_name')
                            ->label('Unit / Departemen')
                            ->badge()
                            ->color('info')
                            ->placeholder('-'),

                        Infolists\Components\TextEntry::make('to_recipient')
                            ->label('Tujuan Penerima')
                            ->placeholder('-'),

                        Infolists\Components\TextEntry::make('creator_name')
                            ->label('Pembuat Memo')
                            ->placeholder('-'),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Waktu Dihapus')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->icon('heroicon-m-clock')
                    ->color('gray'),

                Tables\Columns\TextColumn::make('nomor_memo')
                    ->label('Nomor Memo')
                    ->searchable()
                    ->sortable()
                    ->weight(FontWeight::Bold)
                    ->color('primary')
                    ->copyable()
                    ->placeholder('-'),

                Tables\Columns\TextColumn::make('agenda')
                    ->label('Perihal / Agenda')
                    ->searchable()
                    ->wrap()
                    ->limit(50)
                    ->placeholder('-'),

                Tables\Columns\TextColumn::make('department_name')
                    ->label('Unit Kerja')
                    ->badge()
                    ->color('info')
                    ->placeholder('-'),

                Tables\Columns\TextColumn::make('memo_status')
                    ->label('Status Memo')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => match ($state) {
                        'draft' => 'Draf',
                        'pending' => 'Menunggu Persetujuan',
                        'approved' => 'Disetujui',
                        'rejected' => 'Ditolak',
                        default => ucfirst((string) $state),
                    })
                    ->color(fn (?string $state): string => match ($state) {
                        'draft' => 'gray',
                        'pending' => 'warning',
                        'approved' => 'success',
                        'rejected' => 'danger',
                        default => 'gray',
                    }),

                Tables\Columns\TextColumn::make('deleted_by_name')
                    ->label('Dihapus Oleh')
                    ->searchable()
                    ->description(fn (MemoLog $record): string => $record->deleted_by_role ? "Role: {$record->deleted_by_role}" : '')
                    ->weight(FontWeight::SemiBold),

                Tables\Columns\TextColumn::make('deletion_reason')
                    ->label('Alasan Penghapusan')
                    ->searchable()
                    ->wrap()
                    ->color('danger')
                    ->weight(FontWeight::Medium),
            ])
            ->filters([])
            ->actions([
                Tables\Actions\ViewAction::make()
                    ->label('Detail Audit')
                    ->icon('heroicon-o-eye')
                    ->color('primary')
                    ->modalHeading('Detail Audit Trail Penghapusan Memo'),
            ])
            ->bulkActions([])
            ->emptyStateHeading('Belum Ada Riwayat Penghapusan Memo')
            ->emptyStateDescription('Setiap penghapusan Memo yang telah disetujui oleh HR akan tercatat secara otomatis di halaman Log Memo ini.')
            ->emptyStateIcon('heroicon-o-shield-check');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ManageMemoLogs::route('/'),
        ];
    }
}

