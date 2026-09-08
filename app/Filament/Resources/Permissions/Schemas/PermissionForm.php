<?php

namespace App\Filament\Resources\Permissions\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class PermissionForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Permission Information')
                    ->columns(2)
                    ->schema([
                        TextInput::make('code')
                            ->label('Code')
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->maxLength(150)
                            ->helperText('Unique identifier used by the authorization system.')
                            ->disabled(fn (string $operation): bool => $operation === 'edit'),

                        TextInput::make('module')
                            ->label('Module')
                            ->required()
                            ->maxLength(100)
                            ->disabled(fn (string $operation): bool => $operation === 'edit'),

                        TextInput::make('action')
                            ->label('Action')
                            ->required()
                            ->maxLength(100)
                            ->disabled(fn (string $operation): bool => $operation === 'edit'),

                        TextInput::make('name')
                            ->label('Name')
                            ->required()
                            ->maxLength(150),

                        Textarea::make('description')
                            ->label('Description')
                            ->rows(3)
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}