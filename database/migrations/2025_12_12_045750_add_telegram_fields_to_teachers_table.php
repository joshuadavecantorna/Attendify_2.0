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
        Schema::table('teachers', function (Blueprint $table) {
            $table->string('telegram_chat_id')->nullable()->after('is_active');
            $table->string('telegram_username')->nullable()->after('telegram_chat_id');
            $table->string('preferred_language')->default('en')->after('telegram_username');
            $table->boolean('notification_enabled')->default(false)->after('preferred_language');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('teachers', function (Blueprint $table) {
            $table->dropColumn([
                'telegram_chat_id',
                'telegram_username',
                'preferred_language',
                'notification_enabled'
            ]);
        });
    }
};
