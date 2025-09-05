<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use Illuminate\Support\Facades\Log;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');


Schedule::command('slack:fetch-messages')
    ->everyFiveMinutes()
    ->before(function () {
        Log::info('▶️ slack:fetch-messages:run 開始: ' . now());
    })
    ->after(function () {
        Log::info('✅ slack:fetch-messages:run 完了: ' . now());
    });

// 午前9時と午後9時に実行ごとにSlackメッセージを分析
Schedule::command('slack:analyze-messages')
    ->twiceDaily(9, 21) // 午前9時と午後9時に実行
    ->withoutOverlapping()
    ->before(function () {
        Log::info('▶️ slack:analyze-messages:run 開始: ' . now());
    })
    ->after(function () {
        Log::info('✅ slack:analyze-messages:run 完了: ' . now());
    })
    ->appendOutputTo(storage_path('logs/slack-analysis.log'));

// 30分ごとに直近1時間以内に開始する予定があれば、その内容を Slack 通知（DM）に送信する定期タスク
Schedule::command('slack:notify-events')
    ->everyThirtyMinutes()
    ->before(function () {
        Log::info('▶️ 予定通知タスク開始', [
            'started_at' => now()->toDateTimeString(),
            'next_run' => now()->addMinutes(30)->toDateTimeString(),
            'target_range' => [
                'from' => now()->toDateTimeString(),
                'to' => now()->addHour()->toDateTimeString()
            ]
        ]);
    })
    ->after(function () {
        Log::info('✅ 予定通知タスク完了', [
            'completed_at' => now()->toDateTimeString(),
            'next_run' => now()->addMinutes(30)->toDateTimeString()
        ]);
    });

