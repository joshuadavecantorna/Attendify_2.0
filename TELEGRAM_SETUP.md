# Telegram Bot + n8n Setup Guide

## ✅ Completed Steps

1. ✅ Database migration added (telegram fields)
2. ✅ TelegramService created
3. ✅ TelegramWebhookController created
4. ✅ N8nApiController created
5. ✅ API routes configured
6. ✅ Vue component for user settings created
7. ✅ Bot connected: @Attendify_remind_bot

## 🚀 Next Steps for Local Development

### Option 1: Using ngrok (Recommended for Testing)

1. **Install ngrok**:
   ```bash
   # Download from https://ngrok.com/download
   # Or use: choco install ngrok (if you have Chocolatey)
   ```

2. **Start ngrok tunnel**:
   ```bash
   ngrok http 80 --host-header=Attendify_2.0.test
   ```

3. **Copy the HTTPS URL** (e.g., `https://abc123.ngrok.io`)

4. **Set webhook with ngrok URL**:
   ```bash
   php artisan telegram:setup webhook
   # When prompted, manually set: https://abc123.ngrok.io/api/telegram/webhook
   ```

### Option 2: Production Deployment

Deploy to a production server with a public domain, then:
```bash
php artisan telegram:setup webhook
```

---

## 📱 Testing the Bot

### 1. Link Your Telegram Account

1. Go to Settings → Notifications in Attendify app
2. Click "Generate Verification Code"
3. Click "Open Telegram Bot" or visit: https://t.me/Attendify_remind_bot
4. Send `/start` to the bot
5. Send your verification code (e.g., `ATT-3-ABC12`)
6. Bot will confirm: "✅ Account Connected!"

### 2. Test the API Endpoint

```bash
# Test if API returns upcoming classes
curl -H "Authorization: Bearer n8n_secure_token_attendify_2025" \
  http://Attendify_2.0.test/api/n8n/upcoming-classes
```

---

## 🔧 n8n Workflow Setup

### 1. Install n8n

**Using Docker (Recommended)**:
```bash
docker run -d --name n8n -p 5678:5678 -v ~/.n8n:/home/node/.n8n n8nio/n8n
```

**Or npm**:
```bash
npm install -g n8n
n8n start
```

### 2. Access n8n

Open: http://localhost:5678

### 3. Create Workflow

1. **Add Schedule Trigger Node**
   - Trigger Interval: Every 5 minutes
   - Or Cron: `*/5 * * * *`

2. **Add HTTP Request Node** (for students)
   - Method: GET
   - URL: `http://Attendify_2.0.test/api/n8n/upcoming-classes`
   - Authentication: Header Auth
     - Name: `Authorization`
     - Value: `Bearer n8n_secure_token_attendify_2025`
   - Output: `{{ $json.students }}`

3. **Add Split In Batches Node**
   - Batch Size: 1
   - Connect to HTTP Request output

4. **Add Function Node** (format student message)
   ```javascript
   const student = $input.item.json;
   return {
     chat_id: student.telegram_chat_id,
     text: `🔔 *Class Reminder*\n\nHi ${student.name}!\n\n📚 ${student.class_name}\n⏰ Starts at ${student.start_time} (in ${student.minutes} minutes)\n📍 ${student.room}\n👨‍🏫 Teacher: ${student.teacher_name}\n\nSee you there! Don't forget your materials.`,
     parse_mode: 'Markdown'
   };
   ```

5. **Add Telegram Node**
   - Credential: Add your bot token `8422252808:AAF-ENuddVjuQy1C5VOqPtGvLtK1g8LKmSk`
   - Operation: Send Message
   - Chat ID: `{{ $json.chat_id }}`
   - Text: `{{ $json.text }}`
   - Parse Mode: `{{ $json.parse_mode }}`

6. **Repeat steps 2-5 for teachers** (use `{{ $json.teachers }}` output)

### 4. Activate Workflow

Click "Active" toggle in top right.

---

## 🧪 Testing the Complete Flow

### 1. Create Test Class

```sql
-- Add a class starting in 30 minutes from now
INSERT INTO classes (name, day_of_week, start_time, end_time, teacher_id, room)
VALUES ('Test Class', EXTRACT(ISODOW FROM NOW()), 
        (NOW() + INTERVAL '30 minutes')::TIME, 
        (NOW() + INTERVAL '90 minutes')::TIME, 
        1, 'Room 101');
```

### 2. Enroll Yourself

```sql
-- Make sure your user is linked to Telegram
UPDATE users SET 
  telegram_chat_id = 'YOUR_CHAT_ID',
  notifications_enabled = true
WHERE id = YOUR_USER_ID;
```

### 3. Wait for n8n to Trigger

Check n8n executions tab after 5 minutes to see if workflow ran.

### 4. Check Logs

```bash
# Laravel logs
tail -f storage/logs/laravel.log

# n8n logs (if using Docker)
docker logs -f n8n
```

---

## 📊 Available API Endpoints

### For n8n:

```bash
# Get upcoming classes (30-35 min window)
GET /api/n8n/upcoming-classes
Authorization: Bearer n8n_secure_token_attendify_2025

# Get all classes (debugging)
GET /api/n8n/all-classes
Authorization: Bearer n8n_secure_token_attendify_2025

# Get users with Telegram enabled
GET /api/n8n/telegram-users
Authorization: Bearer n8n_secure_token_attendify_2025

# Health check
GET /api/n8n/health
Authorization: Bearer n8n_secure_token_attendify_2025
```

### For Users:

```bash
# Generate verification code (authenticated)
POST /api/telegram/generate-code

# Unlink Telegram account
POST /api/telegram/unlink

# Toggle notifications
POST /api/telegram/toggle-notifications

# Get connection status
GET /api/telegram/status
```

---

## 🔍 Troubleshooting

### Webhook Not Receiving Messages

1. Check webhook status:
   ```bash
   php artisan telegram:setup info
   ```

2. Check Laravel logs:
   ```bash
   tail -f storage/logs/laravel.log
   ```

3. Verify ngrok is running and tunnel is active

### n8n Not Finding Classes

1. Test API manually:
   ```bash
   curl -H "Authorization: Bearer n8n_secure_token_attendify_2025" \
     http://Attendify_2.0.test/api/n8n/upcoming-classes
   ```

2. Check database:
   ```sql
   SELECT * FROM classes WHERE day_of_week = EXTRACT(ISODOW FROM NOW());
   ```

3. Verify time window (classes must start in 30-35 minutes)

### Messages Not Sending

1. Check Telegram bot token is correct
2. Verify user has `telegram_chat_id` set
3. Check `notifications_enabled = true`
4. Check n8n execution logs

---

## 📝 Todo Before Production

- [ ] Set up production webhook URL
- [ ] Configure n8n with production API URL
- [ ] Add SSL certificate for webhook
- [ ] Set up monitoring for n8n workflow
- [ ] Add error notification alerts
- [ ] Test with multiple users
- [ ] Document user onboarding process
- [ ] Add rate limiting for webhook

---

## 🎯 Summary

**What's Working:**
✅ Telegram bot created and configured
✅ Database ready with telegram fields
✅ Laravel API endpoints for webhook and n8n
✅ Vue component for user settings
✅ Bot can verify users and link accounts

**What Needs Testing:**
⏳ Webhook (requires ngrok for local dev)
⏳ n8n workflow execution
⏳ End-to-end notification flow

**Bot Link:** https://t.me/Attendify_remind_bot
