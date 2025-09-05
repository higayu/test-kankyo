<?php

namespace App\Filament\Resources\UserResource\Pages;

use App\Filament\Resources\UserResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Filament\Tables;

class ListUsers extends ListRecords
{
    protected static string $resource = UserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
    
    protected function getTableRecordUrlUsing(): ?\Closure
    {
        // 行クリック時のURLを無効化（モーダル編集を強制）
        return null;
    }
    
    protected function configureTable(Tables\Table $table): Tables\Table
    {
        return parent::configureTable($table)
            ->recordAction('edit');
    }
}
