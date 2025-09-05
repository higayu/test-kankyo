<?php

namespace App\Filament\Resources\SlackMessageResource\Pages;

use App\Filament\Resources\SlackMessageResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListSlackMessages extends ListRecords
{
    protected static string $resource = SlackMessageResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // 新規作成は不要なので、Actions\CreateActionは含めません
        ];
    }
} 