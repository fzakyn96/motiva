<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\SppdLogResource\Pages;
use App\Models\SppdLog;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Support\Enums\FontWeight;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class SppdLogResource extends Resource
{
    protected static ?string $model = SppdLog::class;

    protected static ?string $navigationIcon = 'heroicon-o-paper-airplane';
    protected static ?string $navigationGroup = 'Log';
    protected static ?int $navigationSort = 2;
    protected static ?string $navigationLabel = 'Log SPPD';
    protected static ?string $modelLabel = 'Log SPPD';
    protected static ?string $pluralModelLabel = 'Log Audit Penghapusan SPPD';

    public static function getNavigationBadge(): ?string
    {
        $user = \Filament\Facades\Filament::auth()->user() ?? auth()->user();
        if (! $user || ! $user->hasAnyRole(['Superadmin', 'Admin', 'Manager'])) {
            return null;
        }

        $count = SppdLog::count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): string | array | null
    {
        return 'danger';
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return 'Riwayat audit penghapusan dokumen SPPD';
    }

    public static function shouldRegisterNavigation(): bool
    {
        $user = \Filament\Facades\Filament::auth()->user() ?? auth()->user();
        if (! $user) {
            return false;
        }

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
                Infolists\Components\Section::make('Audit Trail Penghapusan SPPD')
                    ->description('Informasi alasan dan identitas pengguna yang menghapus dokumen Memo/SPPD.')
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
                            ->label('Alasan Penghapusan')
                            ->weight(FontWeight::SemiBold)
                            ->color('danger')
                            ->columnSpanFull(),
                    ]),

                Infolists\Components\Section::make('Ringkasan Dokumen SPPD yang Dihapus')
                    ->description('Snapshot data SPPD saat terhapus dari sistem.')
                    ->icon('heroicon-o-paper-airplane')
                    ->iconColor('primary')
                    ->columns(3)
                    ->schema([
                        Infolists\Components\TextEntry::make('sppd_number')
                            ->label('Nomor SPPD')
                            ->weight(FontWeight::Bold)
                            ->color('primary')
                            ->placeholder('-'),

                        Infolists\Components\TextEntry::make('nomor_memo')
                            ->label('Nomor Memo Terkait')
                            ->badge()
                            ->color('gray')
                            ->placeholder('-'),

                        Infolists\Components\TextEntry::make('sppd_status')
                            ->label('Status SPPD Saat Dihapus')
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

                        Infolists\Components\TextEntry::make('employee_name')
                            ->label('Pegawai yang Ditugaskan')
                            ->weight(FontWeight::SemiBold)
                            ->placeholder('-'),

                        Infolists\Components\TextEntry::make('department_name')
                            ->label('Unit Kerja')
                            ->badge()
                            ->color('info')
                            ->placeholder('-'),

                        Infolists\Components\TextEntry::make('destination')
                            ->label('Tujuan Dinas')
                            ->icon('heroicon-m-map-pin')
                            ->placeholder('-'),

                        Infolists\Components\TextEntry::make('departure_date')
                            ->label('Tanggal Berangkat')
                            ->date('d F Y')
                            ->placeholder('-'),

                        Infolists\Components\TextEntry::make('return_date')
                            ->label('Tanggal Kembali')
                            ->date('d F Y')
                            ->placeholder('-'),

                        Infolists\Components\TextEntry::make('total_days')
                            ->label('Lama Dinas')
                            ->formatStateUsing(fn ($state) => $state ? "{$state} Hari" : '-'),
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

                Tables\Columns\TextColumn::make('sppd_number')
                    ->label('Nomor SPPD')
                    ->searchable()
                    ->sortable()
                    ->weight(FontWeight::Bold)
                    ->color('primary')
                    ->copyable()
                    ->placeholder('-'),

                Tables\Columns\TextColumn::make('nomor_memo')
                    ->label('Nomor Memo')
                    ->searchable()
                    ->badge()
                    ->color('gray')
                    ->placeholder('-'),

                Tables\Columns\TextColumn::make('employee_name')
                    ->label('Pegawai')
                    ->searchable()
                    ->description(fn (SppdLog $record): string => $record->department_name ? "Unit: {$record->department_name}" : '')
                    ->weight(FontWeight::SemiBold),

                Tables\Columns\TextColumn::make('destination')
                    ->label('Tujuan')
                    ->searchable()
                    ->placeholder('-'),

                Tables\Columns\TextColumn::make('sppd_status')
                    ->label('Status SPPD')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => match ($state) {
                        'draft' => 'Draf',
                        'pending' => 'Menunggu',
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
                    ->description(fn (SppdLog $record): string => $record->deleted_by_role ? "Role: {$record->deleted_by_role}" : ''),

                Tables\Columns\TextColumn::make('deletion_reason')
                    ->label('Alasan Penghapusan')
                    ->searchable()
                    ->wrap()
                    ->color('danger'),
            ])
            ->filters([])
            ->actions([
                Tables\Actions\ViewAction::make()
                    ->label('Detail Audit')
                    ->icon('heroicon-o-eye')
                    ->color('primary')
                    ->modalHeading('Detail Audit Trail Penghapusan SPPD'),
            ])
            ->bulkActions([])
            ->emptyStateHeading('Belum Ada Riwayat Penghapusan SPPD')
            ->emptyStateDescription('Setiap penghapusan SPPD akibat penghapusan Memo akan tercatat secara otomatis di halaman Log SPPD ini.')
            ->emptyStateIcon('heroicon-o-shield-check');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ManageSppdLogs::route('/'),
        ];
    }
}

