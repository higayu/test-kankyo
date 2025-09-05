<?php

namespace App\Filament\Resources\ScheduledEventResource\Pages;

use App\Filament\Resources\ScheduledEventResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListScheduledEvents extends ListRecords
{
    protected static string $resource = ScheduledEventResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
