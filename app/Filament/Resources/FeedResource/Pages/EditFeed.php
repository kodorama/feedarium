<?php

namespace App\Filament\Resources\FeedResource\Pages;

use Filament\Actions;
use App\Filament\Resources\FeedResource;
use Filament\Resources\Pages\EditRecord;

class EditFeed extends EditRecord
{
    protected static string $resource = FeedResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
