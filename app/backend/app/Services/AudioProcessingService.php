<?php

namespace App\Services;

use FFMpeg\FFMpeg;
use FFMpeg\Format\Audio\Wav;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Illuminate\Support\Str;

class AudioProcessingService
{
    private string $whisperApiKey;
    private string $whisperApiUrl;
    private $ffmpeg;

    public function __construct()
    {
        $this->whisperApiKey = config('services.whisper.api_key');
        $this->whisperApiUrl = config('services.whisper.api_url', 'https://api.openai.com/v1/audio/transcriptions');

        if (empty($this->whisperApiKey)) {
            throw new RuntimeException('Whisper APIキーが設定されていません');
        }

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
                throw new RuntimeException('FFmpegバイナリが見つかりません。システムのPATHにFFmpegがインストールされているか確認してください。');
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
            throw new RuntimeException('FFmpegの初期化に失敗しました: ' . $e->getMessage());
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
     * 音声ファイルの処理（ノイズ除去と文字起こし）
     * @return array 処理結果
     */
    public function processAudio(string $audioPath): array
    {
        try {
            Log::info('音声処理開始', ['path' => $audioPath]);

            // ノイズ除去処理
            $processedAudioPath = $this->denoiseAudio($audioPath)['processed_path'];
            
            // 文字起こし処理
            $transcription = $this->transcribeAudio($processedAudioPath);

            return [
                'transcription' => $transcription,
                'processed_audio_path' => $processedAudioPath,
                'processed_audio_size' => filesize($processedAudioPath)
            ];

        } catch (\Exception $e) {
            Log::error('音声処理エラー', [
                'path' => $audioPath,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * 音声ファイルの文字起こし
     */
    public function transcribeAudio(string $audioPath): array
    {
        try {
            Log::info('文字起こし開始', ['path' => $audioPath]);

            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->whisperApiKey,
            ])->attach(
                'file',
                file_get_contents($audioPath),
                basename($audioPath)
            )->post($this->whisperApiUrl, [
                'model' => 'whisper-1',
                'response_format' => 'verbose_json',
                'language' => 'ja'
            ]);

            if (!$response->successful()) {
                throw new RuntimeException(
                    'Whisper APIエラー: ' . ($response->json()['error']['message'] ?? '不明なエラー')
                );
            }

            $result = $response->json();
            Log::info('文字起こし完了', [
                'text' => $result['text'],
                'segments_count' => count($result['segments'] ?? [])
            ]);

            return [
                'text' => $result['text'],
                'segments' => $result['segments'] ?? []
            ];

        } catch (\Exception $e) {
            Log::error('文字起こしエラー', [
                'path' => $audioPath,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }



    /**
     * 音声ファイルのノイズ除去処理
     * 
     * @param string $audioPath 処理対象の音声ファイルパス
     * @return array 処理結果（処理済みファイルのパスと情報）
     * @throws RuntimeException
     */
    public function denoiseAudio(string $audioPath): array
    {
        try {
            Log::info('=== ノイズ除去処理開始 ===', [
                'timestamp' => now()->toDateTimeString(),
                'path' => $audioPath,
                'file_exists' => file_exists($audioPath),
                'is_readable' => is_readable($audioPath),
                'file_size' => file_exists($audioPath) ? filesize($audioPath) : 0,
                'file_permissions' => file_exists($audioPath) ? substr(sprintf('%o', fileperms($audioPath)), -4) : null,
                'directory_exists' => file_exists(dirname($audioPath)),
                'directory_permissions' => file_exists(dirname($audioPath)) ? substr(sprintf('%o', fileperms(dirname($audioPath))), -4) : null,
                'php_user' => get_current_user(),
                'current_dir' => getcwd()
            ]);

            if (!file_exists($audioPath)) {
                Log::error('音声ファイルが見つかりません', [
                    'path' => $audioPath,
                    'absolute_path' => realpath($audioPath),
                    'directory_contents' => file_exists(dirname($audioPath)) ? scandir(dirname($audioPath)) : [],
                    'php_user' => get_current_user(),
                    'current_dir' => getcwd()
                ]);
                throw new RuntimeException('音声ファイルが見つかりません: ' . $audioPath);
            }

            // ファイルの読み取り権限チェック
            if (!is_readable($audioPath)) {
                Log::error('音声ファイルの読み取り権限がありません', [
                    'path' => $audioPath,
                    'permissions' => substr(sprintf('%o', fileperms($audioPath)), -4),
                    'php_user' => get_current_user()
                ]);
                throw new RuntimeException('音声ファイルの読み取り権限がありません: ' . $audioPath);
            }

            // ファイルサイズのチェック
            $fileSize = filesize($audioPath);
            if ($fileSize === 0) {
                Log::error('音声ファイルが空です', [
                    'path' => $audioPath,
                    'size' => $fileSize
                ]);
                throw new RuntimeException('音声ファイルが空です: ' . $audioPath);
            }

            // 保存先ディレクトリの設定
            $outputDir = 'processed/audio';
            $fullOutputDir = storage_path('app/' . $outputDir);

            Log::info('出力ディレクトリ情報', [
                'output_dir' => $outputDir,
                'full_output_dir' => $fullOutputDir,
                'exists' => file_exists($fullOutputDir),
                'is_writable' => is_writable($fullOutputDir),
                'permissions' => file_exists($fullOutputDir) ? substr(sprintf('%o', fileperms($fullOutputDir)), -4) : null
            ]);

            // 出力ディレクトリの作成
            if (!file_exists($fullOutputDir)) {
                Log::info('出力ディレクトリを作成します', ['path' => $fullOutputDir]);
                if (!@mkdir($fullOutputDir, 0777, true)) {
                    $error = error_get_last();
                    Log::error('出力ディレクトリの作成に失敗しました', [
                        'path' => $fullOutputDir,
                        'error' => $error,
                        'php_user' => get_current_user(),
                        'current_dir' => getcwd()
                    ]);
                    throw new RuntimeException('出力ディレクトリの作成に失敗しました: ' . $fullOutputDir);
                }
                // Windows環境でのパーミッション設定
                if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
                    @chmod($fullOutputDir, 0777);
                }
            }

            // 出力ファイル名の生成
            $originalName = basename($audioPath);
            $extension = pathinfo($originalName, PATHINFO_EXTENSION);
            $outputFileName = 'denoised_' . time() . '_' . Str::random(10) . '.' . $extension;
            $outputPath = $outputDir . '/' . $outputFileName;
            $fullOutputPath = storage_path('app/' . $outputPath);

            Log::info('ファイル処理情報', [
                'original_name' => $originalName,
                'extension' => $extension,
                'output_file_name' => $outputFileName,
                'output_path' => $outputPath,
                'full_output_path' => $fullOutputPath
            ]);

            // TODO: 実際のノイズ除去処理を実装
            // 現在はファイルをコピーするだけのダミー実装
            Log::info('ファイルコピー開始', [
                'source' => $audioPath,
                'destination' => $fullOutputPath,
                'source_size' => filesize($audioPath)
            ]);

            if (!@copy($audioPath, $fullOutputPath)) {
                $error = error_get_last();
                Log::error('処理済みファイルの保存に失敗しました', [
                    'source' => $audioPath,
                    'destination' => $fullOutputPath,
                    'error' => $error,
                    'php_user' => get_current_user()
                ]);
                throw new RuntimeException('処理済みファイルの保存に失敗しました');
            }

            // ファイルの権限を設定
            if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
                @chmod($fullOutputPath, 0666);
            } else {
                @chmod($fullOutputPath, 0644);
            }

            Log::info('=== ノイズ除去処理完了 ===', [
                'timestamp' => now()->toDateTimeString(),
                'input_path' => $audioPath,
                'output_path' => $outputPath,
                'file_size' => filesize($fullOutputPath),
                'file_permissions' => substr(sprintf('%o', fileperms($fullOutputPath)), -4),
                'is_readable' => is_readable($fullOutputPath),
                'is_writable' => is_writable($fullOutputPath)
            ]);

            return [
                'processed_path' => $outputPath,
                'original_path' => $audioPath,
                'original_name' => $originalName,
                'processed_name' => $outputFileName,
                'size' => filesize($fullOutputPath),
                'mime_type' => mime_content_type($fullOutputPath)
            ];

        } catch (\Exception $e) {
            Log::error('=== ノイズ除去処理エラー ===', [
                'timestamp' => now()->toDateTimeString(),
                'path' => $audioPath,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'php_user' => get_current_user(),
                'current_dir' => getcwd(),
                'memory_usage' => memory_get_usage(true)
            ]);
            throw $e;
        }
    }

    /**
     * 音声ファイルのノイズを除去する
     *
     * @param string $inputPath 入力ファイルのパス
     * @param string $outputPath 出力ファイルのパス
     * @return void
     * @throws RuntimeException
     */
    public function denoise(string $inputPath, string $outputPath): void
    {
        Log::info('ノイズ除去処理開始', [
            'input' => $inputPath,
            'output' => $outputPath
        ]);

        try {
            $audio = $this->ffmpeg->open($inputPath);
            
            // ノイズ除去フィルターを適用
            $audio->filters()->custom('afftdn');
            
            // WAVフォーマットで出力
            $format = new Wav();
            $format->setAudioChannels(2)
                   ->setAudioKiloBitrate(256);
            
            // 処理を実行
            $audio->save($format, $outputPath);
            
            Log::info('ノイズ除去処理完了', [
                'output_exists' => file_exists($outputPath),
                'output_size' => file_exists($outputPath) ? filesize($outputPath) : 0
            ]);
        } catch (\Exception $e) {
            Log::error('FFmpeg処理エラー', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw new RuntimeException('ノイズ除去処理に失敗しました: ' . $e->getMessage());
        }
    }
} 