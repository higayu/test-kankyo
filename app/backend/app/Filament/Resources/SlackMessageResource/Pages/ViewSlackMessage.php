<?php

namespace App\Filament\Resources\SlackMessageResource\Pages;

use App\Filament\Resources\SlackMessageResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewSlackMessage extends ViewRecord
{
    protected static string $resource = SlackMessageResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // 編集と削除は不要なので、アクションは含めません
        ];
    }
} 