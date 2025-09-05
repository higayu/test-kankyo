<?php

namespace App\Traits;

use Illuminate\Support\Facades\DB;
use Exception;

trait UserCheckHelper
{
    public function checkUserExists($id)
    {
        $user = DB::connection('fcc123_shoutaki')
            ->table('m_users')
            ->where('id', $id)
            ->first();

        if (!$user) {
            return response()->json([
                'status' => 'error',
                'message' => 'ユーザーが存在しません'
            ], 404);
        }
    }
}
