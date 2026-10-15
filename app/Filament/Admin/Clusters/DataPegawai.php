<?php

namespace App\Filament\Admin\Clusters;

use Filament\Clusters\Cluster;

class DataPegawai extends Cluster
{
    protected static ?string $navigationIcon = 'heroicon-o-user-group';

    protected static ?string $navigationGroup = 'Manajemen Pengguna';

    protected static ?string $navigationLabel = 'Data Pegawai';

    protected static ?string $clusterBreadcrumb = 'Data Pegawai';

    protected static ?int $navigationSort = 0;
}
