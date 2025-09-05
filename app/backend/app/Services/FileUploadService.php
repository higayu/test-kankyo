<?php

namespace App\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use RuntimeException;
use Illuminate\Validation\ValidationException;

class FileUploadService
{
    /**
     * マルチパートフォームデータからファイルを直接保存する
     *
     * @param Request $request
     * @param string $directory 保存先ディレクトリ
     * @param array $allowedExtensions 許可する拡張子
     * @param int $maxSize 最大ファイルサイズ（バイト）
     * @return array 保存されたファイルの情報
     * @throws RuntimeException|ValidationException
     */
    public function saveUploadedFile(
        Request $request,
        string $directory = 'uploads',
        array $allowedExtensions = ['wav', 'mp3', 'mp4', 'mpeg', 'mpga', 'm4a', 'webm'],
        int $maxSize = 25 * 1024 * 1024
    ) {
        Log::info('=== ファイルアップロード処理開始 ===', [
            'timestamp' => now()->toDateTimeString(),
            'memory_usage' => memory_get_usage(true),
            'php_version' => PHP_VERSION,
            'os' => PHP_OS,
            'php_settings' => [
                'upload_max_filesize' => ini_get('upload_max_filesize'),
                'post_max_size' => ini_get('post_max_size'),
                'memory_limit' => ini_get('memory_limit'),
                'upload_tmp_dir' => ini_get('upload_tmp_dir')
            ]
        ]);

        try {
            // 1. リクエストの検証
            Log::info('1. リクエスト検証開始');
            
            // リクエストの詳細をログに記録
            Log::info('リクエスト情報', [
                'has_file' => $request->hasFile('audio'),
                'all_files' => array_map(function($file) {
                    if (!$file) return null;
                    try {
                        return [
                            'name' => $file->getClientOriginalName(),
                            'size' => $file->getSize(),
                            'mime_type' => $file->getMimeType(),
                            'error' => $file->getError(),
                            'error_message' => $file->getErrorMessage(),
                            'is_valid' => $file->isValid(),
                            'path' => $file->getPathname(),
                            'real_path' => $file->getRealPath(),
                            'tmp_name' => $file->getPathname()
                        ];
                    } catch (\Exception $e) {
                        return [
                            'error' => $e->getMessage(),
                            'trace' => $e->getTraceAsString()
                        ];
                    }
                }, $request->allFiles()),
                'content_type' => $request->header('Content-Type'),
                'content_length' => $request->header('Content-Length'),
                'request_method' => $request->method(),
                'request_url' => $request->fullUrl()
            ]);

            // Content-Typeの確認
            $contentType = $request->header('Content-Type');
            if (!$contentType || strpos($contentType, 'multipart/form-data') === false) {
                throw new RuntimeException('ファイルのアップロード形式が正しくありません。multipart/form-data形式で送信してください。');
            }

            // ファイルの取得と検証
            if (!$request->hasFile('audio')) {
                throw new RuntimeException('音声ファイルがアップロードされていません。ファイルを選択してください。');
            }

            $file = $request->file('audio');
            if (!$file) {
                throw new RuntimeException('音声ファイルの取得に失敗しました。もう一度お試しください。');
            }

            if (!$file->isValid()) {
                $errorMessage = match($file->getError()) {
                    UPLOAD_ERR_INI_SIZE => 'アップロードされたファイルが大きすぎます。',
                    UPLOAD_ERR_FORM_SIZE => 'アップロードされたファイルが大きすぎます。',
                    UPLOAD_ERR_PARTIAL => 'ファイルが完全にアップロードされませんでした。',
                    UPLOAD_ERR_NO_FILE => 'ファイルがアップロードされていません。',
                    UPLOAD_ERR_NO_TMP_DIR => '一時フォルダが見つかりません。',
                    UPLOAD_ERR_CANT_WRITE => 'ファイルの書き込みに失敗しました。',
                    UPLOAD_ERR_EXTENSION => 'ファイルのアップロードが拡張機能によって停止されました。',
                    default => 'ファイルのアップロードに失敗しました。'
                };
                throw new RuntimeException($errorMessage);
            }

            // ファイルの詳細情報をログに記録
            Log::info('ファイル情報', [
                'name' => $file->getClientOriginalName(),
                'mime_type' => $file->getMimeType(),
                'size' => $file->getSize(),
                'error' => $file->getError(),
                'error_message' => $file->getErrorMessage(),
                'extension' => $file->getClientOriginalExtension(),
                'is_valid' => $file->isValid(),
                'path' => $file->getPathname(),
                'real_path' => $file->getRealPath(),
                'tmp_name' => $file->getPathname()
            ]);

            // バリデーションルールの設定
            $rules = [
                'audio' => [
                    'required',
                    'file',
                    'mimes:' . implode(',', $allowedExtensions),
                    'max:' . ($maxSize / 1024) // KB単位に変換
                ]
            ];

            // カスタムエラーメッセージの設定
            $messages = [
                'audio.required' => '音声ファイルがアップロードされていません。ファイルを選択してください。',
                'audio.file' => '無効なファイル形式です。音声ファイルを選択してください。',
                'audio.mimes' => '対応していないファイル形式です。' . implode(', ', $allowedExtensions) . '形式のファイルを選択してください。',
                'audio.max' => 'ファイルサイズが大きすぎます（最大' . ($maxSize / 1024 / 1024) . 'MB）'
            ];

            // バリデーション実行
            $validator = Validator::make(['audio' => $file], $rules, $messages);
            
            if ($validator->fails()) {
                Log::error('バリデーションエラー', [
                    'errors' => $validator->errors()->toArray(),
                    'file_info' => [
                        'name' => $file->getClientOriginalName(),
                        'mime_type' => $file->getMimeType(),
                        'size' => $file->getSize(),
                        'error' => $file->getError(),
                        'error_message' => $file->getErrorMessage(),
                        'extension' => $file->getClientOriginalExtension(),
                        'is_valid' => $file->isValid()
                    ]
                ]);
                throw new ValidationException($validator);
            }

            // 2. ストレージディレクトリの準備
            Log::info('2. ストレージディレクトリ準備開始');
            
            $baseStoragePath = storage_path('app');
            $uploadDir = $baseStoragePath . '/' . $directory;

            // ストレージディレクトリの存在確認と作成
            if (!file_exists($uploadDir)) {
                if (!@mkdir($uploadDir, 0777, true)) {
                    $error = error_get_last();
                    Log::error('アップロードディレクトリの作成に失敗しました', [
                        'path' => $uploadDir,
                        'error' => $error,
                        'php_user' => get_current_user(),
                        'current_dir' => getcwd()
                    ]);
                    throw new RuntimeException('アップロードディレクトリの作成に失敗しました。システム管理者に連絡してください。');
                }
                if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
                    @chmod($uploadDir, 0777);
                }
            }

            // 3. ファイルの保存
            Log::info('3. ファイル保存開始');
            
            $originalName = $file->getClientOriginalName();
            $extension = $file->getClientOriginalExtension();
            $safeFileName = Str::slug(pathinfo($originalName, PATHINFO_FILENAME)) . '_' . time() . '.' . $extension;
            $relativePath = $directory . '/' . $safeFileName;
            $fullPath = $baseStoragePath . '/' . $relativePath;

            try {
                // ファイルの移動
                if (!$file->move($uploadDir, $safeFileName)) {
                    throw new RuntimeException('ファイルの保存に失敗しました。');
                }

                // ファイルの存在確認
                if (!file_exists($fullPath)) {
                    throw new RuntimeException('ファイルの保存に失敗しました。保存先のファイルが見つかりません。');
                }

                // ファイルの読み取り権限確認
                if (!is_readable($fullPath)) {
                    throw new RuntimeException('ファイルの保存に失敗しました。保存したファイルにアクセスできません。');
                }

                Log::info('ファイル保存成功', [
                    'original_name' => $originalName,
                    'saved_name' => $safeFileName,
                    'path' => $relativePath,
                    'full_path' => $fullPath,
                    'size' => filesize($fullPath),
                    'mime_type' => mime_content_type($fullPath)
                ]);

                return [
                    'path' => $relativePath,
                    'full_path' => $fullPath,
                    'original_name' => $originalName,
                    'saved_name' => $safeFileName,
                    'size' => filesize($fullPath),
                    'mime_type' => mime_content_type($fullPath)
                ];

            } catch (\Exception $e) {
                // 保存に失敗した場合、一時ファイルを削除
                if (file_exists($fullPath)) {
                    @unlink($fullPath);
                }
                throw new RuntimeException('ファイルの保存に失敗しました: ' . $e->getMessage());
            }

        } catch (\Exception $e) {
            Log::error('=== ファイルアップロードエラー ===', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'request_info' => [
                    'has_file' => $request->hasFile('audio'),
                    'content_type' => $request->header('Content-Type'),
                    'content_length' => $request->header('Content-Length'),
                    'request_method' => $request->method()
                ]
            ]);
            throw $e;
        }
    }
} 