<?php

namespace App\Services;

use Exception;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;

class Gemini_Summary
{
    private $apiKey;
    private $model = 'gemini-1.5-flash-latest';
    private const MAX_RETRIES = 3;
    private const DEFAULT_RETRY_DELAY_SECONDS = 60;
    private const API_URL = 'https://generativelanguage.googleapis.com/v1beta/models/%s:generateContent';

    public function __construct()
    {
        try {
            $this->apiKey = env('GEMINI_API_KEY');
            Log::info('Gemini_Summary初期化', [
                'api_key_exists' => !empty($this->apiKey),
                'api_key_length' => strlen($this->apiKey ?? ''),
                'env_file_exists' => file_exists(base_path('.env'))
            ]);
            
            if (empty($this->apiKey)) {
                throw new Exception('GEMINI_API_KEY is not set in .env file');
            }
        } catch (\Exception $e) {
            Log::error('Gemini_Summary初期化エラー', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }

    /**
     * テキストの要約とキーワードを抽出する
     * 
     * @param string $text 要約・キーワード抽出対象のテキスト
     * @return array 要約とキーワードを含む配列
     */
    public function summarizeAndExtractKeywords(string $text): array
    {
        $prompt = "以下のテキストについて、以下の2つのタスクを実行してください：
1. テキストの要約（200文字程度）
2. 重要なキーワードの抽出（5-10個）

テキスト：
{$text}

出力形式：
要約：[要約文]
キーワード：[キーワード1, キーワード2, ...]";

        try {
            $response = $this->generateText($prompt);
            return $this->parseResponse($response);
        } catch (Exception $e) {
            throw new Exception('Gemini APIの呼び出しに失敗しました: ' . $e->getMessage());
        }
    }

    /**
     * テキストを生成する
     * 
     * @param string $prompt プロンプト
     * @param array $history チャット履歴（オプション）
     * @return string 生成されたテキスト
     */
    public function generateText(string $prompt, array $history = []): string
    {
        return $this->sendRequestWithRetry($prompt, function($p) use ($history) {
            return $this->callGeminiAPI($p, $history);
        });
    }

    /**
     * チャット形式でテキストを生成する
     * 
     * @param string $message メッセージ
     * @param array $history チャット履歴（オプション）
     * @return string 生成されたテキスト
     */
    public function chat(string $message, array $history = []): string
    {
        $contents = $history;
        $contents[] = [
            'role' => 'user',
            'parts' => [['text' => $message]]
        ];

        return $this->sendRequestWithRetry($message, function($m) use ($contents) {
            return $this->callGeminiAPI($m, $contents);
        });
    }

    /**
     * リトライ機能付きでAPIリクエストを送信
     * 
     * @param string $prompt プロンプト
     * @param callable $requestFunc 実際のリクエスト関数
     * @param int $retryCount 現在のリトライ回数
     * @return string APIレスポンス
     */
    private function sendRequestWithRetry(string $prompt, callable $requestFunc, int $retryCount = 0): string
    {
        try {
            return $requestFunc($prompt);
        } catch (Exception $e) {
            $errorMessage = $e->getMessage();
            Log::error('Gemini APIリクエストエラー', [
                'error' => $errorMessage,
                'retry_count' => $retryCount
            ]);

            if (strpos($errorMessage, '429') !== false && $retryCount < self::MAX_RETRIES) {
                $delay = $this->getRetryDelayFromResponse($errorMessage);
                Log::info("レート制限に達しました。{$delay}秒後にリトライします。", [
                    'attempt' => $retryCount + 1,
                    'max_retries' => self::MAX_RETRIES
                ]);
                sleep($delay);
                return $this->sendRequestWithRetry($prompt, $requestFunc, $retryCount + 1);
            }

            if (strpos($errorMessage, '429') !== false && $retryCount >= self::MAX_RETRIES) {
                throw new Exception("APIのレート制限に最終的に達しました。しばらく時間をおいてから再試行してください。");
            }

            throw $e;
        }
    }

    /**
     * レスポンスからリトライ遅延時間を取得
     * 
     * @param string $responseContent レスポンス内容
     * @return int リトライ遅延時間（秒）
     */
    private function getRetryDelayFromResponse(string $responseContent): int
    {
        if (preg_match('/"retryDelay"\s*:\s*"(\d+)s"/', $responseContent, $matches)) {
            $delay = (int)$matches[1];
            return max($delay, self::DEFAULT_RETRY_DELAY_SECONDS);
        }
        return self::DEFAULT_RETRY_DELAY_SECONDS;
    }

    /**
     * Gemini APIを呼び出す
     * 
     * @param string $prompt プロンプト
     * @param array $history チャット履歴（オプション）
     * @return string APIレスポンス
     */
    private function callGeminiAPI(string $prompt, array $history = []): string
    {
        $url = sprintf(self::API_URL, $this->model) . "?key={$this->apiKey}";
        
        $data = [
            'contents' => $history ?: [
                [
                    'role' => 'user',
                    'parts' => [
                        ['text' => $prompt]
                    ]
                ]
            ],
            'generationConfig' => [
                'temperature' => 0.7,
                'topK' => 40,
                'topP' => 0.95,
                'maxOutputTokens' => 1024,
            ]
        ];

        Log::info('Gemini APIリクエスト', [
            'url' => $url,
            'request_body' => $data
        ]);

        $response = Http::withHeaders([
            'Content-Type' => 'application/json'
        ])->post($url, $data);

        if (!$response->successful()) {
            $errorMessage = $response->body();
            Log::error('Gemini APIエラー', [
                'status' => $response->status(),
                'error' => $errorMessage
            ]);
            throw new Exception("API Error: {$response->status()} - {$errorMessage}");
        }

        $responseData = $response->json();
        Log::info('Gemini APIレスポンス', [
            'response' => $responseData
        ]);

        if (!isset($responseData['candidates'][0]['content']['parts'][0]['text'])) {
            throw new Exception("APIレスポンスの形式が不正です: " . json_encode($responseData, JSON_UNESCAPED_UNICODE));
        }

        return $responseData['candidates'][0]['content']['parts'][0]['text'];
    }

    /**
     * APIレスポンスを解析して要約とキーワードを抽出
     * 
     * @param string $response APIレスポンス
     * @return array 要約とキーワードの配列
     */
    private function parseResponse(string $response): array
    {
        $summary = '';
        $keywords = [];

        // 要約の抽出
        if (preg_match('/要約：(.*?)(?=キーワード：|$)/s', $response, $matches)) {
            $summary = trim($matches[1]);
        }

        // キーワードの抽出
        if (preg_match('/キーワード：\[(.*?)\]/s', $response, $matches)) {
            $keywords = array_map('trim', explode(',', $matches[1]));
        }

        return [
            'summary' => $summary,
            'keywords' => $keywords
        ];
    }
}
