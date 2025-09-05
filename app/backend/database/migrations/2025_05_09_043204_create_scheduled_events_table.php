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
        Schema::create('scheduled_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('slack_message_id')->constrained('slack_messages')->onDelete('cascade');
            $table->string('event_type', 50);
            $table->string('title', 255);
            $table->text('description')->nullable();
            $table->dateTime('start_datetime');
            $table->dateTime('end_datetime')->nullable();
            $table->string('location', 255)->nullable();
            $table->json('participants')->nullable();
            $table->string('status', 20)->default('pending');
            $table->string('priority', 20)->nullable();
            $table->timestamps();

            // インデックスの追加
            $table->index('event_type');
            $table->index('status');
            $table->index('start_datetime');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('scheduled_events');
    }
};
