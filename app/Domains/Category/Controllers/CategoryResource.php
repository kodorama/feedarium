<?php

namespace App\Domains\Category\Controllers;

use Filament\Forms;
use Filament\Tables;
use App\Models\Category;
use Filament\Resources\Form;
use Filament\Resources\Table;
use Filament\Resources\Resource;

class CategoryResource extends Resource
{
    protected static string $model = Category::class;

    protected static ?string $navigationIcon = 'heroicon-o-tag';

    protected static ?string $navigationGroup = 'Feeds';

    protected static ?string $label = 'Category';

    protected static ?string $pluralLabel = 'Categories';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('name')->required()->maxLength(255),
                Forms\Components\Textarea::make('description')->maxLength(1000),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')->searchable(),
                Tables\Columns\TextColumn::make('description')->limit(50),
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
