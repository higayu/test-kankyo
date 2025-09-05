<?php

namespace App\Models;

use Filament\Panel;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Filament\Models\Contracts\FilamentUser;
use Tymon\JWTAuth\Contracts\JWTSubject;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;

class User extends Authenticatable implements JWTSubject, FilamentUser
{
    use Notifiable;

    // 権限レベルの定数定義
    const ROLE_SYSTEM_ADMIN = 1;
    const ROLE_MANAGER = 2;
    const ROLE_STAFF = 3;

    // リソース名の定数定義
    const RESOURCE_USERS = 'users';
    const RESOURCE_SETTINGS = 'settings';
    // const RESOURCE_ADMIN = 'admin';

    // laravel_db接続を指定
    protected $connection = 'default_mysql';

    protected $fillable = [
        'name',
        'login_code',
        'password',
        'is_admin',
        'entry_date',
        'exit_date',
        'note'
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'password' => 'hashed',
        'is_admin' => 'integer',
        'entry_date' => 'date',
        'exit_date' => 'date',
    ];


    // モデルの作成時にデフォルト値を設定
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($user) {
            // is_adminが設定されていない場合、デフォルトで一般管理者権限を付与
            if (!isset($user->is_admin)) {
                $user->is_admin = 1;
            }
        });
    }

    // ✅ JWT の識別子を取得
    public function getJWTIdentifier()
    {
        return $this->getKey();
    }

    // ✅ JWT に追加するカスタムクレーム
    public function getJWTCustomClaims()
    {
        return [];
    }

    /**
     * Filament へのアクセス権限を判定するメソッド
     *
     * @return bool
     */
    public function canAccessPanel(Panel $panel): bool
    {
        return true; // すべてのユーザーがアクセス可能
    }

    /**
     * ユーザーの権限レベルに基づいてページへのアクセスを制御
     */
    public function canAccessPage(string $pageName): bool
    {
        // 権限レベルに応じたページアクセス制御
        switch ($this->is_admin) {
            case 1: // システム管理者
                return true; // すべてのページにアクセス可能
            case 2: // 管理職員
                return true;
            case 3: // その他
                return in_array($pageName, [
                    'dashboard',
                    'users',
                    'settings'
                ]);
            default:
                return false;
        }
    }

    /**
     * ユーザーの権限レベルに基づいてリソースへのアクセスを制御
     */
    public function canAccessResource(string $resourceName): bool
    {
        // リソース名の正規化
        $normalizedResource = strtolower($resourceName);
        
        // デバッグログ
        Log::info('User::canAccessResource - Debug Info', [
            'user_id' => $this->id,
            'is_admin' => $this->is_admin,
            'resource_name' => $resourceName,
            'normalized_resource' => $normalizedResource
        ]);

        // is_adminが0以外の場合は全てのリソースにアクセス可能
        if ($this->is_admin !== 0) {
            Log::info('Access granted - user is not a regular staff');
            return true;
        }

        Log::warning('Access denied - user is a regular staff');
        return false;
    }
}
