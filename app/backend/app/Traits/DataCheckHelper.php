<?php

namespace App\Traits;

use Illuminate\Support\Facades\DB;

trait DataCheckHelper
{
    public function checkDataExists($id, $table)
    {
        $exists = DB::connection('fcc123_shoutaki')
            ->table($table)
            ->where('service_record_id', $id)
            ->exists();

        if (!$exists) {
            return response()->json([
                'status' => 'success',
                'message' => 'データが存在しません',
                'data' => []
            ], 200, [], JSON_UNESCAPED_UNICODE);
        }
    }

    public function checkServiceDataExists($id, $table)
    {
        $exists = DB::connection('fcc123_shoutaki')
            ->table($table)
            ->where('id', $id)
            ->exists();

        if (!$exists) {
            return response()->json([
                'status' => 'success',
                'message' => 'データが存在しません',
                'data' => []
            ], 200, [], JSON_UNESCAPED_UNICODE);
        }
    }

    public function checkScheduleDataExists($id,  $shcedule_time, $table)
    {
        $exists = DB::connection('fcc123_shoutaki')
            ->table($table)
            ->where('user_id', $id)
            ->where('scheduled_time', $shcedule_time)
            ->exists();

        if (!$exists) {
            return response()->json([
                'status' => 'success',
                'message' => 'データが存在しません',
                'data' => []
            ], 200, [], JSON_UNESCAPED_UNICODE);
        }
    }

    public function checkItemExists($id, $table)
    {
        $exists = DB::connection('fcc123_shoutaki')
            ->table($table)
            ->where('id', $id)
            ->exists();

        if (!$exists) {
            return response()->json([
                'status' => 'success',
                'message' => '指定されたアイテムID（' . $id . '）は存在しません',
                'data' => []
            ], 200, [], JSON_UNESCAPED_UNICODE);
        }
    }
}
