<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->string('telegram_chat_id')->nullable()->after('phone');
            $table->string('telegram_username')->nullable()->after('telegram_chat_id');
            $table->boolean('notification_enabled')->default(true)->after('telegram_username');
            $table->string('preferred_language', 10)->default('en')->after('notification_enabled');
        });
    }

    public function down(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->dropColumn(['telegram_chat_id', 'telegram_username', 'notification_enabled', 'preferred_language']);
        });
    }
};
