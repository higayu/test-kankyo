<?php

return [
    'paths' => ['api/*', 'sanctum/csrf-cookie', 'login'], // 'login' を追加
    'allowed_methods' => ['*'], // すべてのメソッドを許可
    'allowed_origins' => [
        'http://localhost:5173', // 追加

    ],
    'allowed_origins_patterns' => [],
    'allowed_headers' => ['*'], // すべてのヘッダーを許可
    'exposed_headers' => ['Authorization'], // クライアントが `Authorization` を読めるようにする
    'max_age' => 0,
    'supports_credentials' => true, // 認証情報 (Cookie, Authorization ヘッダー) を許可
];



