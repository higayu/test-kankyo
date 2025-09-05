<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{


    /**
     * アプリケーションのコマンドを登録
     *
     * @return void
     */
    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');  // ✅ app/Console/Commands フォルダ内の全コマンドを自動的に登録
        require base_path('routes/console.php');  // ✅ routes/console.php ファイルのコマンドを追加で読み込み
    }
}
