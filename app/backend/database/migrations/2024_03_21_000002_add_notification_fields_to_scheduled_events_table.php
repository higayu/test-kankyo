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
        Schema::table('scheduled_events', function (Blueprint $table) {
            $table->timestamp('last_notified_at')->nullable()->after('priority');
            $table->json('notification_history')->nullable()->after('last_notified_at');
            $table->boolean('is_notification_enabled')->default(true)->after('notification_history');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('scheduled_events', function (Blueprint $table) {
            $table->dropColumn([
                'last_notified_at',
                'notification_history',
                'is_notification_enabled'
            ]);
        });
    }
}; 