<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\SppdCostLogResource\Pages;
use App\Models\SppdCostLog;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Support\Enums\FontWeight;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class SppdCostLogResource extends Resource
{
    protected static ?string $model = SppdCostLog::class;

    protected static ?string $navigationIcon = 'heroicon-o-calculator';
    protected static ?string $navigationGroup = 'Log';
    protected static ?int $navigationSort = 3;
    protected static ?string $navigationLabel = 'Log Biaya SPPD';
    protected static ?string $modelLabel = 'Log Biaya SPPD';
    protected static ?string $pluralModelLabel = 'Log Audit Penghapusan Biaya SPPD';

    public static function getNavigationBadge(): ?string
    {
        $user = \Filament\Facades\Filament::auth()->user() ?? auth()->user();
        if (! $user || ! $user->hasAnyRole(['Superadmin', 'Admin', 'Manager'])) {
            return null;
        }

        $count = SppdCostLog::count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): string | array | null
    {
        return 'danger';
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return 'Riwayat audit penghapusan rincian Biaya SPPD';
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
                Infolists\Components\Section::make('Audit Trail Penghapusan Biaya SPPD')
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

                Infolists\Components\Section::make('Ringkasan Rincian Biaya SPPD yang Dihapus')
                    ->description('Snapshot kalkulasi Biaya SPPD saat terhapus dari sistem.')
                    ->icon('heroicon-o-calculator')
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

                        Infolists\Components\TextEntry::make('employee_name')
                            ->label('Pegawai')
                            ->weight(FontWeight::SemiBold)
                            ->placeholder('-'),

                        Infolists\Components\TextEntry::make('subtotal_transport')
                            ->label('Subtotal Transport')
                            ->formatStateUsing(fn ($state): string => $state !== null ? 'Rp ' . number_format((float) $state, 0, ',', '.') : '-'),

                        Infolists\Components\TextEntry::make('subtotal_per_diem')
                            ->label('Subtotal Uang Harian & Penginapan')
                            ->formatStateUsing(fn ($state): string => $state !== null ? 'Rp ' . number_format((float) $state, 0, ',', '.') : '-'),

                        Infolists\Components\TextEntry::make('subtotal_other')
                            ->label('Subtotal Biaya Lainnya')
                            ->formatStateUsing(fn ($state): string => $state !== null ? 'Rp ' . number_format((float) $state, 0, ',', '.') : '-'),

                        Infolists\Components\TextEntry::make('total_cost')
                            ->label('Total Biaya SPPD')
                            ->formatStateUsing(fn ($state): string => $state !== null ? 'Rp ' . number_format((float) $state, 0, ',', '.') : '-')
                            ->weight(FontWeight::Bold)
                            ->color('success'),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->contentGrid(['default' => 1, 'xl' => 1])
            ->columns([
                Tables\Columns\Layout\Split::make([
                    Tables\Columns\TextColumn::make('sppd_number')
                        ->label('Nomor SPPD')
                        ->searchable()
                        ->sortable()
                        ->weight(FontWeight::Bold)
                        ->color('primary')
                        ->copyable()
                        ->placeholder('-'),

                    Tables\Columns\TextColumn::make('employee_name')
                        ->label('Pegawai')
                        ->searchable()
                        ->weight(FontWeight::SemiBold),

                    Tables\Columns\TextColumn::make('total_cost')
                        ->label('Total Biaya SPPD')
                        ->formatStateUsing(fn ($state): string => $state !== null ? 'Rp ' . number_format((float) $state, 0, ',', '.') : '-')
                        ->sortable()
                        ->badge()
                        ->color('success')
                        ->grow(false),
                ])->from('md'),

                Tables\Columns\Layout\Panel::make([
                    Tables\Columns\Layout\Grid::make(['default' => 1, 'sm' => 2, 'md' => 4])
                        ->schema([
                            Tables\Columns\TextColumn::make('nomor_memo')
                                ->description('Nomor Memo', position: 'above')
                                ->searchable()
                                ->badge()
                                ->color('gray')
                                ->placeholder('-'),

                            Tables\Columns\TextColumn::make('deleted_by_name')
                                ->description('Dihapus Oleh', position: 'above')
                                ->searchable(),

                            Tables\Columns\TextColumn::make('created_at')
                                ->description('Waktu Dihapus', position: 'above')
                                ->dateTime('d/m/Y H:i')
                                ->sortable()
                                ->icon('heroicon-m-clock')
                                ->color('gray'),

                            Tables\Columns\TextColumn::make('deletion_reason')
                                ->description('Alasan Penghapusan', position: 'above')
                                ->searchable()
                                ->wrap()
                                ->color('danger'),
                        ]),
                ])->collapsible(),
            ])
            ->filters([])
            ->actions([
                Tables\Actions\ViewAction::make()
                    ->label('Detail Audit')
                    ->icon('heroicon-o-eye')
                    ->color('primary')
                    ->modalHeading('Detail Audit Trail Penghapusan Biaya SPPD'),
            ])
            ->bulkActions([])
            ->emptyStateHeading('Belum Ada Riwayat Penghapusan Biaya SPPD')
            ->emptyStateDescription('Setiap penghapusan rincian Biaya SPPD akibat penghapusan Memo akan tercatat secara otomatis di halaman Log Biaya SPPD ini.')
            ->emptyStateIcon('heroicon-o-shield-check');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ManageSppdCostLogs::route('/'),
        ];
    }
}

