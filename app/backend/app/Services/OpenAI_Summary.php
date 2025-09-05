<?php

namespace App\Services;

use Exception;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class OpenAI_Summary
{
    private string $apiKey;
    private string $apiEndpoint = 'https://api.openai.com/v1/chat/completions';
    private string $model = 'gpt-3.5-turbo';

    public function __construct()
    {
        $this->apiKey = config('services.openai.api_key');
        if (empty($this->apiKey)) {
            throw new Exception('OpenAI APIキーが設定されていません。');
        }
    }

    /**
     * テキストを要約し、キーワードを抽出する
     *
     * @param string $text 要約対象のテキスト
     * @return array 要約文とキーワードを含む配列
     * @throws Exception
     */
    public function summarize(string $text): array
    {
        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Content-Type' => 'application/json',
            ])->post($this->apiEndpoint, [
                'model' => $this->model,
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => 'あなたは文章の要約とキーワード抽出の専門家です。与えられたテキストを要約し、重要なキーワードを抽出してください。'
                    ],
                    [
                        'role' => 'user',
                        'content' => "以下のテキストを要約し、重要なキーワードを抽出してください。\n\n{$text}\n\n要約は200文字程度で、キーワードは5つ程度抽出してください。"
                    ]
                ],
                'temperature' => 0.7,
                'max_tokens' => 500
            ]);

            if (!$response->successful()) {
                throw new Exception('OpenAI APIへのリクエストが失敗しました: ' . $response->body());
            }

            $result = $response->json();
            $content = $result['choices'][0]['message']['content'];

            // 要約とキーワードを分離
            $parts = explode("\n\n", $content);
            $summary = $parts[0] ?? '';
            $keywords = [];

            if (isset($parts[1])) {
                // キーワード行を処理
                $keywordLines = explode("\n", $parts[1]);
                foreach ($keywordLines as $line) {
                    if (preg_match('/^[・\-\*]\s*(.+)$/', $line, $matches)) {
                        $keywords[] = trim($matches[1]);
                    }
                }
            }

            return [
                'summary' => $summary,
                'keywords' => $keywords
            ];

        } catch (Exception $e) {
            Log::error('OpenAI要約処理でエラーが発生しました: ' . $e->getMessage());
            throw $e;
        }
    }
}
