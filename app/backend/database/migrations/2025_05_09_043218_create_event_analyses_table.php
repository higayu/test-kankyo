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
        Schema::create('event_analyses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('slack_message_id')->constrained('slack_messages')->onDelete('cascade');
            $table->foreignId('scheduled_event_id')->nullable()->constrained('scheduled_events')->onDelete('cascade');
            $table->string('analysis_type', 50);
            $table->json('extracted_data');
            $table->float('confidence_score');
            $table->string('analysis_status', 20);
            $table->timestamps();

            // インデックスの追加
            $table->index('analysis_type');
            $table->index('analysis_status');
            $table->index('confidence_score');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('event_analyses');
    }
};
