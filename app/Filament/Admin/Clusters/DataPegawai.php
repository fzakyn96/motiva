<?php

namespace App\Filament\Admin\Clusters;

use Filament\Clusters\Cluster;

class DataPegawai extends Cluster
{
    protected static ?string $navigationIcon = 'heroicon-o-user-group';

    protected static ?string $navigationGroup = 'Manajemen Pengguna';

    protected static ?string $navigationLabel = 'Data Pegawai';

    protected static ?string $clusterBreadcrumb = 'Data Pegawai';

    protected static ?int $navigationSort = 1;

    public static function canAccess(): bool
    {
        $user = \Filament\Facades\Filament::auth()->user() ?? auth()->user();
        if (! $user) return false;
        return $user->hasAnyRole(['Superadmin', 'Admin', 'HR']);
    }

    public static function getNavigationBadge(): ?string
    {
        $count = \App\Models\Employee::count();
        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'info';
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return 'Total Data Pegawai';
    }
}
