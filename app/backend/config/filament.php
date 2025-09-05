<?php

return [

    'broadcasting' => [

    ],

    'default_filesystem_disk' => env('FILAMENT_FILESYSTEM_DISK', 'public'),

    'assets_path' => null,

    'cache_path' => base_path('bootstrap/cache/filament'),

    'livewire_loading_delay' => 'default',

    'auth' => [
        'guard' => 'filament',
        'middleware' => ['web'],
    ],

    // Filamentで使用するユーザーモデルの設定
    'user' => [
        // 'model' => App\Models\MStaff::class, // m_staff モデルを使用している場合の設定（コメントアウト）
        'model' => App\Models\User::class, // users モデルに戻す
    ],
];
