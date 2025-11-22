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
        Schema::create('notification_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('telegram_chat_id');
            $table->unsignedBigInteger('class_id');
            $table->string('class_name');
            $table->string('room')->nullable();
            $table->string('teacher_name')->nullable();
            $table->string('notification_type'); // 'student_reminder' or 'teacher_reminder'
            $table->integer('minutes')->nullable(); // reminder minutes (30, 15, etc)
            $table->timestamp('sent_at')->nullable();
            $table->boolean('delivered')->default(false);
            $table->text('error_message')->nullable();
            $table->timestamps();

            // Indexes
            $table->index('user_id');
            $table->index('telegram_chat_id');
            $table->index('class_id');
            $table->index('notification_type');
            $table->index('sent_at');
            
            // Foreign keys (optional - uncomment if you want referential integrity)
            // $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            // $table->foreign('class_id')->references('id')->on('classes')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('notification_logs');
    }
};
