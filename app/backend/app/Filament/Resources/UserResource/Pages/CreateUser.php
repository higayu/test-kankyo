<?php

namespace App\Filament\Resources\UserResource\Pages;

use App\Filament\Resources\UserResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;

class CreateUser extends CreateRecord
{
    protected static string $resource = UserResource::class;

    // デフォルトの成功通知を無効化
    protected function getCreatedNotificationTitle(): ?string
    {
        return null;
    }

    // レコード作成後の遷移先を一覧ページに変更
    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
    
    // 正しいメソッド名に変更
    protected function afterCreate(): void
    {
        // 成功メッセージを表示
        Notification::make()
            ->title('スタッフを新規作成しました')
            ->success()
            ->send();
    }

    protected function getFormActions(): array
    {
        return [
            Actions\Action::make('create')
                ->label('保存する')
                ->submit('create'),
            Actions\Action::make('cancel')
                ->label('キャンセル')
                ->url($this->getResource()::getUrl('index'))
                ->color('gray'),
        ];
    }

    protected function afterFormProcessed(): void
    {
        // レコード作成処理
    }

    protected function afterFormProcessingFailed(\Exception $e): void
    {
        // エラーを通知に変換
        Notification::make()
            ->title('エラーが発生しました')
            ->body('スタッフの作成に失敗しました。入力内容を確認してください。')
            ->danger()
            ->send();
        
        // ログにエラー詳細を記録
        Log::error('スタッフ作成エラー: ' . $e->getMessage());
    }
}
