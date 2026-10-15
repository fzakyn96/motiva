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
    protected static ?string $navigationLabel = 'Persetujuan Memo';
    protected static ?string $slug = 'memo-approvals';
    protected static ?string $modelLabel = 'Persetujuan Memo';
    protected static ?string $pluralModelLabel = 'Persetujuan Memo';

    public static function getEloquentQuery(): Builder
    {
        // Only show Approval Requests for Memos
        return parent::getEloquentQuery()->where('document_type', \App\Models\Memo::class);
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
                    ->sortable()
                    ->weight('bold'),
                Tables\Columns\TextColumn::make('document.creator.name')
                    ->label('Pengaju')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('document.agenda')
                    ->label('Agenda')
                    ->searchable()
                    ->wrap(),
                Tables\Columns\TextColumn::make('document.date')
                    ->label('Tanggal Memo')
                    ->date('d/m/Y')
                    ->sortable(),
                Tables\Columns\TextColumn::make('status')
                    ->label('Status Persetujuan')
                    ->badge()
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
            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\Action::make('review')
                    ->label('Review & Persetujuan')
                    ->icon('heroicon-o-eye')
                    ->color('warning')
                    ->url(fn (\App\Models\ApprovalRequest $record) => \App\Filament\Admin\Resources\MemoResource::getUrl('review', ['record' => $record->document_id]))
                    ->visible(fn (\App\Models\ApprovalRequest $record) => $record->status === 'pending'),
                Tables\Actions\Action::make('view_review')
                    ->label('Lihat Review')
                    ->icon('heroicon-o-document-magnifying-glass')
                    ->color('gray')
                    ->url(fn (\App\Models\ApprovalRequest $record) => \App\Filament\Admin\Resources\MemoResource::getUrl('review', ['record' => $record->document_id]))
                    ->visible(fn (\App\Models\ApprovalRequest $record) => $record->status !== 'pending'),
                Tables\Actions\Action::make('print')
                    ->label('Cetak PDF')
                    ->icon('heroicon-o-printer')
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
