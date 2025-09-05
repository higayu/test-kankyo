<?php

namespace App\Filament\Resources\UserResource\Pages;

use App\Filament\Resources\UserResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Model;

class EditUser extends EditRecord
{
    protected static string $resource = UserResource::class;

    // 保存ボタンをカスタマイズ
    protected function getSaveFormAction(): Actions\Action
    {
        return parent::getSaveFormAction()
            ->label('更新する')
            ->successNotification(null) // デフォルトの通知を無効化
            ->after(function () {
                // カスタムの通知を表示
                Notification::make()
                    ->title('スタッフを更新しました')
                    ->success()
                    ->send();
            });
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make()
                ->requiresConfirmation()  // 確認ダイアログを表示
                ->modalHeading(fn() => '「' . $this->record->name . '」さんのスタッフを削除')  // ヘッダータイトル
                ->modalDescription('本当に削除しますか？この操作は元に戻せません。') // 説明文
                ->modalSubmitActionLabel('削除する')  // 確定ボタンのラベル
                ->modalCancelActionLabel('キャンセル')  // キャンセルボタンのラベル
                ->successNotificationTitle('スタッフを削除しました')  // 成功時のメッセージ
                ->successRedirectUrl(UserResource::getUrl('index')),  // 削除成功後に一覧ページへリダイレクト
        ];
    }

    // Filament v3での編集後リダイレクト設定
    protected function getRedirectUrl(): string
    {
        // 編集後の遷移先を一覧ページに設定
        return $this->getResource()::getUrl('index');
    }
    
    // フォーム送信前にメッセージ準備
    protected function beforeSave(): void
    {
        // 名前を一時的に保存
        session()->flash('updated_user_name', $this->record->name);
    }
    
    // フォーム送信完了後に実行
    protected function afterFormProcessed(): void
    {
        // 成功メッセージは getSaveFormAction() で処理するため、
        // ここでは何もしない
    }
}
