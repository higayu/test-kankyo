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
        Schema::table('event_analyses', function (Blueprint $table) {
            $table->timestamp('event_start_datetime')->nullable()->after('extracted_data');
            $table->timestamp('event_end_datetime')->nullable()->after('event_start_datetime');
            $table->string('event_title')->nullable()->after('event_end_datetime');
            $table->string('event_type')->nullable()->after('event_title');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('event_analyses', function (Blueprint $table) {
            $table->dropColumn([
                'event_start_datetime',
                'event_end_datetime',
                'event_title',
                'event_type'
            ]);
        });
    }
}; 