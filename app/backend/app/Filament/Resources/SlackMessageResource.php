<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SlackMessageResource\Pages;
use App\Models\SlackMessage;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class SlackMessageResource extends Resource
{
    protected static ?string $model = SlackMessage::class;

    protected static ?string $navigationIcon = 'heroicon-o-chat-bubble-left-right';

    protected static ?string $navigationLabel = 'Slackメッセージ';

    protected static ?string $modelLabel = 'Slackメッセージ';

    protected static ?string $pluralModelLabel = 'Slackメッセージ';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('channel_id')
                    ->label('チャンネルID')
                    ->required()
                    ->maxLength(255),
                Forms\Components\TextInput::make('user')
                    ->label('ユーザー')
                    ->required()
                    ->maxLength(255),
                Forms\Components\Textarea::make('text')
                    ->label('メッセージ内容')
                    ->required()
                    ->columnSpanFull(),
                Forms\Components\DateTimePicker::make('timestamp')
                    ->label('投稿日時')
                    ->required(),
                Forms\Components\TextInput::make('slack_ts')
                    ->label('SlackメッセージID')
                    ->required()
                    ->maxLength(255),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('channel_id')
                    ->label('チャンネルID')
                    ->searchable(),
                Tables\Columns\TextColumn::make('user')
                    ->label('ユーザー')
                    ->searchable(),
                Tables\Columns\TextColumn::make('text')
                    ->label('メッセージ内容')
                    ->limit(50)
                    ->searchable()
                    ->formatStateUsing(fn (string $state): string => mb_convert_encoding($state, 'UTF-8', 'auto')),
                Tables\Columns\TextColumn::make('timestamp')
                    ->label('投稿日時')
                    ->dateTime('Y-m-d H:i:s')
                    ->sortable(),
                Tables\Columns\TextColumn::make('slack_ts')
                    ->label('SlackメッセージID')
                    ->searchable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('登録日時')
                    ->dateTime('Y-m-d H:i:s')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('updated_at')
                    ->label('更新日時')
                    ->dateTime('Y-m-d H:i:s')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
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
            'index' => Pages\ListSlackMessages::route('/'),
            'view' => Pages\ViewSlackMessage::route('/{record}'),
        ];
    }
} 