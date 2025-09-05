<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use OpenAI;
use GuzzleHttp\Client;
use RuntimeException;

class Whisper_api
{
    private $client;

    public function __construct()
    {
        try {
            $this->client = OpenAI::factory()
                ->withApiKey(env('OPENAI_API_KEY'))
                ->withHttpClient(new Client([
                    'verify' => false
                ]))
                ->make();
        } catch (\Exception $e) {
            Log::error('OpenAIクライアントの初期化に失敗しました', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw new RuntimeException('OpenAIクライアントの初期化に失敗しました: ' . $e->getMessage());
        }
    }

        /**
     * OpenAI Whisper APIを使用して音声ファイルを文字起こしする（テスト用）
     *
     * @return array
     * @throws RuntimeException
     */
    public function WhisperApi($audioPath): array
    {
        Log::info('=== OpenAI Whisper API テスト開始 ===');
        $audioFile = null;
        
        try {
            // テスト用の固定ファイルパスを使用
            // $audioPath = storage_path('app/test_audio/sample.wav');
            if (!file_exists($audioPath)) {
                throw new RuntimeException('テスト用音声ファイルが見つかりません: ' . $audioPath);
            }

            Log::info('テストファイル読み込み', [
                'path' => $audioPath,
                'exists' => file_exists($audioPath),
                'size' => filesize($audioPath)
            ]);

            // 音声ファイルを読み込む
            $audioFile = @fopen($audioPath, 'r');
            if ($audioFile === false) {
                $error = error_get_last();
                Log::error('ファイルオープンエラー', [
                    'error' => $error ? $error['message'] : '不明なエラー',
                    'path' => $audioPath
                ]);
                throw new RuntimeException('音声ファイルの読み込みに失敗しました');
            }

            // Whisper APIを呼び出し
            $response = $this->client->audio()->transcribe([
                'model' => 'whisper-1',
                'file' => $audioFile,
                'response_format' => 'verbose_json',
                'language' => 'ja'
            ]);

            Log::info('Whisper API呼び出し成功', [
                'text' => $response->text,
                'segments_count' => count($response->segments)
            ]);

            return [
                'success' => true,
                'text' => $response->text,
                'segments' => array_map(function($segment) {
                    return [
                        'id' => $segment->id,
                        'start' => $segment->start,
                        'end' => $segment->end,
                        'text' => $segment->text
                    ];
                }, $response->segments)
            ];

        } catch (\Exception $e) {
            Log::error('=== OpenAI Whisper API テストエラー ===', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            throw new RuntimeException('文字起こしに失敗しました: ' . $e->getMessage());
        } finally {
            // ファイルハンドルが有効な場合のみクローズ
            if (is_resource($audioFile)) {
                @fclose($audioFile);
                Log::info('ファイルハンドルをクローズしました');
            }
        }
    }


    /**
     * OpenAI Whisper APIを使用して音声ファイルを文字起こしする（テスト用）
     *
     * @return array
     * @throws RuntimeException
     */
    public function testWhisperApi(): array
    {
        Log::info('=== OpenAI Whisper API テスト開始 ===');
        $audioFile = null;
        
        try {
            // テスト用の固定ファイルパスを使用
            $audioPath = storage_path('app/test_audio/sample.wav');
            if (!file_exists($audioPath)) {
                throw new RuntimeException('テスト用音声ファイルが見つかりません: ' . $audioPath);
            }

            Log::info('テストファイル読み込み', [
                'path' => $audioPath,
                'exists' => file_exists($audioPath),
                'size' => filesize($audioPath)
            ]);

            // 音声ファイルを読み込む
            $audioFile = @fopen($audioPath, 'r');
            if ($audioFile === false) {
                $error = error_get_last();
                Log::error('ファイルオープンエラー', [
                    'error' => $error ? $error['message'] : '不明なエラー',
                    'path' => $audioPath
                ]);
                throw new RuntimeException('音声ファイルの読み込みに失敗しました');
            }

            // Whisper APIを呼び出し
            $response = $this->client->audio()->transcribe([
                'model' => 'whisper-1',
                'file' => $audioFile,
                'response_format' => 'verbose_json',
                'language' => 'ja'
            ]);

            Log::info('Whisper API呼び出し成功', [
                'text' => $response->text,
                'segments_count' => count($response->segments)
            ]);

            return [
                'success' => true,
                'text' => $response->text,
                'segments' => array_map(function($segment) {
                    return [
                        'id' => $segment->id,
                        'start' => $segment->start,
                        'end' => $segment->end,
                        'text' => $segment->text
                    ];
                }, $response->segments)
            ];

        } catch (\Exception $e) {
            Log::error('=== OpenAI Whisper API テストエラー ===', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            throw new RuntimeException('文字起こしに失敗しました: ' . $e->getMessage());
        } finally {
            // ファイルハンドルが有効な場合のみクローズ
            if (is_resource($audioFile)) {
                @fclose($audioFile);
                Log::info('ファイルハンドルをクローズしました');
            }
        }
    }
}
