<?php

namespace App\Filament\Resources\Users\Schemas;

use Filament\Schemas\Schema;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;

class UserForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
           ->components([
                Section::make('Account')
                    ->columns(2)
                    ->schema([
                        Select::make('employee_id')
                            ->label('Employee')
                            ->relationship(
                                name: 'employee',
                                titleAttribute: 'name',
                                modifyQueryUsing: fn ($query) => $query
                                    ->where('is_active', true),
                            )
                            ->searchable()
                            ->preload()
                            ->required(),

                        TextInput::make('username')
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->maxLength(100),

                        TextInput::make('name')
                            ->required()
                            ->maxLength(255),

                        TextInput::make('email')
                            ->email()
                            ->maxLength(255),

                        TextInput::make('password')
                            ->password()
                            ->dehydrated(fn ($state) => filled($state))
                            ->required(
                                fn ($operation) => $operation === 'create'
                            )
                            ->maxLength(255),

                        Toggle::make('is_active')
                            ->label('Active')
                            ->default(true),
                    ]),

                Section::make('Roles')
                    ->schema([
                        Select::make('roles')
                            ->label('Roles')
                            ->relationship(
                                name: 'roles',
                                titleAttribute: 'name',
                                modifyQueryUsing: fn ($query) => $query
                                    ->where('is_active', true),
                            )
                            ->multiple()
                            ->searchable()
                            ->preload(),
                    ]),
            ]);
    }
}
