<?php

return [
    'defaults' => [
        'guard' => 'api', // JWT をデフォルトの認証ガードに設定
        //'passwords' => 'm_staff', // m_staff に変更した部分（コメントアウト）
        'passwords' => 'users', // 元々の設定（users）に戻す
    ],

    'guards' => [
        'web' => [
            'driver' => 'session',
            //'provider' => 'm_staff', // m_staff に変更した部分（コメントアウト）
            'provider' => 'users', // 元々の設定に戻す
        ],

        // JWT 認証用のガード
        'api' => [
            'driver' => 'jwt',
            //'provider' => 'm_staff', // m_staff に変更した部分（コメントアウト）
            'provider' => 'users', // 元々の設定に戻す
        ],

        // Sanctum 認証用のガード
        'sanctum' => [
            'driver' => 'session',
            //'provider' => 'm_staff', // m_staff に変更した部分（コメントアウト）
            'provider' => 'users', // 元々の設定に戻す
        ],

        'filament' => [
            'driver' => 'session',
            'provider' => 'users',
        ],
    ],

    'providers' => [
        //'m_staff' => [ // m_staff に変更した部分（コメントアウト）
        'users' => [ // 元々の設定に戻す
            'driver' => 'eloquent',
            //'model' => App\Models\MStaff::class, // MStaff モデルに変更した部分（コメントアウト）
            //'model' => env('AUTH_MODEL', App\Models\MStaff::class),
            'model' => App\Models\User::class, // 元々の User モデルを使用
        ],
    ],

    'passwords' => [
        //'m_staff' => [ // m_staff に変更した部分（コメントアウト）
        'users' => [ // 元々の設定に戻す
            //'provider' => 'm_staff',
            'provider' => 'users', // 元々の設定
            // Laravel の初期設定では 'password_resets' テーブルを使用する場合が多いですが、
            // 環境に合わせて適宜修正してください
            //'table' => env('AUTH_PASSWORD_RESET_TOKEN_TABLE', 'password_reset_tokens'),
            'table' => 'password_resets', // 元々の設定に戻す
            'expire' => 60,
            'throttle' => 60,
        ],
    ],

    'password_timeout' => env('AUTH_PASSWORD_TIMEOUT', 10800),
];
