<?php

namespace App\Domains\News\Controllers;

use Filament\Forms;
use App\Models\News;
use Filament\Tables;
use Filament\Resources\Form;
use Filament\Resources\Table;
use Filament\Resources\Resource;

class NewsResource extends Resource
{
    protected static string $model = News::class;

    protected static ?string $navigationIcon = 'heroicon-o-document';

    protected static ?string $navigationGroup = 'Feeds';

    protected static ?string $label = 'News';

    protected static ?string $pluralLabel = 'News';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('feed_id')
                    ->relationship('feed', 'name')
                    ->required(),
                Forms\Components\TextInput::make('title')->required()->maxLength(255),
                Forms\Components\TextInput::make('link')->required()->url()->maxLength(255),
                Forms\Components\Textarea::make('description')->maxLength(1000),
                Forms\Components\DateTimePicker::make('published_at'),
                Forms\Components\TextInput::make('author')->maxLength(255),
                Forms\Components\TextInput::make('guid')->maxLength(255),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('title')->searchable(),
                Tables\Columns\TextColumn::make('feed.name')->label('Feed')->searchable(),
                Tables\Columns\TextColumn::make('author')->searchable(),
                Tables\Columns\TextColumn::make('published_at')->dateTime(),
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
