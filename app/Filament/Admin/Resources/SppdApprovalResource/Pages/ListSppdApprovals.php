<?php

namespace App\Filament\Admin\Resources\SppdApprovalResource\Pages;

use App\Filament\Admin\Resources\SppdApprovalResource;
use App\Filament\Admin\Widgets\SppdOverviewWidget;
use Filament\Resources\Components\Tab;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;

class ListSppdApprovals extends ListRecords
{
    protected static string $resource = SppdApprovalResource::class;

    protected static ?string $title = 'Daftar Persetujuan SPPD';

    protected function getHeaderActions(): array
    {
        return [];
    }

    protected function getHeaderWidgets(): array
    {
        return [
            SppdOverviewWidget::class,
        ];
    }

    public function getTabs(): array
    {
        return [
            'pending' => Tab::make('Menunggu Persetujuan')
                ->icon('heroicon-m-clock')
                ->badge(fn () => static::getResource()::getEloquentQuery()->where('status', 'pending')->count())
                ->badgeColor('warning')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', 'pending')),

            'approved' => Tab::make('Disetujui')
                ->icon('heroicon-m-check-circle')
                ->badge(fn () => static::getResource()::getEloquentQuery()->where('status', 'approved')->count())
                ->badgeColor('success')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', 'approved')),

            'rejected' => Tab::make('Ditolak')
                ->icon('heroicon-m-x-circle')
                ->badge(fn () => static::getResource()::getEloquentQuery()->where('status', 'rejected')->count())
                ->badgeColor('danger')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', 'rejected')),

            'all' => Tab::make('Semua')
                ->icon('heroicon-m-list-bullet')
                ->badge(fn () => static::getResource()::getEloquentQuery()->count()),
        ];
    }

    public function getDefaultActiveTab(): string | int | null
    {
        return 'pending';
    }
}
