<?php

namespace App\Console\Commands;

use App\Models\ScheduledEvent;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Http;

class NotifySlackEvents extends Command
{
    protected $signature = 'slack:notify-events';
    protected $description = '本日の予定を登録者にSlack通知で送信します。';

    private $client;
    private const NOTIFICATION_COOLDOWN = 30; // 分単位での通知のクールダウン期間

    public function __construct()
    {
        parent::__construct();
        
        if (app()->environment('local', 'development')) {
            // 開発環境でのみSSL証明書の検証を無効化
            Http::withOptions([
                'verify' => false
            ]);
        }
        
        // Slackトークンの検証
        if (empty(env('SLACK_BOT_TOKEN'))) {
            Log::error('SLACK_BOT_TOKEN is not set');
            throw new \RuntimeException('SLACK_BOT_TOKEN is not set');
        }

        $this->client = new Client([
            'base_uri' => 'https://slack.com/api/',
            'headers' => [
                'Authorization' => 'Bearer ' . env('SLACK_BOT_TOKEN'),
                'Content-Type' => 'application/json; charset=utf-8',
            ],
            'verify' => !app()->environment('local', 'development')
        ]);

        // トークンの検証
        try {
            $response = $this->client->post('auth.test');
            $data = json_decode($response->getBody(), true);
            if (!($data['ok'] ?? false)) {
                Log::error('Invalid SLACK_BOT_TOKEN', ['error' => $data['error'] ?? 'Unknown error']);
                throw new \RuntimeException('Invalid SLACK_BOT_TOKEN: ' . ($data['error'] ?? 'Unknown error'));
            }
        } catch (\Exception $e) {
            Log::error('Failed to validate SLACK_BOT_TOKEN', ['error' => $e->getMessage()]);
            throw new \RuntimeException('Failed to validate SLACK_BOT_TOKEN: ' . $e->getMessage());
        }
    }

    /**
     * Slackメッセージを送信する
     */
    private function sendSlackMessage(string $userId, string $message): bool
    {
        try {
            $response = $this->client->post('chat.postMessage', [
                'json' => [
                    'channel' => $userId, // ユーザーIDを指定してDMを送信
                    'text' => $message,
                    'as_user' => true
                ]
            ]);
            $data = json_decode($response->getBody(), true);
            return $data['ok'] ?? false;
        } catch (\Exception $e) {
            Log::error('Slackメッセージ送信エラー', [
                'user_id' => $userId,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * 通知履歴を更新
     */
    private function updateNotificationHistory(ScheduledEvent $event, string $userId): void
    {
        $history = $event->notification_history ?? [];
        $history[] = [
            'notified_at' => now()->toDateTimeString(),
            'user_id' => $userId
        ];

        $event->update([
            'last_notified_at' => now(),
            'notification_history' => $history
        ]);
    }

    /**
     * 通知可能かチェック
     */
    private function canNotify(ScheduledEvent $event): bool
    {
        if (!$event->is_notification_enabled) {
            return false;
        }

        if ($event->last_notified_at) {
            $lastNotified = Carbon::parse($event->last_notified_at);
            $cooldownEnds = $lastNotified->addMinutes(self::NOTIFICATION_COOLDOWN);
            if (now()->lt($cooldownEnds)) {
                return false;
            }
        }

        return true;
    }

    private function openDirectMessageChannel(string $userId): ?string
    {
        try {
            // ユーザーの存在確認
            $userResponse = $this->client->get('users.info', [
                'query' => ['user' => $userId]
            ]);
            
            $userData = json_decode($userResponse->getBody(), true);
            if (!($userData['ok'] ?? false)) {
                Log::error('ユーザーが見つかりません', [
                    'user_id' => $userId,
                    'error' => $userData['error'] ?? 'Unknown error'
                ]);
                return null;
            }

            // DMチャンネルを開く
            $response = $this->client->post('conversations.open', [
                'json' => [
                    'users' => $userId,
                    'return_im' => true
                ]
            ]);
            
            $data = json_decode($response->getBody(), true);
            if (!($data['ok'] ?? false)) {
                Log::error('DMチャンネルのオープンに失敗', [
                    'user_id' => $userId,
                    'error' => $data['error'] ?? 'Unknown error',
                    'response' => $data
                ]);
                return null;
            }
            
            return $data['channel']['id'] ?? null;
        } catch (\Exception $e) {
            Log::error('DMチャンネルのオープン時にエラー発生', [
                'user_id' => $userId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return null;
        }
    }

    /**
     * 指定したチャンネルにSlackメッセージを送信する
     */
    private function sendSlackMessageToChannel(string $channelId, string $message): bool
    {
        try {
            $response = $this->client->post('chat.postMessage', [
                'json' => [
                    'channel' => $channelId,
                    'text' => $message,
                    'as_user' => true
                ]
            ]);

            $data = json_decode($response->getBody(), true);
            
            if (!($data['ok'] ?? false)) {
                $error = $data['error'] ?? 'Unknown error';
                $errorDetails = '';
                
                // エラーメッセージの詳細化
                switch ($error) {
                    case 'missing_scope':
                        $errorDetails = 'ボットに必要な権限が不足しています。chat:write, channels:read, groups:read, channels:joinのスコープが必要です。';
                        break;
                    case 'channel_not_found':
                        $errorDetails = 'チャンネルが見つかりません。ボットがチャンネルに招待されているか確認してください。';
                        break;
                    case 'not_in_channel':
                        $errorDetails = 'ボットがチャンネルに参加していません。チャンネルにボットを招待してください。';
                        break;
                    case 'invalid_auth':
                        $errorDetails = 'ボットトークンが無効です。トークンを確認してください。';
                        break;
                    default:
                        $errorDetails = '予期せぬエラーが発生しました。';
                }

                Log::error('Slackチャンネルへのメッセージ送信エラー', [
                    'channel_id' => $channelId,
                    'error' => $error,
                    'error_details' => $errorDetails,
                    'response' => $data
                ]);
                
                $this->error("エラー: {$error}");
                $this->error("詳細: {$errorDetails}");
                return false;
            }

            return true;
        } catch (\Exception $e) {
            Log::error('Slackチャンネルへのメッセージ送信エラー', [
                'channel_id' => $channelId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            $this->error("例外発生: " . $e->getMessage());
            return false;
        }
    }

    /**
     * ユーザーにDMでSlackメッセージを送信する
     */
    private function sendSlackMessageToDM(string $userId, string $message): bool
    {
        try {
            // まずDMチャンネルを開く
            $channelId = $this->openDirectMessageChannel($userId);
            if (!$channelId) {
                $this->error("ユーザー {$userId} とのDMチャンネルを開けませんでした。");
                return false;
            }

            return $this->sendSlackMessageToChannel($channelId, $message);
        } catch (\Exception $e) {
            Log::error('SlackDMメッセージ送信エラー', [
                'user_id' => $userId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            $this->error("例外発生: " . $e->getMessage());
            return false;
        }
    }

    /**
     * チャンネルIDの有効性を確認する
     */
    private function validateChannelId(string $channelId): bool
    {
        try {
            $response = $this->client->get('conversations.info', [
                'query' => [
                    'channel' => $channelId
                ]
            ]);
            
            $data = json_decode($response->getBody(), true);
            if (!($data['ok'] ?? false)) {
                Log::error('チャンネルIDの検証に失敗', [
                    'channel_id' => $channelId,
                    'error' => $data['error'] ?? 'Unknown error'
                ]);
                return false;
            }
            
            return true;
        } catch (\Exception $e) {
            Log::error('チャンネルIDの検証時にエラー発生', [
                'channel_id' => $channelId,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * ユーザー情報を取得する
     */
    private function getUserInfo(string $userId): string
    {
        try {
            $response = $this->client->get('users.info', [
                'query' => ['user' => $userId]
            ]);
            
            $data = json_decode($response->getBody(), true);
            if (!($data['ok'] ?? false)) {
                Log::error('ユーザー情報の取得に失敗', [
                    'user_id' => $userId,
                    'error' => $data['error'] ?? 'Unknown error'
                ]);
                return $userId;
            }
            
            // ユーザーの表示名を優先し、なければ実名を返す
            return $data['user']['profile']['display_name'] ?: 
                   $data['user']['profile']['real_name'] ?: 
                   $userId;
        } catch (\Exception $e) {
            Log::error('ユーザー情報の取得時にエラー発生', [
                'user_id' => $userId,
                'error' => $e->getMessage()
            ]);
            return $userId;
        }
    }

    public function handle()
    {
        $this->info('予定通知処理を開始します。');

        // BOTトークンの確認
        if (empty(env('SLACK_BOT_TOKEN'))) {
            $this->error('SLACK_BOT_TOKENが設定されていません。');
            return;
        }

        // 通知先チャンネルIDの確認
        $notificationChannelId = env('SLACK_NOTIFICATION_CHANNEL_ID');
        if (empty($notificationChannelId)) {
            $this->error('SLACK_NOTIFICATION_CHANNEL_IDが設定されていません。');
            return;
        }

        // チャンネルIDの有効性を確認
        if (!$this->validateChannelId($notificationChannelId)) {
            $this->error('指定されたチャンネルIDが無効です。');
            return;
        }

        // 今日の予定を取得（SlackMessageとの関連を含む）
        $today = now()->startOfDay();
        $tomorrow = now()->addDay()->startOfDay();
        
        $events = ScheduledEvent::with('slackMessage')
            ->where('start_datetime', '>=', $today)
            ->where('start_datetime', '<', $tomorrow)
            ->where('status', '!=', 'cancelled')
            ->where(function ($query) {
                $query->whereNull('last_notified_at')
                    ->orWhere('last_notified_at', '<=', now()->subMinutes(self::NOTIFICATION_COOLDOWN));
            })
            ->where('is_notification_enabled', true)
            ->orderBy('start_datetime')
            ->get();
        
        if ($events->isEmpty()) {
            $this->info('本日の通知対象の予定はありません。');
            return;
        }

        $this->info(sprintf(
            '%s の予定が %d 件あります。',
            $today->format('Y-m-d'),
            $events->count()
        ));

        // 全ての予定をまとめてチャンネルに通知
        $messages = ["【本日の予定一覧】\n"];
        
        foreach ($events as $event) {
            if (!$this->canNotify($event)) {
                continue;
            }

            $startTime = $event->start_datetime->format('H:i');
            $createdBy = $event->slackMessage ? 
                "@" . $this->getUserInfo($event->slackMessage->user) : 
                '不明';
            
            $messages[] = sprintf(
                "⏰ %s\n" .
                "作成者: %s\n" .
                "タイトル: %s\n" .
                "説明: %s\n" .
                "%s" .
                "場所: %s\n" .
                "優先度: %s\n",
                $startTime,
                $createdBy,
                $event->title ?? '（無題）',
                $event->description ?? '（なし）',
                $event->end_datetime ? "終了時刻: " . $event->end_datetime->format('H:i') . "\n" : "",
                $event->location ?? '（未設定）',
                $event->priority ?? '（未設定）'
            );
        }

        // メッセージを送信
        $fullMessage = implode("\n", $messages);
        if ($this->sendSlackMessageToChannel($notificationChannelId, $fullMessage)) {
            $this->info("チャンネル {$notificationChannelId} に予定の通知を送信しました。");
            // 各予定の通知履歴を更新
            foreach ($events as $event) {
                if ($this->canNotify($event)) {
                    $this->updateNotificationHistory($event, 'channel');
                }
            }
        } else {
            $this->error("チャンネル {$notificationChannelId} への予定の通知送信に失敗しました。");
        }

        $this->info('予定通知処理が完了しました。');
    }
} 