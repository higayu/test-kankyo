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
            $table->boolean('is_analyzed')->default(false)->after('analyzed_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('slack_messages', function (Blueprint $table) {
            $table->dropColumn('is_analyzed');
        });
    }
}; 