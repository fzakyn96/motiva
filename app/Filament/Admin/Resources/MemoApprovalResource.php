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
                Tables\Actions\Action::make('print')
                    ->label('Cetak PDF')
                    ->icon('heroicon-o-printer')
                    ->color('info')
                    ->url(fn (\App\Models\ApprovalRequest $record) => route('memos.print', $record->document_id))
                    ->openUrlInNewTab()
                    ->visible(fn (\App\Models\ApprovalRequest $record) => $record->status === 'approved'),
                Tables\Actions\Action::make('approve')
                    ->label('Setujui')
                    ->color('success')
                    ->icon('heroicon-o-check')
                    ->requiresConfirmation()
                    ->modalHeading('Konfirmasi Persetujuan Memo')
                    ->modalDescription('Apakah Anda yakin ingin menyetujui memo ini?')
                    ->modalSubmitActionLabel('Ya, Setujui')
                    ->modalCancelActionLabel('Batal')
                    ->visible(fn (\App\Models\ApprovalRequest $record) => $record->status === 'pending')
                    ->action(function (\App\Models\ApprovalRequest $record) {
                        $user = auth()->user()->employee;
                        $resolver = new \App\Services\Approval\ApprovalResolver();
                        try {
                            $resolver->approve($record, $user, 'Disetujui melalui Dashboard');
                            \Filament\Notifications\Notification::make()->title('Memo Berhasil Disetujui')->success()->send();
                        } catch (\Exception $e) {
                            \Filament\Notifications\Notification::make()->title('Gagal Menyetujui Memo')->body($e->getMessage())->danger()->send();
                        }
                    }),
                Tables\Actions\Action::make('reject')
                    ->label('Tolak')
                    ->color('danger')
                    ->icon('heroicon-o-x-mark')
                    ->requiresConfirmation()
                    ->modalHeading('Tolak Pengajuan Memo')
                    ->modalDescription('Masukkan alasan penolakan memo ini.')
                    ->modalSubmitActionLabel('Tolak Memo')
                    ->modalCancelActionLabel('Batal')
                    ->visible(fn (\App\Models\ApprovalRequest $record) => $record->status === 'pending')
                    ->form([
                        Forms\Components\Textarea::make('notes')->required()->label('Alasan Penolakan'),
                    ])
                    ->action(function (\App\Models\ApprovalRequest $record, array $data) {
                        $user = auth()->user()->employee;
                        $resolver = new \App\Services\Approval\ApprovalResolver();
                        try {
                            $resolver->reject($record, $user, $data['notes']);
                            \Filament\Notifications\Notification::make()->title('Memo Berhasil Ditolak')->success()->send();
                        } catch (\Exception $e) {
                            \Filament\Notifications\Notification::make()->title('Gagal Menolak Memo')->body($e->getMessage())->danger()->send();
                        }
                    }),
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
