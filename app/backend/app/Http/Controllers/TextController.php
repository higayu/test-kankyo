<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Services\Gemini_Summary;
use App\Services\Test_text_push;
use App\Services\OpenAI_Summary;

class TextController extends Controller
{
    private Gemini_Summary $geminiSummary;
    private Test_text_push $testTextPush;
    private OpenAI_Summary $openaiSummary;

    public function __construct(
        Gemini_Summary $geminiSummary,
        Test_text_push $testTextPush,
        OpenAI_Summary $openaiSummary
    ) {
        $this->geminiSummary = $geminiSummary;
        $this->testTextPush = $testTextPush;
        $this->openaiSummary = $openaiSummary;
    }

    /**
     * Gemini APIを使用してテキストの要約とキーワード抽出をテストする
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function testGeminiText()
    {
        Log::info('=== Gemini Text API テスト開始 ===');

        try {
            // 1. テストテキストデータの取得
            $textData = $this->testTextPush->getTextData();
            
            if (!$textData['success']) {
                throw new \Exception('テキストデータの取得に失敗しました: ' . ($textData['message'] ?? '不明なエラー'));
            }

            // 2. テキストの要約とキーワード抽出
            $result = $this->geminiSummary->summarizeAndExtractKeywords($textData['text']);

            // 3. レスポンスの作成
            return response()->json([
                'success' => true,
                'message' => 'テキストの要約とキーワード抽出に成功しました',
                'data' => [
                    'summary' => $result['summary'],
                    'keywords' => $result['keywords'],
                    'original_text_length' => strlen($textData['text']),
                    'segments_count' => count($textData['segments'])
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('=== Gemini Text API テストエラー ===', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'テキスト処理に失敗しました: ' . $e->getMessage(),
                'error_details' => [
                    'type' => get_class($e),
                    'code' => $e->getCode(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine()
                ]
            ], 500);
        }
    }

    /**
     * OpenAI APIを使用してテキストの要約とキーワード抽出をテストする
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function testOpenAIsummarize()
    {
        Log::info('=== OpenAI Summarize API テスト開始 ===');

        try {
            // 1. テストテキストデータの取得
            $textData = $this->testTextPush->getTextData();
            
            if (!$textData['success']) {
                throw new \Exception('テキストデータの取得に失敗しました: ' . ($textData['message'] ?? '不明なエラー'));
            }

            // 2. テキストの要約とキーワード抽出
            $result = $this->openaiSummary->summarize($textData['text']);

            // 3. レスポンスの作成
            return response()->json([
                'success' => true,
                'message' => 'テキストの要約とキーワード抽出に成功しました',
                'data' => [
                    'summary' => $result['summary'],
                    'keywords' => $result['keywords'],
                    'original_text_length' => strlen($textData['text']),
                    'segments_count' => count($textData['segments'])
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('=== OpenAI Summarize API テストエラー ===', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'テキスト処理に失敗しました: ' . $e->getMessage(),
                'error_details' => [
                    'type' => get_class($e),
                    'code' => $e->getCode(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine()
                ]
            ], 500);
        }
    }
} 