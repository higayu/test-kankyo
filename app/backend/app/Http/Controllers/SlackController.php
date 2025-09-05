<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Models\SlackMessage;

class SlackController extends Controller
{
    public function handleEvent(Request $request)
    {
        // Slackからのチャレンジリクエストに対応
        if ($request->has('challenge')) {
            return response()->json(['challenge' => $request->challenge]);
        }

        // イベントの検証
        if (!$this->verifySlackRequest($request)) {
            Log::error('Invalid Slack request signature');
            return response()->json(['error' => 'Invalid request'], 401);
        }

        $payload = $request->all();
        
        // イベントタイプの確認
        if ($payload['type'] === 'event_callback') {
            $event = $payload['event'];
            
            // メッセージイベントの処理
            if ($event['type'] === 'message') {
                return $this->handleMessageEvent($event);
            }
        }

        return response()->json(['status' => 'ok']);
    }

    public function handleCommand(Request $request)
    {
        // Slackコマンドの検証
        if (!$this->verifySlackRequest($request)) {
            Log::error('Invalid Slack command request');
            return response()->json(['error' => 'Invalid request'], 401);
        }

        $command = $request->get('command');
        $text = $request->get('text');
        $userId = $request->get('user_id');
        $channelId = $request->get('channel_id');

        // /ask-ai コマンドの処理
        if ($command === '/ask-ai') {
            return $this->handleAskAiCommand($text, $userId, $channelId);
        }

        return response()->json(['text' => '未対応のコマンドです。']);
    }

    public function handleInteraction(Request $request)
    {
        $payload = json_decode($request->get('payload'), true);
        
        if (!$this->verifySlackRequest($request)) {
            Log::error('Invalid Slack interaction request');
            return response()->json(['error' => 'Invalid request'], 401);
        }

        // インタラクションタイプの確認
        switch ($payload['type']) {
            case 'block_actions':
                return $this->handleBlockActions($payload);
            case 'view_submission':
                return $this->handleViewSubmission($payload);
        }

        return response()->json(['status' => 'ok']);
    }

    private function verifySlackRequest(Request $request): bool
    {
        $signingSecret = env('SLACK_SIGNING_SECRET');
        $timestamp = $request->header('X-Slack-Request-Timestamp');
        $signature = $request->header('X-Slack-Signature');

        // タイムスタンプの検証（5分以上古いリクエストは拒否）
        if (abs(time() - $timestamp) > 300) {
            return false;
        }

        $baseString = "v0:{$timestamp}:" . $request->getContent();
        $calculatedSignature = 'v0=' . hash_hmac('sha256', $baseString, $signingSecret);

        return hash_equals($calculatedSignature, $signature);
    }

    private function handleMessageEvent(array $event)
    {
        try {
            // メッセージの保存
            SlackMessage::create([
                'channel' => $event['channel'],
                'user' => $event['user'],
                'text' => $event['text'],
                'ts' => $event['ts'],
                'thread_ts' => $event['thread_ts'] ?? null,
            ]);

            return response()->json(['status' => 'ok']);
        } catch (\Exception $e) {
            Log::error('Failed to handle message event', [
                'error' => $e->getMessage(),
                'event' => $event
            ]);
            return response()->json(['error' => 'Internal error'], 500);
        }
    }

    private function handleAskAiCommand(string $text, string $userId, string $channelId)
    {
        // AIへの問い合わせ処理をここに実装
        return response()->json([
            'response_type' => 'in_channel',
            'text' => "あなたの質問: {$text}\n処理中です..."
        ]);
    }

    private function handleBlockActions(array $payload)
    {
        $actions = $payload['actions'][0];
        $actionId = $actions['action_id'];
        
        // アクションに応じた処理
        switch ($actionId) {
            case 'regenerate':
                // 再生成処理
                break;
            case 'save':
                // 保存処理
                break;
            case 'summarize':
                // 要約処理
                break;
        }

        return response()->json(['status' => 'ok']);
    }

    private function handleViewSubmission(array $payload)
    {
        // モーダルやダイアログの送信処理
        return response()->json(['status' => 'ok']);
    }
} 