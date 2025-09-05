<?php

namespace App\Services;

use Google\GenerativeAI\Client;
use Google\GenerativeAI\GenerativeModel;
use Exception;
use GuzzleHttp\Client as GuzzleClient;

class Gemini_audio
{
    private $apiKey;
    private $client;
    private $model;

    // 利用可能なモデル一覧
    public const AVAILABLE_MODELS = [
        'gemini-1.5-pro-latest' => '最新版、音声・画像などを扱えるモデル',
        'gemini-1.5-flash-latest' => '軽量・高速モデル',
        'gemini-1.5-pro' => '安定版モデル'
    ];

    public function __construct(string $model = 'gemini-1.5-pro-latest')
    {
        $apiKey = getenv('GEMINI_API_KEY');
        
        if (!array_key_exists($model, self::AVAILABLE_MODELS)) {
            throw new Exception('指定されたモデルは利用できません。利用可能なモデル: ' . implode(', ', array_keys(self::AVAILABLE_MODELS)));
        }
        
        $this->model = $model;

        if (empty($apiKey)) {
            throw new Exception('GEMINI_API_KEYが設定されていません。');
        }

        try {
            $this->apiKey = $apiKey;
            $this->client = new GuzzleClient([
                'api_key' => $this->apiKey
            ]);
        } catch (Exception $e) {
            throw new Exception('Gemini AIクライアントの初期化に失敗しました: ' . $e->getMessage());
        }
    }

    /**
     * 音声ファイルをアップロードし、その内容を説明するテキストを生成する
     *
     * @param string $audioFilePath 音声ファイルのパス
     * @return string 生成されたテキスト
     * @throws Exception
     */
    public function describeAudio(string $audioFilePath): string
    {
        try {
            if (!file_exists($audioFilePath)) {
                throw new Exception('音声ファイルが見つかりません。');
            }

            // 音声ファイルをBase64エンコード
            $audioData = base64_encode(file_get_contents($audioFilePath));

            // Gemini APIを直接呼び出し
            $response = $this->client->post('https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-pro-latest:generateContent', [
                'headers' => [
                    'Content-Type' => 'application/json',
                    'x-goog-api-key' => $this->apiKey
                ],
                'json' => [
                    'contents' => [
                        [
                            'parts' => [
                                [
                                    'inline_data' => [
                                        'mime_type' => 'audio/wav',
                                        'data' => $audioData
                                    ]
                                ],
                                ['text' => 'Describe this audio clip']
                            ]
                        ]
                    ]
                ]
            ]);

            $result = json_decode($response->getBody(), true);
            return $result['candidates'][0]['content']['parts'][0]['text'] ?? '音声の説明を生成できませんでした。';

        } catch (Exception $e) {
            throw new Exception('音声の説明生成中にエラーが発生しました: ' . $e->getMessage());
        }
    }

    /**
     * 音声ファイルを文字起こしする
     * 注意: このメソッドは音声ファイルをテキストに変換する別のサービス（例：Whisper API）と
     * 組み合わせて使用する必要があります。
     *
     * @param string $audioFilePath 音声ファイルのパス
     * @param array $options オプション
     * @return string 文字起こしされたテキスト
     * @throws Exception
     */
    public function transcribeAudio(string $audioFilePath, array $options = []): string
    {
        try {
            if (!file_exists($audioFilePath)) {
                throw new Exception('音声ファイルが見つかりません。');
            }

            // TODO: ここで音声ファイルをテキストに変換する処理を実装
            // 例: Whisper APIを使用して音声をテキストに変換
            $transcribedText = $this->convertAudioToText($audioFilePath);

            // 変換されたテキストをGeminiで処理
            return $this->processTranscribedText($transcribedText, $options);

        } catch (Exception $e) {
            throw new Exception('音声認識中にエラーが発生しました: ' . $e->getMessage());
        }
    }

    /**
     * 音声ファイルをテキストに変換する
     * 注意: このメソッドは実装が必要です。Whisper APIなどの音声認識サービスを使用してください。
     *
     * @param string $audioFilePath 音声ファイルのパス
     * @return string 変換されたテキスト
     * @throws Exception
     */
    private function convertAudioToText(string $audioFilePath): string
    {
        try {
            // 音声ファイルをBase64エンコード
            $audioData = base64_encode(file_get_contents($audioFilePath));

            // Gemini APIを呼び出して音声認識を実行
            $response = $this->client->post('https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-pro-latest:generateContent', [
                'headers' => [
                    'Content-Type' => 'application/json',
                    'x-goog-api-key' => $this->apiKey
                ],
                'json' => [
                    'contents' => [
                        [
                            'parts' => [
                                [
                                    'inline_data' => [
                                        'mime_type' => 'audio/wav',
                                        'data' => $audioData
                                    ]
                                ],
                                ['text' => 'この音声を文字起こししてください。']
                            ]
                        ]
                    ],
                    'generationConfig' => [
                        'temperature' => 0.1,  // より正確な文字起こしのために低めに設定
                        'topK' => 1,
                        'topP' => 1
                    ]
                ]
            ]);

            $result = json_decode($response->getBody(), true);
            return $result['candidates'][0]['content']['parts'][0]['text'] ?? '音声の文字起こしに失敗しました。';

        } catch (Exception $e) {
            throw new Exception('音声認識中にエラーが発生しました: ' . $e->getMessage());
        }
    }

    /**
     * 文字起こしされたテキストをGeminiで処理する
     *
     * @param string $text 文字起こしされたテキスト
     * @param array $options オプション
     * @return string 処理されたテキスト
     * @throws Exception
     */
    private function processTranscribedText(string $text, array $options = []): string
    {
        try {
            $defaultOptions = [
                'temperature' => 0.7,
                'maxOutputTokens' => 1024,
                'topP' => 0.8,
                'topK' => 40
            ];

            $options = array_merge($defaultOptions, $options);

            $model = new GenerativeModel($this->client, $this->model);
            
            $prompt = "以下の文字起こしテキストを整形し、句読点を適切に追加してください：\n\n" . $text;
            
            $response = $model->generateContent([
                'contents' => [
                    ['parts' => [['text' => $prompt]]]
                ],
                'generationConfig' => $options
            ]);

            return $response->getText();

        } catch (Exception $e) {
            throw new Exception('テキスト処理中にエラーが発生しました: ' . $e->getMessage());
        }
    }

    /**
     * 利用可能なモデルの一覧を取得
     *
     * @return array モデル名と説明の配列
     */
    public static function getAvailableModels(): array
    {
        return self::AVAILABLE_MODELS;
    }

}
