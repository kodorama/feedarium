<?php

namespace App\Domains\User\Controllers;

use Filament\Forms;
use App\Models\User;
use Filament\Tables;
use Filament\Resources\Form;
use Filament\Resources\Table;
use Filament\Resources\Resource;

class UserResource extends Resource
{
    protected static string $model = User::class;

    protected static ?string $navigationIcon = 'heroicon-o-user';

    protected static ?string $navigationGroup = 'Users';

    protected static ?string $label = 'User';

    protected static ?string $pluralLabel = 'Users';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('name')->required()->maxLength(255),
                Forms\Components\TextInput::make('email')->required()->email()->maxLength(255),
                Forms\Components\Toggle::make('is_admin')->label('Admin')->default(false),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')->searchable(),
                Tables\Columns\TextColumn::make('email')->searchable(),
                Tables\Columns\IconColumn::make('is_admin')->boolean(),
                Tables\Columns\TextColumn::make('created_at')->dateTime(),
            ])
            ->filters([
                // Add filters if needed
            ]);
    }

    public static function canViewAny(): bool
    {
        return auth()->user()?->is_admin === true;
    }

    public static function canAccessPanel(): bool
    {
        return auth()->user()?->is_admin === true;
    }
}
