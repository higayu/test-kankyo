<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ApiController;
use App\Methods\UserList;
use App\Http\Controllers\SlackController;
use App\Http\Controllers\AudioController;
use App\Http\Controllers\TextController;

// ✅ 認証用ルート
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

// ✅ 認証が必要なルート
Route::middleware('auth:api')->group(function () { // JWT 用


});

// Slack関連のエンドポイント
Route::post('/slack/events', [SlackController::class, 'handleEvent']);
Route::post('/slack/commands', [SlackController::class, 'handleCommand']);
Route::post('/slack/interactions', [SlackController::class, 'handleInteraction']);

// 音声処理エンドポイント（認証不要）
Route::prefix('audio')->group(function () {
    Route::post('/denoise', [AudioController::class, 'denoise']);
    Route::post('/test-save', [AudioController::class, 'testSave']);
    Route::post('/test-whisper-api', [AudioController::class, 'testWhisperApi']);
    Route::post('/test-gemini-speech', [AudioController::class, 'testGeminiSpeechApi']);
});

// テキスト処理エンドポイント（認証不要）
Route::prefix('text')->group(function () {
    Route::post('/test-gemini', [TextController::class, 'testGeminiText']);
    Route::post('/test-openai', [TextController::class, 'testOpenAIsummarize']);
});
