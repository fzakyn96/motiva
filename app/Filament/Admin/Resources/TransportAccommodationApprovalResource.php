<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\TransportAccommodationApprovalResource\Pages;
use App\Filament\Admin\Support\RecapTableSupport;
use App\Models\ApprovalRequest;
use App\Models\TransportAccommodationRecap;
use Filament\Resources\Resource;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class TransportAccommodationApprovalResource extends Resource
{
    protected static ?string $model = ApprovalRequest::class;

    protected static ?string $navigationIcon = 'heroicon-o-ticket';

    protected static ?string $navigationGroup = 'Persetujuan';

    protected static ?string $navigationLabel = 'Persetujuan Rekap Tiket & Akomodasi';

    protected static ?string $slug = 'transport-accommodation-approvals';

    protected static ?string $modelLabel = 'Persetujuan Rekap Tiket & Akomodasi';

    protected static ?string $pluralModelLabel = 'Persetujuan Rekap Tiket & Akomodasi';

    protected static ?int $navigationSort = 3;

    public static function canViewAny(): bool
    {
        $user = \Filament\Facades\Filament::auth()->user() ?? auth()->user();

        return (bool) $user?->hasAnyRole(['Superadmin', 'Admin', 'Manager', 'Division Head']);
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
        return RecapTableSupport::pendingApprovalBadge(TransportAccommodationRecap::class);
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->where('document_type', TransportAccommodationRecap::class)
            ->with(['document', 'requester', 'steps.policyStep', 'steps.approver']);
    }

    public static function table(Table $table): Table
    {
        return RecapTableSupport::approvalTable(
            $table,
            TransportAccommodationRecapResource::class,
            'recaps.transport.print'
        );
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTransportAccommodationApprovals::route('/'),
        ];
    }
}

