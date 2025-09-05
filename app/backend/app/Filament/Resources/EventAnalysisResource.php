<?php

namespace App\Filament\Resources;

use App\Filament\Resources\EventAnalysisResource\Pages;
use App\Filament\Resources\EventAnalysisResource\RelationManagers;
use App\Models\EventAnalysis;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Database\Eloquent\Model;

class EventAnalysisResource extends Resource
{
    protected static ?string $model = EventAnalysis::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    protected static ?string $navigationLabel = '分析結果';

    protected static ?string $modelLabel = '分析結果';

    protected static ?string $pluralModelLabel = '分析結果一覧';

    protected static ?int $navigationSort = 2;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('slack_message_id')
                    ->relationship('slackMessage', 'text')
                    ->required()
                    ->label('Slackメッセージ'),
                Forms\Components\Select::make('analysis_type')
                    ->options([
                        'event_extraction' => 'イベント抽出',
                    ])
                    ->required()
                    ->label('分析タイプ'),
                Forms\Components\DateTimePicker::make('event_start_datetime')
                    ->label('予定開始日時'),
                Forms\Components\DateTimePicker::make('event_end_datetime')
                    ->label('予定終了日時'),
                Forms\Components\TextInput::make('event_title')
                    ->label('予定タイトル')
                    ->maxLength(255),
                Forms\Components\TextInput::make('event_type')
                    ->label('予定タイプ')
                    ->maxLength(255),
                Forms\Components\TextInput::make('confidence_score')
                    ->numeric()
                    ->label('信頼度スコア'),
                Forms\Components\Select::make('analysis_status')
                    ->options([
                        'pending' => '処理待ち',
                        'processing' => '処理中',
                        'success' => '成功',
                        'failed' => '失敗',
                    ])
                    ->required()
                    ->label('分析ステータス'),
                Forms\Components\KeyValue::make('extracted_data')
                    ->label('抽出データ')
                    ->keyLabel('キー')
                    ->valueLabel('値'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('slackMessage.text')
                    ->label('Slackメッセージ')
                    ->limit(50)
                    ->searchable(),
                Tables\Columns\TextColumn::make('event_title')
                    ->label('予定タイトル')
                    ->searchable(),
                Tables\Columns\TextColumn::make('event_type')
                    ->label('予定タイプ')
                    ->searchable(),
                Tables\Columns\TextColumn::make('event_start_datetime')
                    ->label('開始日時')
                    ->dateTime()
                    ->sortable(),
                Tables\Columns\TextColumn::make('event_end_datetime')
                    ->label('終了日時')
                    ->dateTime()
                    ->sortable(),
                Tables\Columns\TextColumn::make('analysis_type')
                    ->label('分析タイプ'),
                Tables\Columns\TextColumn::make('confidence_score')
                    ->label('信頼度')
                    ->numeric(2),
                Tables\Columns\TextColumn::make('analysis_status')
                    ->label('ステータス')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'success' => 'success',
                        'failed' => 'danger',
                        'processing' => 'warning',
                        default => 'secondary',
                    }),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('作成日時')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('analysis_status')
                    ->options([
                        'pending' => '処理待ち',
                        'processing' => '処理中',
                        'success' => '成功',
                        'failed' => '失敗',
                    ])
                    ->label('ステータス'),
                Tables\Filters\Filter::make('has_event')
                    ->query(fn (Builder $query): Builder => $query->whereNotNull('event_start_datetime'))
                    ->label('予定あり'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListEventAnalyses::route('/'),
            'create' => Pages\CreateEventAnalysis::route('/create'),
            'edit' => Pages\EditEventAnalysis::route('/{record}/edit'),
        ];
    }
}
