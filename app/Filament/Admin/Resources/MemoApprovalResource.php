<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\MemoApprovalResource\Pages;
use App\Models\ApprovalRequest;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class MemoApprovalResource extends Resource
{
    protected static ?string $model = ApprovalRequest::class;

    protected static ?string $navigationIcon = 'heroicon-o-document-check';
    protected static ?string $navigationGroup = 'Persetujuan';
    protected static ?string $navigationLabel = 'Memo';
    protected static ?string $slug = 'memo-approvals';
    protected static ?string $modelLabel = 'Memo';
    protected static ?string $pluralModelLabel = 'Memo';

    public static function getNavigationBadge(): ?string
    {
        $count = static::getEloquentQuery()->where('status', 'pending')->count();
        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function getNavigationItems(): array
    {
        return [
            \Filament\Navigation\NavigationItem::make(static::getNavigationLabel())
                ->group(static::getNavigationGroup())
                ->parentItem(static::getNavigationParentItem())
                ->icon(static::getNavigationIcon())
                ->activeIcon(static::getActiveNavigationIcon())
                ->isActiveWhen(function () {
                    if (request()->routeIs(static::getRouteBaseName() . '.*')) {
                        return true;
                    }
                    if (request()->routeIs('filament.admin.resources.memos.review')) {
                        return request()->query('from') === 'approvals' || auth()->user()?->hasRole('Manager');
                    }
                    return false;
                })
                ->badge(static::getNavigationBadge(), color: static::getNavigationBadgeColor())
                ->badgeTooltip(static::getNavigationBadgeTooltip())
                ->sort(static::getNavigationSort())
                ->url(static::getNavigationUrl()),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()->where('document_type', \App\Models\Memo::class);
        $user = \Filament\Facades\Filament::auth()->user() ?? auth()->user();

        if (! $user || $user->hasRole(['Superadmin', 'Admin'])) {
            return $query;
        }

        $employee = $user->employee;
        if (! $employee) {
            return $query;
        }

        // Managers and users can see approval requests relevant to their department or where they are approver/creator
        return $query->where(function (Builder $q) use ($employee) {
            $q->whereHasMorph('document', [\App\Models\Memo::class], function (Builder $memoQuery) use ($employee) {
                $memoQuery->where('department_id', $employee->department_id)
                    ->orWhere('creator_employee_id', $employee->id)
                    ->orWhereHas('creator', function (Builder $creatorQuery) use ($employee) {
                        $creatorQuery->where('manager_id', $employee->id);
                    });
            });
        });
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('status')
                    ->label('Status')
                    ->disabled(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('document.nomor_memo')
                    ->label('Nomor Memo')
                    ->searchable()
                    ->weight('bold'),
                Tables\Columns\TextColumn::make('document.creator.name')
                    ->label('Pengaju')
                    ->searchable(),
                Tables\Columns\TextColumn::make('document.agenda')
                    ->label('Agenda')
                    ->searchable()
                    ->wrap(),
                Tables\Columns\TextColumn::make('document.date')
                    ->label('Tanggal Memo')
                    ->date('d/m/Y'),
                Tables\Columns\TextColumn::make('status')
                    ->label('Status Persetujuan')
                    ->sortable()
                    ->badge()
                    ->icon(fn (string $state): string => match ($state) {
                        'pending' => 'heroicon-m-clock',
                        'approved' => 'heroicon-m-check-circle',
                        'rejected' => 'heroicon-m-x-circle',
                        default => 'heroicon-m-question-mark-circle',
                    })
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'pending' => 'Menunggu Persetujuan',
                        'approved' => 'Disetujui',
                        'rejected' => 'Ditolak',
                        default => ucfirst($state),
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'pending' => 'warning',
                        'approved' => 'success',
                        'rejected' => 'danger',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Diajukan Pada')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label('Status Persetujuan')
                    ->options([
                        'pending' => 'Menunggu Persetujuan',
                        'approved' => 'Disetujui',
                        'rejected' => 'Ditolak',
                    ]),
            ])
            ->actions([
                Tables\Actions\Action::make('review')
                    ->label('Proses')
                    ->icon('heroicon-o-play-circle')
                    ->button()
                    ->size('sm')
                    ->color('warning')
                    ->url(fn (\App\Models\ApprovalRequest $record) => \App\Filament\Admin\Resources\MemoResource::getUrl('review', ['record' => $record->document_id, 'from' => 'approvals']))
                    ->visible(fn (\App\Models\ApprovalRequest $record) => $record->status === 'pending'),
                Tables\Actions\Action::make('view_review')
                    ->label('Lihat')
                    ->icon('heroicon-o-document-magnifying-glass')
                    ->button()
                    ->size('sm')
                    ->color('gray')
                    ->url(fn (\App\Models\ApprovalRequest $record) => \App\Filament\Admin\Resources\MemoResource::getUrl('review', ['record' => $record->document_id, 'from' => 'approvals']))
                    ->visible(fn (\App\Models\ApprovalRequest $record) => $record->status !== 'pending'),
                Tables\Actions\Action::make('print')
                    ->label('Cetak')
                    ->icon('heroicon-o-printer')
                    ->button()
                    ->size('sm')
                    ->color('info')
                    ->url(fn (\App\Models\ApprovalRequest $record) => route('memos.print', $record->document_id))
                    ->openUrlInNewTab()
                    ->visible(fn (\App\Models\ApprovalRequest $record) => $record->status === 'approved'),
            ])
            ->bulkActions([
                //
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListMemoApprovals::route('/'),
        ];
    }
}
