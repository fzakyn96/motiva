<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\ReimbursementLogResource\Pages;
use App\Models\ReimbursementLog;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Support\Enums\FontWeight;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class ReimbursementLogResource extends Resource
{
    protected static ?string $model = ReimbursementLog::class;

    protected static ?string $navigationIcon = 'heroicon-o-banknotes';
    protected static ?string $navigationGroup = 'Log';
    protected static ?int $navigationSort = 4;
    protected static ?string $navigationLabel = 'Log Reimbursement';
    protected static ?string $modelLabel = 'Log Reimbursement';
    protected static ?string $pluralModelLabel = 'Log Audit Penghapusan Reimbursement';

    public static function getNavigationBadge(): ?string
    {
        $user = \Filament\Facades\Filament::auth()->user() ?? auth()->user();
        if (! $user || ! $user->hasAnyRole(['Superadmin', 'Admin', 'Manager'])) {
            return null;
        }

        $count = ReimbursementLog::count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): string | array | null
    {
        return 'danger';
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return 'Riwayat audit penghapusan dokumen Reimbursement';
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
                Infolists\Components\Section::make('Audit Trail Penghapusan Reimbursement')
                    ->description('Informasi alasan dan identitas pengguna yang menghapus dokumen.')
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

                Infolists\Components\Section::make('Ringkasan Dokumen Reimbursement yang Dihapus')
                    ->description('Snapshot data Reimbursement saat terhapus dari sistem.')
                    ->icon('heroicon-o-banknotes')
                    ->iconColor('primary')
                    ->columns(3)
                    ->schema([
                        Infolists\Components\TextEntry::make('reimbursement_number')
                            ->label('Nomor Reimbursement')
                            ->weight(FontWeight::Bold)
                            ->color('primary')
                            ->placeholder('-'),

                        Infolists\Components\TextEntry::make('sppd_number')
                            ->label('Nomor SPPD Terkait')
                            ->badge()
                            ->color('gray')
                            ->placeholder('-'),

                        Infolists\Components\TextEntry::make('nomor_memo')
                            ->label('Nomor Memo Terkait')
                            ->badge()
                            ->color('gray')
                            ->placeholder('-'),

                        Infolists\Components\TextEntry::make('title')
                            ->label('Keperluan Klaim')
                            ->weight(FontWeight::SemiBold)
                            ->columnSpan(['default' => 1, 'md' => 2])
                            ->placeholder('-'),

                        Infolists\Components\TextEntry::make('reimbursement_status')
                            ->label('Status Saat Dihapus')
                            ->badge()
                            ->formatStateUsing(fn (?string $state): string => match ($state) {
                                'draft' => 'Draf',
                                'pending' => 'Menunggu Persetujuan',
                                'approved' => 'Disetujui (Approved)',
                                'rejected' => 'Ditolak',
                                'paid' => 'Dibayarkan',
                                default => ucfirst((string) $state),
                            })
                            ->color(fn (?string $state): string => match ($state) {
                                'draft' => 'gray',
                                'pending' => 'warning',
                                'approved' => 'success',
                                'rejected' => 'danger',
                                'paid' => 'info',
                                default => 'gray',
                            }),

                        Infolists\Components\TextEntry::make('employee_name')
                            ->label('Pegawai Pengaju')
                            ->placeholder('-'),

                        Infolists\Components\TextEntry::make('total_claim_amount')
                            ->label('Total Klaim Diganti')
                            ->money('IDR', locale: 'id')
                            ->weight(FontWeight::Bold)
                            ->color('warning'),

                        Infolists\Components\TextEntry::make('total_amount')
                            ->label('Total Keseluruhan (Termasuk Kantor)')
                            ->money('IDR', locale: 'id'),
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

                Tables\Columns\TextColumn::make('reimbursement_number')
                    ->label('Nomor Reimbursement')
                    ->searchable()
                    ->sortable()
                    ->weight(FontWeight::Bold)
                    ->color('primary')
                    ->copyable()
                    ->placeholder('-'),

                Tables\Columns\TextColumn::make('sppd_number')
                    ->label('Nomor SPPD')
                    ->searchable()
                    ->badge()
                    ->color('gray')
                    ->placeholder('-'),

                Tables\Columns\TextColumn::make('employee_name')
                    ->label('Pegawai Pengaju')
                    ->searchable()
                    ->description(fn (ReimbursementLog $record): string => $record->title ?: '')
                    ->weight(FontWeight::SemiBold),

                Tables\Columns\TextColumn::make('total_claim_amount')
                    ->label('Total Klaim')
                    ->money('IDR', locale: 'id')
                    ->sortable()
                    ->weight(FontWeight::Bold)
                    ->color('warning'),

                Tables\Columns\TextColumn::make('reimbursement_status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => match ($state) {
                        'draft' => 'Draf',
                        'pending' => 'Menunggu',
                        'approved' => 'Disetujui',
                        'rejected' => 'Ditolak',
                        'paid' => 'Dibayarkan',
                        default => ucfirst((string) $state),
                    })
                    ->color(fn (?string $state): string => match ($state) {
                        'draft' => 'gray',
                        'pending' => 'warning',
                        'approved' => 'success',
                        'rejected' => 'danger',
                        'paid' => 'info',
                        default => 'gray',
                    }),

                Tables\Columns\TextColumn::make('deleted_by_name')
                    ->label('Dihapus Oleh')
                    ->searchable()
                    ->description(fn (ReimbursementLog $record): string => $record->deleted_by_role ? "Role: {$record->deleted_by_role}" : ''),

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
                    ->modalHeading('Detail Audit Trail Penghapusan Reimbursement'),
            ])
            ->bulkActions([])
            ->emptyStateHeading('Belum Ada Riwayat Penghapusan Reimbursement')
            ->emptyStateDescription('Setiap penghapusan Reimbursement akan tercatat secara otomatis di halaman Log Reimbursement ini.')
            ->emptyStateIcon('heroicon-o-shield-check');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ManageReimbursementLogs::route('/'),
        ];
    }
}

