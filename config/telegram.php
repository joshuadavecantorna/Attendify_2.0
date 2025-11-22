<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Telegram Bot Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for Telegram Bot API integration for sending class
    | reminders and notifications to students and teachers.
    |
    */

    'bot_token' => env('TELEGRAM_BOT_TOKEN'),

    'webhook_secret' => env('TELEGRAM_WEBHOOK_SECRET'),

    'api_url' => env('TELEGRAM_API_URL', 'https://api.telegram.org'),

    /*
    |--------------------------------------------------------------------------
    | Notification Settings
    |--------------------------------------------------------------------------
    */

    'reminder_minutes' => env('TELEGRAM_REMINDER_MINUTES', 30),

    'rate_limit' => [
        'messages_per_second' => 30,
        'messages_per_minute' => 20,
    ],

    /*
    |--------------------------------------------------------------------------
    | Message Templates
    |--------------------------------------------------------------------------
    */

    'templates' => [
        'student_reminder' => "🔔 *Class Reminder*\n\nHi {name}!\n\n📚 {class_name}\n⏰ Starts at {start_time} (in {minutes} minutes)\n📍 {room}\n👨‍🏫 Teacher: {teacher_name}\n\nSee you there! Don't forget your materials.",
        
        'teacher_reminder' => "🔔 *Class Reminder*\n\nHi {name}!\n\n📚 {class_name}\n⏰ Starts at {start_time} (in {minutes} minutes)\n📍 {room}\n👥 {enrolled_count} students enrolled\n\nPrepare your materials and see you in class!",
        
        'welcome' => "👋 Welcome to *Attendify Reminder Bot*!\n\nPlease send your verification code to link your account.",
        
        'verification_success' => "✅ *Account Connected!*\n\nYou will now receive class reminders 30 minutes before each class.\n\nYou can also ask me questions like:\n• What is my next class?\n• Show my schedule\n• What classes do I have today?\n• When is my Computer Science class?\n\nYou can manage your notification settings in the Attendify app.",
        
        'verification_failed' => "❌ *Invalid verification code*\n\nPlease check your code in the Attendify app and try again.",
        
        'account_already_linked' => "ℹ️ Your Telegram account is already linked to Attendify.\n\nYou can ask me questions like:\n• What is my next class?\n• Show my schedule\n• What classes do I have today?",
        
        'notifications_disabled' => "🔕 You have disabled notifications in Attendify.\n\nEnable them in your settings to receive reminders.",
    ],

];
