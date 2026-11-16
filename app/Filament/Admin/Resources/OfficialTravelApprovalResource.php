<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\OfficialTravelApprovalResource\Pages;
use App\Filament\Admin\Support\RecapTableSupport;
use App\Models\ApprovalRequest;
use App\Models\OfficialTravelRecap;
use Filament\Resources\Resource;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class OfficialTravelApprovalResource extends Resource
{
    protected static ?string $model = ApprovalRequest::class;

    protected static ?string $navigationIcon = 'heroicon-o-document-chart-bar';

    protected static ?string $navigationGroup = 'Persetujuan';

    protected static ?string $navigationLabel = 'Persetujuan Rekap Perjalanan Dinas';

    protected static ?string $slug = 'official-travel-approvals';

    protected static ?string $modelLabel = 'Persetujuan Rekap Perjalanan Dinas';

    protected static ?string $pluralModelLabel = 'Persetujuan Rekap Perjalanan Dinas';

    protected static ?int $navigationSort = 4;

    public static function canViewAny(): bool
    {
        $user = \Filament\Facades\Filament::auth()->user() ?? auth()->user();

        return (bool) $user?->hasAnyRole(['Superadmin', 'Admin', 'Manager', 'Division Head', 'HR']);
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

    public static function getNavigationBadge(): ?string
    {
        return RecapTableSupport::pendingApprovalBadge(OfficialTravelRecap::class);
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->where('document_type', OfficialTravelRecap::class)
            ->with(['document', 'requester', 'steps.policyStep', 'steps.approver']);
    }

    public static function table(Table $table): Table
    {
        return RecapTableSupport::approvalTable(
            $table,
            OfficialTravelRecapResource::class,
            'recaps.travel.print'
        );
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListOfficialTravelApprovals::route('/'),
        ];
    }
}

