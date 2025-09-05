<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\File;

// APIサーバーであることを示すルートルート
Route::get('/', function () {
    return response()->json([
        'message' => 'Welcome to ShotakiCare API',
        'status' => 'running'
    ]);
});

// SPA用のルート（index.htmlが存在する場合）
Route::get('{any}', function () {
    $indexPath = public_path('index.html');
    if (File::exists($indexPath)) {
        return File::get($indexPath);
    }
    return response()->json([
        'message' => 'Welcome to ShotakiCare API',
        'status' => 'running'
    ]);
})->where('any', '^(?!api).*$');

// Route::get('/', function () {
//     return view('welcome');
// });

