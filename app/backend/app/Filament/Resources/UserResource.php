<?php

namespace App\Filament\Resources;

use Filament\Forms;
use App\Models\User;
use Filament\Tables;
use Filament\Forms\Form;
use Filament\Tables\Table;
use Filament\Resources\Resource;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Database\Eloquent\Builder;
use App\Filament\Resources\UserResource\Pages;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use App\Filament\Resources\UserResource\RelationManagers;
use Filament\Forms\Components\Group;
use Illuminate\Support\Facades\Log;
use Illuminate\Database\Eloquent\Model;
use Filament\Notifications\Notification;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static ?string $modelLabel = 'スタッフ';
    protected static ?string $pluralModelLabel = 'スタッフ一覧';

    protected static ?string $navigationIcon = 'heroicon-o-users';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Group::make([
                    Forms\Components\TextInput::make('name')
                        ->label('名前')
                        ->required()
                        ->maxLength(255),
                    Forms\Components\TextInput::make('login_code')
                        ->label('ログインコード')
                        ->required()
                        ->unique(ignoreRecord: true)
                        ->maxLength(10),
                    Forms\Components\TextInput::make('password')
                        ->label('パスワード')
                        ->password()
                        ->revealable()
                        ->dehydrateStateUsing(fn(string $state): string => Hash::make($state))
                        ->dehydrated(fn(?string $state): bool => filled($state))
                        ->required(fn(string $operation): bool => $operation === 'create')
                        ->maxLength(255)
                        ->hiddenOn('edit'),
                ])
                    ->columns(2)
                    ->columnSpanFull(),

                Group::make([
                    Forms\Components\DatePicker::make('entry_date')
                        ->label('入社日')
                        ->default(now()->format('Y-m-d')),
                    Forms\Components\DatePicker::make('exit_date')
                        ->label('退社日')
                        ->default(null),
                    Forms\Components\Select::make('is_admin')
                        ->label('管理者権限')
                        ->default(3)
                        ->options([
                            0 => 'その他',
                            1 => 'システム管理者',
                            2 => '一般管理者',
                            3 => '一般職員',
                        ])
                        ->selectablePlaceholder(false)
                        ->columns(2),
                    // Forms\Components\TextInput::make('nfchasu')
                    //     ->label('NFCハッシュ')
                    //     ->maxLength(64)
                    //     ->unique(ignoreRecord: true)
                    //     ->columnSpanFull(),
                    Forms\Components\Textarea::make('note')
                        ->label('備考')
                        ->maxLength(1000)
                        ->columnSpanFull(),
                ])
                    ->columns(2)
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('名前')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('login_code')
                    ->label('ログインコード')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('is_admin')
                    ->label('権限')
                    ->formatStateUsing(function ($state) {
                        switch ($state) {
                            case 0:
                                return '一般職員';
                            case 1:
                                return 'システム管理者';
                            case 2:
                                return '一般管理者';
                            case 3:
                                return 'その他';
                            default:
                                return '不明';
                        }
                    })
                    ->badge()
                    ->color(function ($state) {
                        switch ($state) {
                            case 0:
                                return 'gray';
                            case 1:
                                return 'danger';
                            case 2:
                                return 'success';
                            case 3:
                                return 'gray';
                            default:
                                return 'gray';
                        }
                    })
                    ->sortable(),
                Tables\Columns\TextColumn::make('entry_date')
                    ->label('入社日')
                    ->dateTime('Y-m-d')
                    ->sortable(),
                Tables\Columns\TextColumn::make('exit_date')
                    ->label('在籍状況')
                    ->getStateUsing(function (User $record) {
                        if ($record->exit_date === null) {
                            return '在籍中';
                        }
                        return date('Y-m-d', strtotime($record->exit_date));
                    })
                    ->badge()
                    ->color('gray')
                    ->sortable(),
                // Tables\Columns\TextColumn::make('created_at')
                //     ->label('作成日時')
                //     ->dateTime('Y-m-d')
                //     ->sortable()
                //     ->toggleable(isToggledHiddenByDefault: true),
                // Tables\Columns\TextColumn::make('updated_at')
                //     ->label('更新日時')
                //     ->dateTime('Y-m-d')
                //     ->sortable()
                //     ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\Action::make('edit')
                    ->label('編集')
                    ->icon('heroicon-o-pencil')
                    ->color('warning')
                    ->modalHeading(fn($record) => '「' . $record->name . '」さんのスタッフデータを編集')
                    ->modalSubmitActionLabel('更新する')
                    ->modalCancelActionLabel('キャンセル')
                    ->successNotification(null)
                    ->visible(fn () => Auth::user() && in_array(Auth::user()->is_admin, [1, 2]))
                    ->form([
                        Forms\Components\TextInput::make('name')
                            ->label('名前')
                            ->required()
                            ->default(fn (User $record): string => $record->name),
                        Forms\Components\TextInput::make('login_code')
                            ->label('ログインコード')
                            ->required()
                            ->default(fn (User $record): string => $record->login_code),
                        Forms\Components\DatePicker::make('entry_date')
                            ->label('入社日')
                            ->default(fn (User $record) => $record->entry_date),
                        Forms\Components\DatePicker::make('exit_date')
                            ->label('退社日')
                            ->default(fn (User $record) => $record->exit_date),
                        Forms\Components\Select::make('is_admin')
                            ->label('管理者権限')
                            ->options([
                                0 => 'その他',
                                1 => 'システム管理者',
                                2 => '一般管理者',
                                3 => '一般職員',
                            ])
                            ->default(fn (User $record): int => $record->is_admin),
                        Forms\Components\Textarea::make('note')
                            ->label('備考')
                            ->default(fn (User $record): ?string => $record->note),
                        Forms\Components\TextInput::make('new_password')
                            ->label('新しいパスワード（変更する場合のみ入力）')
                            ->password()
                            ->revealable()
                            ->dehydrated(fn ($state) => filled($state))
                            ->required(false),
                    ])
                    ->action(function (array $data, User $record): void {
                        // 更新データの準備
                        $updateData = [
                            'name' => $data['name'],
                            'login_code' => $data['login_code'],
                            'entry_date' => $data['entry_date'],
                            'exit_date' => $data['exit_date'],
                            'is_admin' => $data['is_admin'],
                            'note' => $data['note'],
                        ];
                        
                        // パスワードが入力されていれば更新
                        if (isset($data['new_password']) && !empty($data['new_password'])) {
                            $updateData['password'] = Hash::make($data['new_password']);
                        }
                        
                        // データを更新
                        $record->update($updateData);
                    })
                    ->after(function (array $data) {
                        // 成功メッセージを表示（必ず実行されるように別途afterでも設定）
                        Notification::make()
                            ->title('スタッフを更新しました')
                            ->success()
                            ->send();
                    }),
                Tables\Actions\ActionGroup::make([
                    Tables\Actions\DeleteAction::make()
                        ->visible(fn () => Auth::user() && in_array(Auth::user()->is_admin, [1, 2]))
                        ->requiresConfirmation()
                        ->modalHeading(fn($record) => '「' . $record->name . '」さんを削除')
                        ->modalDescription('本当に削除しますか？この操作は元に戻せません。')
                        ->modalSubmitActionLabel('削除する')
                        ->modalCancelActionLabel('キャンセル')
                        ->successNotificationTitle('スタッフを削除しました')
                        ->successRedirectUrl(self::getUrl('index')),
                ]),
            ])
            ->bulkActions([
                // Tables\Actions\BulkActionGroup::make([
                //     Tables\Actions\DeleteBulkAction::make(),
                // ]),
            ])
            ->recordAction('edit');
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
            'index' => Pages\ListUsers::route('/'),
            'create' => Pages\CreateUser::route('/create'),
            // 編集ページのルートは無効化し、代わりにモーダル編集を強制する
            // 'edit' => Pages\EditUser::route('/{record}/edit'),
        ];
    }

    public static function getNavigationGroup(): ?string
    {
        return '基本情報管理';
    }

    public static function canAccess(): bool
    {
        return true;
    }

    public static function canCreate(): bool
    {
        $user = Auth::user();
        return $user && in_array($user->is_admin, [1, 2]);
    }

    public static function canEdit(Model $record): bool
    {
        $user = Auth::user();
        return $user && in_array($user->is_admin, [1, 2]);
    }

    public static function canDelete(Model $record): bool
    {
        $user = Auth::user();
        return $user && in_array($user->is_admin, [1, 2]);
    }
}
