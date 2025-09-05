<?php

namespace App\Console\Commands;

use App\Models\EventAnalysis;
use App\Models\ScheduledEvent;
use App\Models\SlackMessage;
use Illuminate\Console\Command;
use OpenAI;
use Illuminate\Support\Facades\Log;

class AnalyzeSlackMessages extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'slack:analyze-messages';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '未分析のSlackメッセージを分析し、予定情報を抽出します';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Slackメッセージの分析を開始します...');

        // OpenAIクライアントの初期化
        $client = OpenAI::client(config('openai.api_key'));

        // 未分析（is_analyzed が false）のメッセージを取得
        $messages = SlackMessage::where('is_analyzed', false)->get();

        if ($messages->isEmpty()) {
            $this->info('分析が必要なメッセージはありません。');
            return;
        }

        foreach ($messages as $message) {
            try {
                $this->info("メッセージID: {$message->id} の分析を開始");
                
                // OpenAI APIを使用してメッセージを分析
                $response = $client->chat()->create([
                    'model' => 'gpt-4',
                    'messages' => [
                        [
                            'role' => 'system',
                            'content' => 'あなたは予定情報を抽出する専門家です。以下のメッセージから予定の情報を抽出し、JSON形式で返してください。メッセージに複数の予定が含まれている場合は、配列形式で複数の予定情報を返してください。各予定の抽出する情報：イベントタイプ、タイトル、説明、開始日時、終了日時、場所、参加者、優先度。日時はISO 8601形式で返してください。'
                        ],
                        [
                            'role' => 'user',
                            'content' => $message->text
                        ]
                    ],
                    'temperature' => 0.3,
                ]);

                $content = $response->choices[0]->message->content;
                $analysisResults = (json_decode($content, true) ?? []);

                // 単一の予定情報の場合は配列に変換
                if (!isset($analysisResults[0])) {
                    $analysisResults = [$analysisResults];
                }

                if (empty($analysisResults)) {
                    $analysisResults = [['error' => '無効な分析結果（または空の応答）']];
                }

                foreach ($analysisResults as $analysisResult) {
                    if (isset($analysisResult['error'])) {
                        Log::warning('分析結果にエラーが含まれています', [
                            'message_id' => $message->id,
                            'error' => $analysisResult['error']
                        ]);
                        continue;
                    }

                    // 日本語キーを英語キーにマッピング
                    $mappedResult = [
                        'event_type' => $analysisResult['イベントタイプ'] ?? null,
                        'title' => $analysisResult['タイトル'] ?? null,
                        'description' => $analysisResult['説明'] ?? null,
                        'start_datetime' => $analysisResult['開始日時'] ?? null,
                        'end_datetime' => $analysisResult['終了日時'] ?? null,
                        'location' => $analysisResult['場所'] ?? null,
                        'participants' => $analysisResult['参加者'] ?? [],
                        'priority' => $analysisResult['優先度'] ?? null,
                    ];

                    // 分析結果を保存（オリジナルのデータと変換後のデータの両方を保存）
                    $analysis = EventAnalysis::create([
                        'slack_message_id' => $message->id,
                        'analysis_type' => 'event_extraction',
                        'extracted_data' => $analysisResult, // オリジナルの日本語データ
                        'confidence_score' => 0.8,
                        'analysis_status' => 'success',
                        'event_start_datetime' => isset($mappedResult['start_datetime']) ? 
                            \Carbon\Carbon::parse($mappedResult['start_datetime']) : null,
                        'event_end_datetime' => isset($mappedResult['end_datetime']) ? 
                            \Carbon\Carbon::parse($mappedResult['end_datetime']) : null,
                        'event_title' => $mappedResult['title'],
                        'event_type' => $mappedResult['event_type']
                    ]);

                    // 予定情報を保存（extracted_dataから必要な情報を抽出）
                    if (isset($mappedResult['start_datetime'])) {
                        try {
                            // 必須フィールドのバリデーション
                            $requiredFields = ['start_datetime', 'event_type', 'title'];
                            foreach ($requiredFields as $field) {
                                if (!isset($mappedResult[$field]) || empty($mappedResult[$field])) {
                                    throw new \Exception("必須フィールド {$field} が不足しています");
                                }
                            }

                            // 日時フォーマットの検証
                            $startDateTime = \Carbon\Carbon::parse($mappedResult['start_datetime']);
                            $endDateTime = isset($mappedResult['end_datetime']) 
                                ? \Carbon\Carbon::parse($mappedResult['end_datetime'])
                                : null;

                            // 予定情報の作成
                            $event = ScheduledEvent::create([
                                'slack_message_id' => $message->id,
                                'event_type' => $mappedResult['event_type'],
                                'title' => $mappedResult['title'],
                                'description' => $mappedResult['description'],
                                'start_datetime' => $startDateTime,
                                'end_datetime' => $endDateTime,
                                'location' => $mappedResult['location'],
                                'participants' => $mappedResult['participants'],
                                'status' => 'pending',
                                'priority' => $this->validatePriority($mappedResult['priority'] ?? 'medium')
                            ]);

                            // 分析結果に予定IDを紐付け
                            $analysis->update(['scheduled_event_id' => $event->id]);
                            
                            $this->info("予定情報の保存が完了しました（ID: {$event->id}）");
                        } catch (\Exception $e) {
                            Log::error('予定情報の保存に失敗', [
                                'message_id' => $message->id,
                                'error' => $e->getMessage(),
                                'extracted_data' => $analysisResult
                            ]);
                            
                            // 分析結果のステータスを更新
                            $analysis->update([
                                'analysis_status' => 'failed',
                                'extracted_data' => array_merge($analysisResult, ['error' => $e->getMessage()])
                            ]);
                            
                            $this->error("予定情報の保存に失敗しました: {$e->getMessage()}");
                            continue;
                        }
                    } else {
                        $this->warn("メッセージID: {$message->id} から一部の予定情報を抽出できませんでした");
                    }
                }

                // 分析完了時刻とフラグを更新
                $message->analyzed_at = now();
                $message->is_analyzed = true;
                $message->save();

                $this->info("メッセージID: {$message->id} の分析が完了しました");

            } catch (\Exception $e) {
                Log::error('メッセージ分析エラー', [
                    'message_id' => $message->id,
                    'error' => $e->getMessage()
                ]);

                // 分析失敗を記録
                EventAnalysis::create([
                    'slack_message_id' => $message->id,
                    'analysis_type' => 'event_extraction',
                    'extracted_data' => ['error' => $e->getMessage()],
                    'confidence_score' => 0,
                    'analysis_status' => 'failed'
                ]);

                // エラー時もフラグを更新（再分析を防ぐため）
                $message->analyzed_at = now();
                $message->is_analyzed = true;
                $message->save();

                $this->error("メッセージID: {$message->id} の分析中にエラーが発生しました: {$e->getMessage()}");
            }
        }

        $this->info('すべての未分析メッセージの分析が完了しました');
    }

    /**
     * 優先度の値を検証し、有効な値を返す
     *
     * @param string $priority
     * @return string
     */
    private function validatePriority(string $priority): string
    {
        $validPriorities = ['high', 'medium', 'low'];
        return in_array(strtolower($priority), $validPriorities) 
            ? strtolower($priority) 
            : 'medium';
    }
}
