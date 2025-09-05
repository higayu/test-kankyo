<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Tymon\JWTAuth\Facades\JWTAuth;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class AuthController extends Controller
{
    // ✅ ユーザー登録
    public function register(Request $request)
    {
        try {
            $request->validate([
                'name' => 'required|string|max:255',
                'login_code' => 'required|string|alpha_num|unique:users',
                'email' => 'required|string|email|max:255|unique:users',
                'password' => 'required|string|min:6|confirmed',
            ]);

            $user = User::create([
                'name' => $request->name,
                'email' => $request->email,
                'login_code' => $request->login_code,
                'password' => Hash::make($request->password),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'ユーザー登録が完了しました'
            ], 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => '入力データが無効です',
                'errors' => $e->errors()
            ], 400);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'ユーザー登録に失敗しました',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // ✅ ユーザーログイン
    public function login(Request $request)
    {
        try {
            // バリデーション
            $validator = Validator::make($request->all(), [
                'login_code' => 'required|string',
                'password' => 'required|string',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'バリデーションエラー',
                    'error_type' => 'validation',
                    'errors' => $validator->errors()
                ], 422);
            }

            $credentials = $request->only('login_code', 'password');

            if (!$token = Auth::attempt($credentials)) {
                return response()->json([
                    'success' => false,
                    'message' => 'ログインに失敗しました',
                    'error_type' => 'authentication',
                    'error' => 'ログイン認証エラー'
                ], 401);
            }

            return response()->json([
                'success' => true,
                'message' => 'ログインに成功しました',
                'token' => $token,
                'user' => Auth::user()
            ]);

        } catch (\PDOException $e) {
            Log::error('データベース接続エラー: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'データベース接続エラー',
                'error_type' => 'database',
                'error' => '現在サービスが利用できません'
            ], 503);
        } catch (\Exception $e) {
            Log::error('ログインエラー: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'サーバーエラー',
                'error_type' => 'server',
                'error' => '予期せぬエラーが発生しました'
            ], 500);
        }
    }

    // NFCログイン
    public function nfcLogin(Request $request)
    {
        try {
            $nfchasu = $request->input('nfchasu');
            
            if (empty($nfchasu)) {
                return response()->json([
                    'success' => false,
                    'message' => 'NFCハッシュ値が提供されていません'
                ], 400);
            }

            // 受け取ったハッシュ値をSHA256で変換
            $hashedNfcHash = hash('sha256', $nfchasu);
            
            // 変換したハッシュ値でユーザーを検索
            $user = User::where('nfchasu', $hashedNfcHash)->first();

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => '無効なNFCハッシュ値です',
                    'nfchasu' => $nfchasu,
                    'hashedNfcHash' => $hashedNfcHash
                ], 401);
            }

            // JWTトークンの生成
            $token = JWTAuth::fromUser($user);

            return response()->json([
                'success' => true,
                'message' => 'NFCログインに成功しました',
                'token' => $token,
                'user' => $user
            ]);

        } catch (\PDOException $e) {
            Log::error('データベース接続エラー: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'データベース接続エラー',
                'error_type' => 'database',
                'error' => '現在サービスが利用できません'
            ], 503);
        } catch (\Exception $e) {
            Log::error('NFCログインエラー: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'ログイン処理中にエラーが発生しました',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    //-------------------------------------------------------------------//
        // // ✅ ユーザー登録（MStaffを使用）
        // public function register(Request $request)
        // {
        //     $request->validate([
        //         'name' => 'required|string|max:255',
        //         // ユニークチェックも 'users' ではなく 'm_staff' テーブルに変更
        //         'email' => 'required|string|email|max:255|unique:m_staff',
        //         'password' => 'required|string|min:6|confirmed',
        //     ]);
    
        //     // User::create(...) を MStaff::create(...) に変更
        //     $mStaff = MStaff::create([
        //         'name' => $request->name,
        //         'email' => $request->email,
        //         'password' => Hash::make($request->password),
        //     ]);
    
        //     return response()->json(['message' => 'MStaff created successfully'], 201);
        // }
    
        // // ✅ ユーザーログイン（MStaffを使用）
        // public function login(Request $request)
        // {
        //     $credentials = $request->only('email', 'password');
    
        //     // ここでは、Auth::attempt() が config/auth.php の設定に従い MStaff モデルからユーザーを取得します。
        //     if (!$token = Auth::attempt($credentials)) {
        //         return response()->json(['error' => 'Unauthorized'], 401);
        //     }
    
        //     return response()->json([
        //         'message' => 'Login successful',
        //         'token' => $token,
        //         'user' => Auth::user()  // ここで取得されるのは MStaff モデルのインスタンスになります。
        //     ]);
        // }
//----------------------------------------------------------------------------//


    // ✅ ログアウト
    public function logout()
    {
        Auth::logout();
        return response()->json(['message' => 'Successfully logged out']);
    }

    
    // ✅ ユーザー情報取得
    public function profile()
    {
        return response()->json(Auth::user());
    }

}
