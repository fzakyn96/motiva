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

    protected static ?string $title = 'Daftar Perhitungan Biaya SPPD';

    protected function getHeaderActions(): array
    {
        return [
            \Filament\Actions\Action::make('bulk_by_memo')
                ->label('Input Biaya Bulk per No. Memo')
                ->icon('heroicon-o-squares-plus')
                ->color('primary')
                ->modalHeading('Input Biaya SPPD Massal (Bulk) Berdasarkan Nomor Memo')
                ->modalDescription('Pilih Nomor Memo untuk mengisi rincian biaya dan mengunggah evidence sekaligus bagi peserta penugasan dalam Memo tersebut. Pada halaman berikutnya, Anda juga tetap dapat mengecualikan pegawai tertentu bilamana biaya/evidence-nya berbeda.')
                ->modalSubmitActionLabel('Lanjut Isi Biaya & Evidence')
                ->form([
                    \Filament\Forms\Components\Select::make('business_trip_id')
                        ->label('Pilih Nomor Memo Penugasan')
                        ->options(function (): array {
                            return \App\Models\BusinessTrip::whereHas('sppds', fn (Builder $q) => $q->where('status', 'approved'))
                                ->with(['memo'])
                                ->withCount(['sppds as approved_sppds_count' => fn (Builder $q) => $q->where('status', 'approved')])
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
                    $firstSppd = Sppd::where('business_trip_id', $data['business_trip_id'])
                        ->where('status', 'approved')
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
                ->badge(fn () => Sppd::where('status', 'approved')->count()),
            'uncalculated' => Tab::make('Belum Dihitung')
                ->badge(fn () => Sppd::where('status', 'approved')->whereDoesntHave('cost')->count())
                ->badgeColor('warning')
                ->modifyQueryUsing(fn (Builder $query) => $query->whereDoesntHave('cost')),
            'calculated' => Tab::make('Sudah Dihitung')
                ->badge(fn () => Sppd::where('status', 'approved')->whereHas('cost')->count())
                ->badgeColor('success')
                ->modifyQueryUsing(fn (Builder $query) => $query->whereHas('cost')),
        ];
    }
}
