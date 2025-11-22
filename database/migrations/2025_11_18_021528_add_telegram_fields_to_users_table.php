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
        Schema::table('users', function (Blueprint $table) {
            $table->string('telegram_chat_id')->nullable()->unique()->after('email');
            $table->string('telegram_username')->nullable()->after('telegram_chat_id');
            $table->boolean('notifications_enabled')->default(false)->after('telegram_username');
            $table->string('verification_code')->nullable()->unique()->after('notifications_enabled');
            $table->index('telegram_chat_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['telegram_chat_id']);
            $table->dropColumn(['telegram_chat_id', 'telegram_username', 'notifications_enabled', 'verification_code']);
        });
    }
};
