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
        $count = static::getEloquentQuery()->where('status', 'pending')->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()
            ->where('document_type', TransportAccommodationRecap::class)
            ->whereIn('document_id', TransportAccommodationRecap::select('id'))
            ->with(['document', 'requester', 'steps.policyStep', 'steps.approver']);

        $user = \Filament\Facades\Filament::auth()->user() ?? auth()->user();
        if (! $user || $user->hasAnyRole(['Superadmin', 'Admin', 'Division Head'])) {
            return $query;
        }

        $employee = $user->employee;
        if ($employee && ! $employee->isBusinessDivisionHead() && $employee->work_area_id) {
            $query->where(function (Builder $q) use ($employee) {
                $q->whereHas('requester', fn (Builder $rq) => $rq->where('work_area_id', $employee->work_area_id))
                    ->orWhereHasMorph('document', [TransportAccommodationRecap::class], function (Builder $docQuery) use ($employee) {
                        $docQuery->whereHas('businessTrip.sppds.employee', fn (Builder $eq) => $eq->where('work_area_id', $employee->work_area_id));
                    });
            });
        }

        return $query;
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

