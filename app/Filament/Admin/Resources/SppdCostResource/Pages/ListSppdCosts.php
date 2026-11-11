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
        return [];
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
