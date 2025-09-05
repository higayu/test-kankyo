<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use FFMpeg\FFMpeg;
use FFMpeg\Coordinate\TimeCode;
use FFMpeg\Format\Audio\Wav;
use Illuminate\Support\Str;
use App\Services\AudioProcessingService;
use App\Services\FileUploadService;
use App\Services\Whisper_api;
use App\Services\Test_text_push;
use OpenAI;
use App\Services\Gemini_audio;
use RuntimeException;

class AudioController extends Controller
{
    private $ffmpeg;
    private $audioProcessingService;
    private $fileUploadService;
    private $whisperApi;
    private $testTextPush;
    private $geminiAudio;

    public function __construct(
        AudioProcessingService $audioProcessingService,
        FileUploadService $fileUploadService,
        Whisper_api $whisperApi,
        Test_text_push $testTextPush,
        Gemini_audio $geminiAudio
    ) {
        $this->audioProcessingService = $audioProcessingService;
        $this->fileUploadService = $fileUploadService;
        $this->whisperApi = $whisperApi;
        $this->testTextPush = $testTextPush;
        $this->geminiAudio = $geminiAudio;

        try {
            // FFmpegとFFProbeの実行ファイルを検索
            $ffmpegPath = $this->findExecutable('ffmpeg');
            $ffprobePath = $this->findExecutable('ffprobe');

            if (!$ffmpegPath || !$ffprobePath) {
                $error = [
                    'ffmpeg' => [
                        'found' => !empty($ffmpegPath),
                        'path' => $ffmpegPath
                    ],
                    'ffprobe' => [
                        'found' => !empty($ffprobePath),
                        'path' => $ffprobePath
                    ],
                    'path' => getenv('PATH'),
                    'php_user' => get_current_user()
                ];
                Log::error('FFmpegバイナリが見つかりません', $error);
                throw new \RuntimeException('FFmpegバイナリが見つかりません。システムのPATHにFFmpegがインストールされているか確認してください。');
            }

            $this->ffmpeg = FFMpeg::create([
                'ffmpeg.binaries'  => $ffmpegPath,
                'ffprobe.binaries' => $ffprobePath,
                'timeout'          => 3600,
                'ffmpeg.threads'   => 12,
            ]);

            // FFmpegの動作確認
            $this->ffmpeg->getFFMpegDriver()->getProcessBuilderFactory()->create('ffmpeg', ['-version']);
            Log::info('FFmpegの初期化に成功しました', [
                'ffmpeg_path' => $ffmpegPath,
                'ffprobe_path' => $ffprobePath
            ]);

        } catch (\Exception $e) {
            Log::error('FFmpegの初期化に失敗しました', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw new \RuntimeException('FFmpegの初期化に失敗しました: ' . $e->getMessage());
        }
    }

    /**
     * システムのPATHから実行ファイルを検索
     *
     * @param string $executable 実行ファイル名
     * @return string|null 実行ファイルのパス、見つからない場合はnull
     */
    private function findExecutable($executable)
    {
        // Windows環境の場合、拡張子を追加
        if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
            $executable .= '.exe';
        }

        // PATH環境変数を取得
        $path = getenv('PATH');
        if (!$path) {
            return null;
        }

        // PATHを配列に分割
        $paths = explode(PATH_SEPARATOR, $path);

        // 各ディレクトリで実行ファイルを検索
        foreach ($paths as $dir) {
            $file = $dir . DIRECTORY_SEPARATOR . $executable;
            if (file_exists($file) && is_executable($file)) {
                return $file;
            }
        }

        return null;
    }

    /**
     * 音声ファイルのノイズを除去する
     *
     * @param Request $request
     * @return \Illuminate\Http\Response
     */
    public function denoise(Request $request)
    {
        Log::info('=== ノイズ除去処理開始 ===');
        $uploadResult = null;
        $outputPath = null;

        try {
            // 1. ファイルのアップロードと保存
            Log::info('1. ファイルアップロード処理開始');
            
            $uploadResult = $this->fileUploadService->saveUploadedFile(
                $request,
                'uploads',
                ['wav', 'webm'],
                10 * 1024 * 1024 // 10MB
            );

            $inputPath = $uploadResult['full_path'];
            $outputPath = storage_path('app/processed/' . basename($uploadResult['path']));

            // 2. 出力ディレクトリの準備
            $outputDir = dirname($outputPath);
            if (!is_dir($outputDir)) {
                if (!mkdir($outputDir, 0777, true)) {
                    throw new \RuntimeException('出力ディレクトリの作成に失敗しました');
                }
                if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
                    chmod($outputDir, 0777);
                }
            }

            // 3. ノイズ除去処理
            Log::info('3. ノイズ除去処理開始');
            $this->audioProcessingService->denoise($inputPath, $outputPath);

            // 4. 一時ファイルの削除
            Log::info('4. 一時ファイル削除開始');
            Storage::delete($uploadResult['path']);
            Log::info('4. 一時ファイル削除完了');

            // 5. レスポンス返却
            Log::info('5. レスポンス返却開始');
            $response = response()->download($outputPath)->deleteFileAfterSend();
            Log::info('5. レスポンス返却完了');
            Log::info('=== ノイズ除去処理正常終了 ===');

            return $response;

        } catch (\Exception $e) {
            Log::error('=== ノイズ除去処理エラー ===', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            // ファイルの保存は成功している場合のメッセージを準備
            $successMessage = '';
            if ($uploadResult && file_exists($uploadResult['full_path'])) {
                $successMessage = '※音声ファイルの保存は成功しています。';
            }

            // 一時ファイルのクリーンアップ
            if (isset($uploadResult['path'])) {
                Log::info('クリーンアップ: 入力ファイル削除試行', ['path' => $uploadResult['path']]);
                Storage::delete($uploadResult['path']);
            }
            if (isset($outputPath) && file_exists($outputPath)) {
                Log::info('クリーンアップ: 出力ファイル削除試行', ['path' => $outputPath]);
                unlink($outputPath);
            }

            return response()->json([
                'message' => '音声処理に失敗しました: ' . $e->getMessage() . ($successMessage ? "\n" . $successMessage : ''),
                'error_details' => [
                    'type' => get_class($e),
                    'code' => $e->getCode(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine()
                ],
                'file_saved' => $uploadResult ? [
                    'path' => $uploadResult['path'],
                    'original_name' => $uploadResult['original_name'],
                    'size' => $uploadResult['size']
                ] : null
            ], 500);
        }
    }

    /**
     * 音声ファイルの保存テスト
     *
     * @param Request $request
     * @return \Illuminate\Http\Response
     */
    public function testSave(Request $request)
    {
        Log::info('=== ノイズ除去処理開始 ===');
        $uploadResult = null;
        $outputPath = null;

        try {
            // 1. ファイルのアップロードと保存
            Log::info('1. ファイルアップロード処理開始');
            
            $uploadResult = $this->fileUploadService->saveUploadedFile(
                $request,
                'uploads',
                ['wav', 'webm'],
                10 * 1024 * 1024 // 10MB
            );

            Log::info('ファイルアップロード完了', [
                'path' => $uploadResult['path'],
                'original_name' => $uploadResult['original_name'],
                'size' => $uploadResult['size']
            ]);

            $audioPath = $uploadResult['full_path'];

            try {
                $result = $this->whisperApi->WhisperApi($audioPath);
                // テキストデータを取得
                $textData= $result;
            } catch (\Exception $e) {
                return response()->json([
                    'success' => false,
                    'message' => $e->getMessage(),
                    'error_details' => [
                        'type' => get_class($e),
                        'code' => $e->getCode(),
                        'file' => $e->getFile(),
                        'line' => $e->getLine()
                    ]
                ], 500);
            }


            return response()->json([
                'success' => true,
                'message' => 'ファイルの保存に成功しました',
                'data' => [
                    'file' => [
                        'path' => $uploadResult['path'],
                        'original_name' => $uploadResult['original_name'],
                        'size' => $uploadResult['size'],
                        'mime_type' => $uploadResult['mime_type']
                    ],
                    'text_data' => $textData
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('=== ファイル保存テストエラー ===', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'message' => 'ファイルの保存に失敗しました: ' . $e->getMessage(),
                'error_details' => [
                    'type' => get_class($e),
                    'code' => $e->getCode(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine()
                ],
            ], 500);
        }
    }

    /**
     * OpenAI Whisper APIを使用して音声ファイルを文字起こしする（テスト用）
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function testWhisperApi()
    {
        try {
            $result = $this->whisperApi->testWhisperApi();
            return response()->json($result);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
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
     * Gemini Speech APIを使用して音声ファイルを文字起こしする（テスト用）
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function testGeminiSpeechApi()
    {
        Log::info('=== Gemini Speech API テスト開始 ===');
        $uploadResult = null;

        try {

            // $audioPath = $uploadResult['full_path'];
            // テスト用の固定ファイルパスを使用
            $audioPath = storage_path('app/test_audio/sample.wav');
            if (!file_exists($audioPath)) {
                throw new RuntimeException('テスト用音声ファイルが見つかりません: ' . $audioPath);
            }

            // 2. 音声ファイルの形式を検証
            if (!$this->geminiAudio->describeAudio($audioPath)) {
                throw new \Exception('サポートされていない音声ファイル形式です。対応形式: wav, mp3, flac');
            }

            // 3. Gemini Speech APIで文字起こし
            $transcript = $this->geminiAudio->transcribeAudio($audioPath);

            // 4. 一時ファイルの削除
            Storage::delete($uploadResult['path']);

            return response()->json([
                'success' => true,
                'message' => '文字起こしに成功しました',
                'data' => [
                    'transcript' => $transcript,
                    'file_info' => [
                        'original_name' => $uploadResult['original_name'],
                        'size' => $uploadResult['size'],
                        'mime_type' => $uploadResult['mime_type']
                    ]
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('=== Gemini Speech API テストエラー ===', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            // 一時ファイルのクリーンアップ
            if ($uploadResult && isset($uploadResult['path'])) {
                Storage::delete($uploadResult['path']);
            }

            return response()->json([
                'success' => false,
                'message' => '文字起こしに失敗しました: ' . $e->getMessage(),
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