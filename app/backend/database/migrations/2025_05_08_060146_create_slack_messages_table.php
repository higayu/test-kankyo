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
        Schema::create('slack_messages', function (Blueprint $table) {
            $table->id();
            $table->string('channel_id');
            $table->string('user')->nullable();
            $table->text('text')->nullable()->charset('utf8mb4')->collation('utf8mb4_unicode_ci');
            $table->timestamp('timestamp')->nullable();
            $table->string('slack_ts')->unique()->nullable(); // Slack メッセージ ID はユニーク
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('slack_messages');
    }
};