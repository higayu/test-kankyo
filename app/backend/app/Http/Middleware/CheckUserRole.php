<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class CheckUserRole
{
    // 権限チェックを除外するルート
    private $excludedRoutes = [
        'filament.admin.auth.login',
        'filament.admin.auth.logout',
        'filament.admin.home',
        'filament.admin.pages.dashboard',
        'filament.admin.pages.filament-playground',
    ];

    // 権限チェックを除外するパス
    private $excludedPaths = [
        'admin',
        'admin/logout',
        'admin/login',
        'admin/auth/login',
        'admin/auth/logout',
    ];

    public function handle(Request $request, Closure $next)
    {
        // デバッグ：ミドルウェアが実行されたことを確認
        Log::info('CheckUserRole middleware executed');

        // 現在のパスを取得
        $path = $request->path();
        Log::info('Current path', ['path' => $path]);

        // 除外パスの場合は権限チェックをスキップ
        if (in_array($path, $this->excludedPaths)) {
            Log::info('Skipping permission check for excluded path', ['path' => $path]);
            return $next($request);
        }

        $user = Auth::user();
        
        if (!$user) {
            Log::info('No authenticated user found, redirecting to login');
            return redirect()->route('filament.admin.auth.login');
        }

        // 現在のルート名を取得
        $routeName = $request->route() ? $request->route()->getName() : null;

        // 除外ルートの場合は権限チェックをスキップ
        if ($routeName && in_array($routeName, $this->excludedRoutes)) {
            Log::info('Skipping permission check for excluded route', ['route' => $routeName]);
            return $next($request);
        }

        // パスからリソース名を抽出
        $resourceName = null;
        if (preg_match('/admin\/([^\/]+)(?:\/.*)?$/', $path, $matches)) {
            $resourceName = $matches[1];
        }

        // デバッグログ
        Log::info('CheckUserRole - Debug Info', [
            'user_id' => $user->id,
            'is_admin' => $user->is_admin,
            'path' => $path,
            'resource_name' => $resourceName,
            'route_name' => $routeName,
            'request_method' => $request->method()
        ]);

        // リソース名が取得できた場合のみ権限チェックを実行
        if ($resourceName) {
            Log::info('Checking resource access', [
                'resource_name' => $resourceName,
                'user_permissions' => [
                    'is_system_admin' => $user->is_admin === User::ROLE_SYSTEM_ADMIN,
                    'is_manager' => $user->is_admin === User::ROLE_MANAGER,
                    'is_staff' => $user->is_admin === User::ROLE_STAFF
                ]
            ]);

            if (!$user->canAccessResource($resourceName)) {
                Log::warning('Access denied', [
                    'user_id' => $user->id,
                    'is_admin' => $user->is_admin,
                    'resource_name' => $resourceName,
                    'path' => $path
                ]);
                
                // 権限のないリソースにアクセスしようとした場合、ケアプランページにリダイレクト
                return redirect()->route('filament.admin.resources.care-plans.index');
            }
        } else {
            Log::info('No resource name found in path, skipping permission check');
        }

        return $next($request);
    }
} 