<?php

namespace App\Filament\Resources\EventAnalysisResource\Pages;

use App\Filament\Resources\EventAnalysisResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditEventAnalysis extends EditRecord
{
    protected static string $resource = EventAnalysisResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
