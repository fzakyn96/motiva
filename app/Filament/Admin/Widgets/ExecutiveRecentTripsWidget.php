<?php

namespace App\Filament\Admin\Widgets;

use App\Filament\Admin\Resources\SppdCostResource;
use App\Filament\Admin\Resources\SppdResource;
use App\Models\Sppd;
use Carbon\Carbon;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Database\Eloquent\Builder;

class ExecutiveRecentTripsWidget extends BaseWidget
{
    protected static ?string $heading = 'Monitoring Penugasan Perjalanan Dinas Terkini';
    protected static ?int $sort = 5;

    protected int | string | array $columnSpan = 'full';

    public static function canView(): bool
    {
        $user = \Filament\Facades\Filament::auth()->user() ?? auth()->user();
        if (! $user) return false;
        return $user->hasAnyRole(['Division Head', 'Manager', 'Supervisor', 'Superadmin', 'Admin', 'HR']);
    }

    public function table(Table $table): Table
    {
        $user = \Filament\Facades\Filament::auth()->user() ?? auth()->user();
        $employee = $user?->employee;
        $isAllAccess = ! $user || $user->hasRole(['Superadmin', 'Admin', 'HR', 'Division Head']);

        $query = Sppd::query()
            ->with(['employee.position', 'employee.department', 'businessTrip', 'cost'])
            ->latest('id');

        if (! $isAllAccess && $employee) {
            $query->whereHas('employee', function (Builder $q) use ($employee) {
                $q->where('department_id', $employee->department_id)
                    ->orWhere('manager_id', $employee->id)
                    ->orWhere('id', $employee->id);
            });
        }

        return $table
            ->query($query)
            ->columns([
                Tables\Columns\TextColumn::make('sppd_number')
                    ->label('Nomor SPPD')
                    ->weight('bold')
                    ->color('primary')
                    ->copyable()
                    ->placeholder('Draf'),

                Tables\Columns\TextColumn::make('employee.name')
                    ->label('Pegawai')
                    ->description(function (Sppd $record) {
                        $pos = $record->employee?->position?->name;
                        $dept = $record->employee?->department?->code ?? $record->employee?->department?->name;
                        $parts = array_filter([$pos, $dept]);
                        return ! empty($parts) ? implode(' • ', $parts) : '-';
                    }),

                Tables\Columns\TextColumn::make('destination')
                    ->label('Tujuan Dinas')
                    ->state(fn (Sppd $record) => $record->businessTrip?->destination ?? ($record->destination_location ?? '-'))
                    ->icon('heroicon-o-map-pin'),

                Tables\Columns\TextColumn::make('period')
                    ->label('Jadwal Dinas')
                    ->state(function (Sppd $record) {
                        $start = $record->departure_date ?? ($record->businessTrip?->start_date);
                        $end = $record->return_date ?? ($record->businessTrip?->end_date);
                        $startF = $start ? Carbon::parse($start)->format('d/m/Y') : '-';
                        $endF = $end ? Carbon::parse($end)->format('d/m/Y') : '-';
                        $days = $record->total_days ?: ($start && $end ? Carbon::parse($start)->diffInDays(Carbon::parse($end)) + 1 : 0);
                        return "{$startF} - {$endF}" . ($days ? " ({$days} Hari)" : '');
                    })
                    ->color('gray'),

                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'draft' => 'Draf',
                        'pending' => 'Menunggu',
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

                Tables\Columns\TextColumn::make('cost.total_cost')
                    ->label('Biaya SPPD')
                    ->formatStateUsing(fn ($state) => $state !== null ? 'Rp ' . number_format((float) $state, 0, ',', '.') : 'Belum dihitung')
                    ->weight('bold')
                    ->color(fn ($state) => $state !== null ? 'success' : 'warning')
                    ->placeholder('Belum dihitung'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label('Status SPPD')
                    ->options([
                        'draft' => 'Draf',
                        'pending' => 'Menunggu Persetujuan',
                        'approved' => 'Disetujui',
                        'rejected' => 'Ditolak',
                    ]),
            ])
            ->actions([
                Tables\Actions\Action::make('calculate_cost')
                    ->label('Hitung Biaya')
                    ->icon('heroicon-o-calculator')
                    ->button()
                    ->size('sm')
                    ->color('warning')
                    ->visible(fn (Sppd $record) => $isAllAccess && $record->status === 'approved' && ! $record->cost)
                    ->url(fn (Sppd $record) => SppdCostResource::getUrl('calculate', ['record' => $record])),

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
                    ->color('gray')
                    ->url(fn (Sppd $record) => SppdResource::getUrl('review', ['record' => $record])),
            ])
            ->paginated([5, 10]);
    }
}
