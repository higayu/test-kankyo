<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ScheduledEventResource\Pages;
use App\Filament\Resources\ScheduledEventResource\RelationManagers;
use App\Models\ScheduledEvent;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class ScheduledEventResource extends Resource
{
    protected static ?string $model = ScheduledEvent::class;

    protected static ?string $navigationIcon = 'heroicon-o-calendar';

    protected static ?string $navigationLabel = '予定管理';

    protected static ?string $modelLabel = '予定';

    protected static ?string $pluralModelLabel = '予定一覧';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('基本情報')
                    ->schema([
                        Forms\Components\TextInput::make('title')
                            ->label('タイトル')
                            ->required()
                            ->maxLength(255),
                        Forms\Components\Select::make('event_type')
                            ->label('予定タイプ')
                            ->options([
                                'meeting' => '会議',
                                'lunch' => '会食',
                                'task' => 'タスク',
                                'other' => 'その他',
                            ])
                            ->required(),
                        Forms\Components\Textarea::make('description')
                            ->label('説明')
                            ->maxLength(65535)
                            ->columnSpanFull(),
                    ])->columns(2),

                Forms\Components\Section::make('日時・場所')
                    ->schema([
                        Forms\Components\DateTimePicker::make('start_datetime')
                            ->label('開始日時')
                            ->required(),
                        Forms\Components\DateTimePicker::make('end_datetime')
                            ->label('終了日時'),
                        Forms\Components\TextInput::make('location')
                            ->label('場所')
                            ->maxLength(255),
                    ])->columns(2),

                Forms\Components\Section::make('参加者・優先度')
                    ->schema([
                        Forms\Components\TagsInput::make('participants')
                            ->label('参加者')
                            ->placeholder('メールアドレスを入力')
                            ->suggestions([
                                'user@example.com',
                                'admin@example.com',
                            ]),
                        Forms\Components\Select::make('priority')
                            ->label('優先度')
                            ->options([
                                'high' => '高',
                                'medium' => '中',
                                'low' => '低',
                            ])
                            ->default('medium')
                            ->required(),
                        Forms\Components\Select::make('status')
                            ->label('ステータス')
                            ->options([
                                'pending' => '未実施',
                                'completed' => '完了',
                                'cancelled' => 'キャンセル',
                            ])
                            ->default('pending')
                            ->required(),
                    ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('title')
                    ->label('タイトル')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('event_type')
                    ->label('予定タイプ')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'meeting' => 'info',
                        'lunch' => 'success',
                        'task' => 'warning',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'meeting' => '会議',
                        'lunch' => '会食',
                        'task' => 'タスク',
                        default => 'その他',
                    }),
                Tables\Columns\TextColumn::make('start_datetime')
                    ->label('開始日時')
                    ->dateTime('Y-m-d H:i')
                    ->sortable(),
                Tables\Columns\TextColumn::make('location')
                    ->label('場所')
                    ->searchable(),
                Tables\Columns\TextColumn::make('priority')
                    ->label('優先度')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'high' => 'danger',
                        'medium' => 'warning',
                        'low' => 'success',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'high' => '高',
                        'medium' => '中',
                        'low' => '低',
                        default => '未設定',
                    }),
                Tables\Columns\TextColumn::make('status')
                    ->label('ステータス')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'completed' => 'success',
                        'cancelled' => 'danger',
                        default => 'warning',
                    })
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'pending' => '未実施',
                        'completed' => '完了',
                        'cancelled' => 'キャンセル',
                        default => '未設定',
                    }),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('event_type')
                    ->label('予定タイプ')
                    ->options([
                        'meeting' => '会議',
                        'lunch' => '会食',
                        'task' => 'タスク',
                        'other' => 'その他',
                    ]),
                Tables\Filters\SelectFilter::make('priority')
                    ->label('優先度')
                    ->options([
                        'high' => '高',
                        'medium' => '中',
                        'low' => '低',
                    ]),
                Tables\Filters\SelectFilter::make('status')
                    ->label('ステータス')
                    ->options([
                        'pending' => '未実施',
                        'completed' => '完了',
                        'cancelled' => 'キャンセル',
                    ]),
                Tables\Filters\Filter::make('start_datetime')
                    ->label('開始日時')
                    ->form([
                        Forms\Components\DatePicker::make('start_from')
                            ->label('開始日（From）'),
                        Forms\Components\DatePicker::make('start_until')
                            ->label('開始日（Until）'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['start_from'],
                                fn (Builder $query, $date): Builder => $query->whereDate('start_datetime', '>=', $date),
                            )
                            ->when(
                                $data['start_until'],
                                fn (Builder $query, $date): Builder => $query->whereDate('start_datetime', '<=', $date),
                            );
                    })
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
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
            'index' => Pages\ListScheduledEvents::route('/'),
            'create' => Pages\CreateScheduledEvent::route('/create'),
            'edit' => Pages\EditScheduledEvent::route('/{record}/edit'),
        ];
    }
}
