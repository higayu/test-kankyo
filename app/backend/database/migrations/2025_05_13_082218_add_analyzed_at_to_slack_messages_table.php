<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('slack_messages', function (Blueprint $table) {
            // 分析済みフラグとしてanalyzed_atカラムを追加（NULLの場合は未分析）
            $table->timestamp('analyzed_at')->nullable();
            // インデックスを追加して検索を高速化
            $table->index('analyzed_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('slack_messages', function (Blueprint $table) {
            $table->dropIndex(['analyzed_at']);
            $table->dropColumn('analyzed_at');
        });
    }
};
