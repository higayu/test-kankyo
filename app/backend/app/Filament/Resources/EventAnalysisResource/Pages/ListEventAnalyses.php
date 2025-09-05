<?php

namespace App\Filament\Resources\EventAnalysisResource\Pages;

use App\Filament\Resources\EventAnalysisResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListEventAnalyses extends ListRecords
{
    protected static string $resource = EventAnalysisResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
