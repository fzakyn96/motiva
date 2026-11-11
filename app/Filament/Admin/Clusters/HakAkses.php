<?php

namespace App\Filament\Admin\Clusters;

use Filament\Clusters\Cluster;

class HakAkses extends Cluster
{
    protected static ?string $navigationIcon = 'heroicon-o-shield-check';

    protected static ?string $navigationGroup = 'Manajemen Pengguna';

    protected static ?string $navigationLabel = 'Hak Akses & Pengguna';

    protected static ?string $clusterBreadcrumb = 'Hak Akses & Pengguna';

    protected static ?int $navigationSort = 2;

    public static function canAccess(): bool
    {
        $user = \Filament\Facades\Filament::auth()->user() ?? auth()->user();
        if (! $user) return false;
        return $user->hasAnyRole(['Superadmin', 'Admin']);
    }

    public static function getNavigationBadge(): ?string
    {
        $count = \App\Models\User::count();
        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'primary';
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return 'Total Akun Pengguna';
    }
}
