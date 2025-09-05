<?php

namespace App\Console\Commands;

use App\Models\SlackMessage;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class FetchSlackMessages extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'slack:fetch-messages';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '指定した Slack チャンネルのメッセージを取得し、データベースに保存します';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $token = config('slack_fetch.bot_token');
        $channelId = config('slack_fetch.channel_id');
        $slackUrl = 'https://slack.com/api/conversations.history';

        if (!$token || !$channelId) {
            $this->error('Slack のトークンまたはチャンネル ID が設定されていません。');
            return;
        }

        try {
            $response = Http::withToken($token)
                ->get($slackUrl, [
                    'channel' => $channelId,
                    'limit' => 100,
                ]);

            if ($response->successful()) {
                $data = $response->json();

                if ($data['ok']) {
                    $messages = $data['messages'];

                    if (!empty($messages)) {
                        $this->saveMessagesToDatabase($messages, $channelId);
                        $this->info(count($messages) . ' 件のメッセージをデータベースに保存しました。');
                    } else {
                        $this->info('新しいメッセージはありませんでした。');
                    }
                } else {
                    $this->error('Slack API エラー: ' . ($data['error'] ?? '不明なエラー'));
                    Log::error('Slack API エラー: ' . ($data['error'] ?? '不明なエラー'));
                }
            } else {
                $this->error('Slack API のリクエストに失敗しました: ' . $response->status());
                Log::error('Slack API のリクエストに失敗しました: ' . $response->status());
            }

        } catch (\Exception $e) {
            $this->error('エラーが発生しました: ' . $e->getMessage());
            Log::error('エラーが発生しました: ' . $e->getMessage());
        }
    }

    protected function saveMessagesToDatabase(array $messages, string $channelId)
    {
        foreach ($messages as $message) {
            SlackMessage::updateOrCreate(
                ['slack_ts' => $message['ts']], // Slack のメッセージ ID をキーとして重複を防ぐ
                [
                    'channel_id' => $channelId,
                    'user' => $message['user'] ?? null,
                    'text' => $message['text'] ?? null,
                    'timestamp' => isset($message['ts']) ? \Carbon\Carbon::createFromTimestamp($message['ts']) : null,
                ]
            );
        }
    }
}