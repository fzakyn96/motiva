<?php

namespace App\Filament\Admin\Widgets;

use App\Filament\Admin\Resources\SppdResource;
use App\Models\Sppd;
use Carbon\Carbon;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class StaffRecentTripsWidget extends BaseWidget
{
    protected static ?string $heading = 'Daftar Penugasan Perjalanan Dinas (SPPD) Saya';
    protected static ?int $sort = 2;

    protected int | string | array $columnSpan = 'full';

    public static function canView(): bool
    {
        $user = \Filament\Facades\Filament::auth()->user() ?? auth()->user();
        if (! $user) {
            return false;
        }

        return ! $user->hasAnyRole(['Manager', 'Supervisor', 'Superadmin', 'Admin', 'HR']);
    }

    public function table(Table $table): Table
    {
        $user = \Filament\Facades\Filament::auth()->user() ?? auth()->user();
        $employee = $user?->employee;

        $query = Sppd::query()
            ->with(['businessTrip.memo', 'cost'])
            ->where('employee_id', $employee?->id ?? 0)
            ->latest('id');

        return $table
            ->query($query)
            ->contentGrid(['default' => 1, 'xl' => 1])
            ->columns([
                Tables\Columns\Layout\Split::make([
                    Tables\Columns\TextColumn::make('businessTrip.activity_name')
                        ->label('Kegiatan / Keperluan Dinas')
                        ->weight('bold')
                        ->placeholder('-')
                        ->wrap(),

                    Tables\Columns\TextColumn::make('destination')
                        ->label('Tujuan Dinas')
                        ->state(fn (Sppd $record) => $record->businessTrip?->destination ?? ($record->destination_location ?? '-'))
                        ->icon('heroicon-o-map-pin'),

                    Tables\Columns\TextColumn::make('status')
                        ->label('Status SPPD')
                        ->badge()
                        ->grow(false)
                        ->formatStateUsing(fn (string $state): string => match ($state) {
                            'draft' => 'Draf (Perlu Dilengkapi)',
                            'pending' => 'Menunggu Persetujuan',
                            'approved' => 'Disetujui',
                            'rejected' => 'Ditolak',
                            default => ucfirst($state),
                        })
                        ->color(fn (string $state): string => match ($state) {
                            'draft' => 'warning',
                            'pending' => 'info',
                            'approved' => 'success',
                            'rejected' => 'danger',
                            default => 'gray',
                        }),
                ])->from('md'),

                Tables\Columns\Layout\Panel::make([
                    Tables\Columns\Layout\Grid::make(['default' => 1, 'sm' => 3])
                        ->schema([
                            Tables\Columns\TextColumn::make('sppd_number')
                                ->description('Nomor SPPD', position: 'above')
                                ->weight('bold')
                                ->color('primary')
                                ->copyable()
                                ->placeholder('Draf SPPD'),

                            Tables\Columns\TextColumn::make('period')
                                ->description('Jadwal Dinas', position: 'above')
                                ->state(function (Sppd $record) {
                                    $start = $record->departure_date ?? ($record->businessTrip?->start_date);
                                    $end = $record->return_date ?? ($record->businessTrip?->end_date);
                                    $startF = $start ? Carbon::parse($start)->format('d/m/Y') : '-';
                                    $endF = $end ? Carbon::parse($end)->format('d/m/Y') : '-';
                                    $days = $record->total_days ?: ($start && $end ? Carbon::parse($start)->diffInDays(Carbon::parse($end)) + 1 : 0);
                                    return "{$startF} - {$endF}" . ($days ? " ({$days} Hari)" : '');
                                })
                                ->color('gray'),

                            Tables\Columns\TextColumn::make('cost.total_cost')
                                ->description('Biaya SPPD', position: 'above')
                                ->formatStateUsing(fn ($state) => $state !== null ? 'Rp ' . number_format((float) $state, 0, ',', '.') : 'Belum dihitung HR')
                                ->weight('bold')
                                ->color(fn ($state) => $state !== null ? 'success' : 'gray')
                                ->placeholder('Belum dihitung HR'),
                        ]),
                ])->collapsible(),
            ])
            ->actions([
                Tables\Actions\Action::make('edit_sppd')
                    ->label('Lengkapi & Ajukan')
                    ->icon('heroicon-o-pencil-square')
                    ->button()
                    ->size('sm')
                    ->color('warning')
                    ->visible(fn (Sppd $record) => $record->status === 'draft')
                    ->url(fn (Sppd $record) => SppdResource::getUrl('edit', ['record' => $record])),

                Tables\Actions\Action::make('print_sppd')
                    ->label('Cetak')
                    ->icon('heroicon-o-printer')
                    ->button()
                    ->size('sm')
                    ->color('info')
                    ->visible(fn (Sppd $record) => $record->status !== 'draft')
                    ->url(fn (Sppd $record) => route('sppds.print', $record))
                    ->openUrlInNewTab(),

                Tables\Actions\Action::make('view_sppd')
                    ->label('Detail')
                    ->icon('heroicon-o-eye')
                    ->button()
                    ->size('sm')
                    ->color('primary')
                    ->url(fn (Sppd $record) => SppdResource::getUrl('review', ['record' => $record])),
            ])
            ->emptyStateHeading('Belum Ada Penugasan SPPD')
            ->emptyStateDescription('Belum ada dokumen Surat Perintah Perjalanan Dinas (SPPD) yang diterbitkan untuk Anda.')
            ->emptyStateIcon('heroicon-o-paper-airplane')
            ->paginated([5, 10]);
    }
}

