<?php

namespace App\Filament\Admin\Widgets;

use App\Filament\Admin\Resources\MemoApprovalResource;
use App\Filament\Admin\Resources\MemoResource;
use App\Models\ApprovalRequest;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class PendingMemoApprovalsWidget extends BaseWidget
{
    protected static ?string $heading = 'Memo Menunggu Persetujuan Anda';
    protected static ?int $sort = 2;
    protected int | string | array $columnSpan = 'full';

    public static function canView(): bool
    {
        $user = \Filament\Facades\Filament::auth()->user() ?? auth()->user();
        if (! $user) {
            return false;
        }

        return $user->hasRole(['Manager', 'Superadmin', 'Admin']);
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(
                MemoApprovalResource::getEloquentQuery()
                    ->where('status', 'pending')
                    ->latest('created_at')
            )
            ->columns([
                Tables\Columns\TextColumn::make('document.nomor_memo')
                    ->label('Nomor Memo')
                    ->weight('bold')
                    ->searchable(),
                Tables\Columns\TextColumn::make('document.creator.name')
                    ->label('Pengaju')
                    ->searchable(),
                Tables\Columns\TextColumn::make('document.agenda')
                    ->label('Agenda')
                    ->limit(55)
                    ->tooltip(fn ($record) => $record->document?->agenda),
                Tables\Columns\TextColumn::make('document.date')
                    ->label('Tanggal Memo')
                    ->date('d/m/Y'),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Waktu Pengajuan')
                    ->since(),
            ])
            ->actions([
                Tables\Actions\Action::make('review')
                    ->label('Proses')
                    ->icon('heroicon-o-play-circle')
                    ->button()
                    ->size('sm')
                    ->color('warning')
                    ->url(fn (ApprovalRequest $record) => MemoResource::getUrl('review', ['record' => $record->document_id, 'from' => 'approvals'])),
            ])
            ->paginated([5])
            ->emptyStateHeading('Tidak Ada Memo Menunggu Persetujuan')
            ->emptyStateDescription('Semua pengajuan memo di unit kerja Anda telah selesai ditinjau.')
            ->emptyStateIcon('heroicon-o-check-badge');
    }
}
