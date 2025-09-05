# SSL 認証の為に、ダウンロードした `cacert.pem`を以下に配置する必要がある(SSL認証をスキップする場合は必要なし)
 C:\\php\\extras\\ssl\\cacert.pem",

# ffmpegをノイズ除去に使用する場合
環境変数に以下を登録して再起動（ffmpegはダウンロードしてくる）
```
 C:\ffmpeg\bin
 ```
 ダウンロードのURL
 [ffmpegのダウンロード](https://ffmpeg.org/download.html)

phpの設定ファイルを編集する必要あり

```php.ini
;extension=ffi
```

# 返却値のjsonデータ
ポストマンでテスト送信
http://localhost:8001/api/audio/test-whisper-api

`app\backend\storage\app\test_audio\sample.wav`を送信する音声ファイルとして使用

```app\backend\app\Http\Controllers\AudioController.php
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
use OpenAI;

class AudioController extends Controller
{
    private $ffmpeg;
    private $audioProcessingService;
    private $fileUploadService;

    public function __construct(
        AudioProcessingService $audioProcessingService,
        FileUploadService $fileUploadService
    ) {
        $this->audioProcessingService = $audioProcessingService;
        $this->fileUploadService = $fileUploadService;

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

            return response()->json([
                'success' => true,
                'message' => 'ファイルの保存に成功しました',
                'data' => [
                    'file' => [
                        'path' => $uploadResult['path'],
                        'original_name' => $uploadResult['original_name'],
                        'size' => $uploadResult['size'],
                        'mime_type' => $uploadResult['mime_type']
                    ]
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
                ]
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
        Log::info('=== OpenAI Whisper API テスト開始 ===');
        $audioFile = null;
        
        try {
            // テスト用の固定ファイルパスを使用
            $audioPath = storage_path('app/test_audio/sample.wav');
            if (!file_exists($audioPath)) {
                throw new \RuntimeException('テスト用音声ファイルが見つかりません: ' . $audioPath);
            }

            Log::info('テストファイル読み込み', [
                'path' => $audioPath,
                'exists' => file_exists($audioPath),
                'size' => filesize($audioPath)
            ]);

            // OpenAIクライアントの初期化
            $client = \OpenAI::factory()
                ->withApiKey(env('OPENAI_API_KEY'))
                ->withHttpClient(new \GuzzleHttp\Client([
                    //'verify' => 'C:\\php\\extras\\ssl\\cacert.pem'
                    'verify' => false
                ]))
                ->make();

            // 音声ファイルを読み込む
            $audioFile = @fopen($audioPath, 'r');
            if ($audioFile === false) {
                $error = error_get_last();
                Log::error('ファイルオープンエラー', [
                    'error' => $error ? $error['message'] : '不明なエラー',
                    'path' => $audioPath
                ]);
                throw new \RuntimeException('音声ファイルの読み込みに失敗しました');
            }

            // Whisper APIを呼び出し
            $response = $client->audio()->transcribe([
                'model' => 'whisper-1',
                'file' => $audioFile,
                'response_format' => 'verbose_json',
                'language' => 'ja'
            ]);

            Log::info('Whisper API呼び出し成功', [
                'text' => $response->text,
                'segments_count' => count($response->segments)
            ]);

            return response()->json([
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
            ]);

        } catch (\Exception $e) {
            Log::error('=== OpenAI Whisper API テストエラー ===', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

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
        } finally {
            // ファイルハンドルが有効な場合のみクローズ
            if (is_resource($audioFile)) {
                @fclose($audioFile);
                Log::info('ファイルハンドルをクローズしました');
            }
        }
    }
} 
```

```
{
    "success": true,
    "text": "浅野ともみです 今日の東京株式市場で日経平均株価は小幅促進となっています 終わり値は昨日に比べ22円72銭高の11,088円58銭でした 当初一部の値上がり銘柄数は1146 対して値下がりは368 変わらずは104銘柄となっています ここでプレゼントのお知らせですこの番組では毎月発行のマンスリーレポート4月号を 抽選で10名様にプレゼントいたします お申し込みはお電話で東京03-0107-8373 03-0107-8373まで 以上番組からのお知らせでした",
    "segments": [
        {
            "id": 0,
            "start": 0,
            "end": 7.28000020980835,
            "text": "浅野ともみです 今日の東京株式市場で日経平均株価は小幅促進となっています"
        },
        {
            "id": 1,
            "start": 7.28000020980835,
            "end": 20.280000686645508,
            "text": "終わり値は昨日に比べ22円72銭高の11,088円58銭でした 当初一部の値上がり銘柄数は1146"
        },
        {
            "id": 2,
            "start": 20.280000686645508,
            "end": 27.440000534057617,
            "text": "対して値下がりは368 変わらずは104銘柄となっています"
        },
        {
            "id": 3,
            "start": 27.440000534057617,
            "end": 36.279998779296875,
            "text": "ここでプレゼントのお知らせですこの番組では毎月発行のマンスリーレポート4月号を 抽選で10名様にプレゼントいたします"
        },
        {
            "id": 4,
            "start": 36.279998779296875,
            "end": 46.040000915527344,
            "text": "お申し込みはお電話で東京03-0107-8373 03-0107-8373まで"
        },
        {
            "id": 5,
            "start": 46.040000915527344,
            "end": 50,
            "text": "以上番組からのお知らせでした"
        }
    ]
}
```

# Whisper API のレスポンスに含まれる segments の start と end 
- 各発話セグメントが音声ファイル中のどこからどこまでかを示す タイムスタンプ（秒単位の浮動小数点数） です。

説明
```
start: セグメントの開始時刻（秒）
end: セグメントの終了時刻（秒）
```
**たとえば以下のセグメント：**

```
{
  "id": 0,
  "start": 0,
  "end": 16.31999969482422,
  "text": "はい相談支援センターで山ほどと申します"
}
```

これは、音声ファイルの 0.0 秒から 16.32 秒まで に「はい相談支援センターで山ほどと申します」という音声が含まれている、という意味です。


Whisper API 単体では「要約」や「キーポイント抽出」はできませんが、Whisperで文字起こしした結果を使って、別途 ChatGPT（Text API） に命令すれば実現可能です。

✅ 方法：Whisper + ChatGPT を組み合わせる
① Whisper API（音声→テキスト）
Whisperで音声をテキスト化（すでにやっている処理）：
```


$response = $this->client->audio()->transcribe([
    'model' => 'whisper-1',
    'file' => $audioFile,
    'response_format' => 'verbose_json',
    'language' => 'ja'
]);
$text = $response->text;
```

② ChatGPT（テキスト→要約／キーポイント抽出）
Whisperで得られた $text を、OpenAIの Chat API に渡して命令します：

```
$summaryResponse = $this->client->chat()->create([
    'model' => 'gpt-4',
    'messages' => [
        ['role' => 'system', 'content' => 'あなたは有能な会話分析者です。'],
        ['role' => 'user', 'content' => "以下の会話を要約し、キーポイントを抽出してください。\n\n$text"]
    ]
]);
$summary = $summaryResponse->choices[0]->message->content;
```

📦 結果として得られるもの（例）
```
{
    "success": true,
    "text": "（Whisper書き起こし全文）",
    "segments": [ ... ],
    "summary": "相談者は中学2年の息子が不登校であることに悩み、支援を求めて電話をした。妻との対応方針の違いや、仕事への影響も語られた。",
    "key_points": [
        "息子が3ヶ月不登校",
        "体調不良を訴えるが医学的問題なし",
        "妻は様子見派、父は積極的対応希望",
        "父親自身のメンタルも疲弊気味"
    ]
}
```

🔧 実装アドバイス
Whisper の結果をそのまま GPT に流すのが最も手軽

トークン長に注意（長すぎるとエラーになる）

必要に応じて文字起こし結果を要約前にトリミング or セグメント単位で送信

🧩 まとめ
機能	API	備考
音声の文字起こし	Whisper API (audio.transcribe)	日本語対応、正確
要約・キーポイント抽出	Chat API (chat.create)	gpt-3.5 でも可能、gpt-4 の方が精度高い

必要であれば、Laravel内でこの連携まで組み込むサンプルコードをご用意できます。ご希望があればお知らせください。