<?php

namespace App\Filament\Admin\Resources\SppdCostResource\Pages;

use App\Filament\Admin\Resources\SppdCostResource;
use App\Models\Sppd;
use Filament\Resources\Components\Tab;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;

class ListSppdCosts extends ListRecords
{
    protected static string $resource = SppdCostResource::class;

    protected static ?string $title = 'Perhitungan Biaya SPPD';

    protected function getHeaderActions(): array
    {
        return [
            \Filament\Actions\Action::make('bulk_by_memo')
                ->label('Input Biaya Bulk per No. Memo')
                ->icon('heroicon-o-squares-plus')
                ->color('primary')
                ->visible(function (): bool {
                    $user = \Filament\Facades\Filament::auth()->user() ?? auth()->user();

                    return $user !== null && $user->hasAnyRole(['Superadmin', 'Admin', 'HR']);
                })
                ->modalHeading('Input Biaya SPPD Massal (Bulk) Berdasarkan Nomor Memo')
                ->modalDescription('Pilih Nomor Memo untuk mengisi rincian biaya dan mengunggah evidence sekaligus bagi peserta penugasan dalam Memo tersebut. Pada halaman berikutnya, Anda juga tetap dapat mengecualikan pegawai tertentu bilamana biaya/evidence-nya berbeda.')
                ->modalSubmitActionLabel('Lanjut Isi Biaya & Evidence')
                ->form([
                    \Filament\Forms\Components\Select::make('business_trip_id')
                        ->label('Pilih Nomor Memo Penugasan')
                        ->options(function (): array {
                            $user = \Filament\Facades\Filament::auth()->user() ?? auth()->user();
                            $areaId = ($user && ! $user->hasAnyRole(['Superadmin', 'Admin'])) ? $user->employee?->work_area_id : null;

                            return \App\Models\BusinessTrip::whereHas('sppds', function (Builder $q) use ($areaId) {
                                $q->where('status', 'approved');
                                if ($areaId) {
                                    $q->whereHas('employee', fn (Builder $eq) => $eq->where('work_area_id', $areaId));
                                }
                            })
                                ->with(['memo'])
                                ->withCount(['sppds as approved_sppds_count' => function (Builder $q) use ($areaId) {
                                    $q->where('status', 'approved');
                                    if ($areaId) {
                                        $q->whereHas('employee', fn (Builder $eq) => $eq->where('work_area_id', $areaId));
                                    }
                                }])
                                ->orderByDesc('id')
                                ->get()
                                ->mapWithKeys(function (\App\Models\BusinessTrip $trip): array {
                                    $noMemo = $trip->memo?->nomor_memo ?: ('Memo #' . $trip->id);
                                    $agenda = $trip->memo?->agenda ?: ($trip->activity_name ?: '-');
                                    return [$trip->id => "{$noMemo} — {$agenda} ({$trip->approved_sppds_count} Peserta Penugasan)"];
                                })
                                ->all();
                        })
                        ->searchable()
                        ->preload()
                        ->required(),
                ])
                ->action(function (array $data): void {
                    $user = \Filament\Facades\Filament::auth()->user() ?? auth()->user();
                    $areaId = ($user && ! $user->hasAnyRole(['Superadmin', 'Admin'])) ? $user->employee?->work_area_id : null;

                    $firstSppd = Sppd::where('business_trip_id', $data['business_trip_id'])
                        ->where('status', 'approved')
                        ->when($areaId, fn (Builder $q) => $q->whereHas('employee', fn (Builder $eq) => $eq->where('work_area_id', $areaId)))
                        ->orderBy('id')
                        ->first();

                    if (! $firstSppd) {
                        return;
                    }

                    $this->redirect(SppdCostResource::getUrl('calculate', [
                        'record' => $firstSppd->id,
                        'bulk_memo' => 1,
                    ]));
                }),
        ];
    }

    public function getTabs(): array
    {
        return [
            'all' => Tab::make('Semua SPPD')
                ->badge(fn () => SppdCostResource::getEloquentQuery()->count()),
            'uncalculated' => Tab::make('Belum Dihitung')
                ->badge(fn () => SppdCostResource::getEloquentQuery()->whereDoesntHave('cost')->count())
                ->badgeColor('warning')
                ->modifyQueryUsing(fn (Builder $query) => $query->whereDoesntHave('cost')),
            'calculated' => Tab::make('Sudah Dihitung')
                ->badge(fn () => SppdCostResource::getEloquentQuery()->whereHas('cost')->count())
                ->badgeColor('success')
                ->modifyQueryUsing(fn (Builder $query) => $query->whereHas('cost')),
        ];
    }
}
