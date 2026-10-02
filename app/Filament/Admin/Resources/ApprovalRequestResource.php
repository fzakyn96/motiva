<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\ApprovalRequestResource\Pages;
use App\Filament\Admin\Resources\ApprovalRequestResource\RelationManagers;
use App\Models\ApprovalRequest;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class ApprovalRequestResource extends Resource
{
    protected static ?string $model = ApprovalRequest::class;

    protected static ?string $navigationIcon = 'heroicon-o-check-circle';
    protected static ?string $navigationGroup = 'Approvals';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Approval Request Details')
                    ->schema([
                        Forms\Components\Select::make('approval_policy_id')
                            ->relationship('policy', 'name')
                            ->required(),
                        Forms\Components\Select::make('requester_employee_id')
                            ->relationship('requester', 'name')
                            ->label('Requester')
                            ->required(),
                        Forms\Components\Select::make('status')
                            ->options([
                                'pending' => 'Pending Approval',
                                'approved' => 'Approved',
                                'rejected' => 'Rejected',
                            ])
                            ->required()
                            ->default('pending'),
                        Forms\Components\TextInput::make('document_type')
                            ->required()
                            ->maxLength(255),
                        Forms\Components\TextInput::make('document_id')
                            ->required()
                            ->numeric(),
                    ])
                    ->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('document_type')
                    ->searchable()
                    ->formatStateUsing(fn (string $state) => class_basename($state)),
                Tables\Columns\TextColumn::make('document_id')
                    ->sortable(),
                Tables\Columns\TextColumn::make('policy.name')
                    ->label('Policy')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('requester.name')
                    ->label('Requester')
                    ->sortable()
                    ->searchable()
                    ->weight('bold'),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'pending' => 'warning',
                        'approved' => 'success',
                        'rejected' => 'danger',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\Action::make('approve')
                    ->label('Approve')
                    ->color('success')
                    ->icon('heroicon-o-check')
                    ->requiresConfirmation()
                    ->visible(fn (\App\Models\ApprovalRequest $record) => $record->status === 'pending')
                    ->action(function (\App\Models\ApprovalRequest $record) {
                        $manager = \App\Models\Employee::where('identity_number', 'MGR01')->first(); // Simulated approver
                        $resolver = new \App\Services\Approval\ApprovalResolver();
                        try {
                            $resolver->approve($record, $manager, 'Approved via Dashboard');
                            \Filament\Notifications\Notification::make()->title('Approved')->success()->send();
                        } catch (\Exception $e) {
                            \Filament\Notifications\Notification::make()->title('Error')->body($e->getMessage())->danger()->send();
                        }
                    }),
                Tables\Actions\Action::make('reject')
                    ->label('Reject')
                    ->color('danger')
                    ->icon('heroicon-o-x-mark')
                    ->requiresConfirmation()
                    ->visible(fn (\App\Models\ApprovalRequest $record) => $record->status === 'pending')
                    ->form([
                        Forms\Components\Textarea::make('notes')->required(),
                    ])
                    ->action(function (\App\Models\ApprovalRequest $record, array $data) {
                        $manager = \App\Models\Employee::where('identity_number', 'MGR01')->first(); // Simulated approver
                        $resolver = new \App\Services\Approval\ApprovalResolver();
                        try {
                            $resolver->reject($record, $manager, $data['notes']);
                            \Filament\Notifications\Notification::make()->title('Rejected')->success()->send();
                        } catch (\Exception $e) {
                            \Filament\Notifications\Notification::make()->title('Error')->body($e->getMessage())->danger()->send();
                        }
                    }),
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
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
            'index' => Pages\ListApprovalRequests::route('/'),
            'create' => Pages\CreateApprovalRequest::route('/create'),
            'edit' => Pages\EditApprovalRequest::route('/{record}/edit'),
        ];
    }
}
