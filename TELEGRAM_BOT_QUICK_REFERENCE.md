# 🚀 Telegram Bot - Quick Reference

## 📋 Summary
Complete Telegram bot implementation for Attendify with:
- ✅ User registration via Student ID or Teacher ID
- ✅ Duplicate prevention (one ID = one Telegram account)
- ✅ Daily 6 AM notifications
- ✅ On-demand schedule with /today
- ✅ Notification management (/stop, /resume)
- ✅ Help system

---

## 🔌 API Endpoints Created

### 1. Check User
```
GET /api/n8n/check-user?telegram_chat_id={chat_id}
```
Returns: User exists status

### 2. Register
```
POST /api/n8n/register
Body: {
  "id": "STU-2025-001",
  "telegram_chat_id": "5226305211",
  "telegram_username": "username"
}
```
Returns: Success or error with duplicate detection

### 3. Get Today's Schedule
```
GET /api/n8n/today-schedule?telegram_chat_id={chat_id}
```
Returns: Today's classes for the user

### 4. Disable Notifications
```
POST /api/n8n/notifications/disable
Body: { "telegram_chat_id": "..." }
```

### 5. Enable Notifications
```
POST /api/n8n/notifications/enable
Body: { "telegram_chat_id": "..." }
```

---

## 🤖 Bot Commands

| Command | What It Does |
|---------|--------------|
| `/start` | Register or welcome back message |
| `/today` | Get schedule immediately |
| `/stop` | Disable daily notifications |
| `/resume` | Enable daily notifications |
| `/help` | Show all commands |

---

## 📱 User Flow

### First Time User:
```
User: /start
Bot: "Welcome! Please enter your ID..."
User: STU-2025-001
Bot: "✅ Registration successful! Welcome, JOSHUA DAVE G. CANTORNA"
```

### Get Schedule:
```
User: /today
Bot: "📚 Good day! You have 3 classes today:
     1. 7:00 AM - Programming I (CS02)..."
```

### Stop Notifications:
```
User: /stop
Bot: "🔕 Notifications disabled"
```

---

## 🔐 Security Features

1. **Duplicate Prevention:**
   - Same telegram_chat_id cannot register multiple IDs
   - Same ID cannot be linked to multiple telegram accounts

2. **ID Validation:**
   - Searches both students and teachers tables
   - Returns proper error if ID not found

3. **Safe Operations:**
   - All operations check user exists first
   - Graceful error handling

---

## 📂 Files Modified

1. `app/Http/Controllers/Api/N8NController.php` - Added 5 new methods
2. `routes/api.php` - Added 5 new routes
3. `database/migrations/2025_12_12_045750_add_telegram_fields_to_teachers_table.php` - New
4. `app/Models/Teacher.php` - Added telegram fields
5. `N8N_TELEGRAM_BOT_SETUP.md` - Complete setup guide
6. `TELEGRAM_BOT_IMPLEMENTATION_PLAN.md` - Implementation plan

---

## 🧪 Quick Test

### Test Registration (Local):
```powershell
curl.exe -k -X POST "https://attendify_2.0.test/api/n8n/register" `
  -H "Content-Type: application/json" `
  -d '{\"id\":\"STU-2025-001\",\"telegram_chat_id\":\"TEST123\",\"telegram_username\":\"testuser\"}'
```

### Test Get Schedule (Local):
```powershell
curl.exe -k "https://attendify_2.0.test/api/n8n/today-schedule?telegram_chat_id=5226305211"
```

### Test Registration (Production):
```bash
curl -X POST "https://attendify20-production.up.railway.app/api/n8n/register" \
  -H "Content-Type: application/json" \
  -d '{"id":"STU-2025-001","telegram_chat_id":"TEST123","telegram_username":"testuser"}'
```

---

## 🚀 Next Steps

1. **Deploy to Railway:**
   ```bash
   git add .
   git commit -m "Add Telegram bot integration with duplicate prevention"
   git push origin test
   ```

2. **Set up n8n Workflow:**
   - Follow `N8N_TELEGRAM_BOT_SETUP.md`
   - Create Telegram Trigger
   - Add HTTP Request nodes
   - Configure message templates

3. **Test End-to-End:**
   - Send /start to bot
   - Register with test ID
   - Verify database update
   - Test /today command
   - Test /stop and /resume

4. **Share Bot:**
   - Give users the bot link: `t.me/YOUR_BOT_USERNAME`
   - Share instructions

---

## 📞 Bot Info

**Bot Token:** `8422252808:AAF-ENuddVjuQy1C5VOqPtGvLtK1g8LKmSk`

**To get bot link:**
```bash
# The bot username is in your Telegram @BotFather chat
# Bot link format: t.me/YOUR_BOT_USERNAME
```

---

## 💡 Tips

1. **ID Format Examples:**
   - Student: `STU-2025-001`, `STU-2025-003`
   - Teacher: `TEACH-001`, `TEACH-002`

2. **Common Issues:**
   - "ID not found" → Check if ID exists in database
   - "Already registered" → ID linked to another account
   - No response → Check n8n workflow is active

3. **Database Check:**
   ```sql
   -- Check if registration worked
   SELECT student_id, telegram_chat_id, telegram_username, notification_enabled 
   FROM students 
   WHERE student_id = 'STU-2025-001';
   ```

---

## ✅ Completion Checklist

- [x] Database migration (telegram fields for teachers)
- [x] Teacher model updated
- [x] 5 API endpoints created
- [x] Routes added
- [x] Duplicate prevention implemented
- [x] Documentation created
- [x] Local testing passed
- [ ] Deploy to Railway
- [ ] Create n8n workflow
- [ ] Test with real bot
- [ ] Share with users

---

## 🎉 Success!

All backend code is complete and tested locally. Ready for deployment and n8n configuration!
