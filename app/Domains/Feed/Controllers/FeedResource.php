<?php

namespace App\Domains\Feed\Controllers;

use Filament\Forms;
use App\Models\Feed;
use Filament\Tables;
use Filament\Resources\Form;
use Filament\Resources\Table;
use Filament\Resources\Resource;

class FeedResource extends Resource
{
    protected static string $model = Feed::class;

    protected static ?string $navigationIcon = 'heroicon-o-rss';

    protected static ?string $navigationGroup = 'Feeds';

    protected static ?string $label = 'Feed';

    protected static ?string $pluralLabel = 'Feeds';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('name')->required()->maxLength(255),
                Forms\Components\TextInput::make('url')->required()->url()->maxLength(255),
                Forms\Components\Textarea::make('description')->maxLength(1000),
                Forms\Components\Toggle::make('active')->default(true),
                Forms\Components\Select::make('category_id')
                    ->relationship('category', 'name')
                    ->nullable(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')->searchable(),
                Tables\Columns\TextColumn::make('url')->searchable(),
                Tables\Columns\TextColumn::make('description')->limit(50),
                Tables\Columns\IconColumn::make('active')->boolean(),
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
