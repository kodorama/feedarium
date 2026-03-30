<?php

namespace App\Filament\Resources;

use App\Models\Feed;
use Filament\Tables;
use App\Models\Category;
use Filament\Tables\Table;
use Filament\Schemas\Schema;
use Filament\Resources\Resource;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\Textarea;
use Filament\Tables\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Actions\BulkActionGroup;
use Filament\Tables\Actions\DeleteBulkAction;
use App\Filament\Resources\FeedResource\Pages;

class FeedResource extends Resource
{
    protected static ?string $model = Feed::class;

    public static function getNavigationIcon(): string|\BackedEnum|null
    {
        return 'heroicon-o-rss';
    }

    public static function getNavigationSort(): ?int
    {
        return 2;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')
                ->required()
                ->maxLength(255),
            TextInput::make('url')
                ->label('Feed URL')
                ->required()
                ->url()
                ->maxLength(500)
                ->unique(ignoreRecord: true),
            Select::make('category_id')
                ->label('Category')
                ->options(fn () => Category::query()->orderBy('name')->pluck('name', 'id'))
                ->nullable()
                ->searchable(),
            Textarea::make('description')
                ->nullable()
                ->rows(3),
            Toggle::make('active')
                ->default(true),
            TextInput::make('hub_url')
                ->label('Hub URL (WebSub)')
                ->url()
                ->nullable()
                ->maxLength(500),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('category.name')
                    ->label('Category')
                    ->placeholder('—')
                    ->sortable(),
                Tables\Columns\TextColumn::make('url')->limit(50)->searchable(),
                Tables\Columns\IconColumn::make('active')->boolean()->sortable(),
                Tables\Columns\TextColumn::make('last_fetched_at')
                    ->dateTime()
                    ->sortable()
                    ->placeholder('Never'),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                TernaryFilter::make('active'),
            ])
            ->actions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListFeeds::route('/'),
            'create' => Pages\CreateFeed::route('/create'),
            'edit' => Pages\EditFeed::route('/{record}/edit'),
        ];
    }
}
