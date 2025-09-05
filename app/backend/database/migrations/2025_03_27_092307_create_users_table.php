<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 外部キー制約チェックを一時的に無効化
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        
        Schema::create('users', function (Blueprint $table) {
            $table->increments('id'); // 修正: 符号なしinteger型でオートインクリメント
            $table->string('name');
            $table->string('login_code')->unique();
            $table->string('password');
            $table->boolean('is_admin')->default(false);
            $table->date('entry_date')->nullable();
            $table->date('exit_date')->nullable();
            $table->text('note')->nullable();
            $table->timestamps();
            $table->string('nfchasu', 64)->nullable()->unique();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('users');
    }
}; 