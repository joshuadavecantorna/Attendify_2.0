# 🤖 N8N TELEGRAM BOT WORKFLOW SETUP

## Bot Information
**Bot Token:** `8422252808:AAF-ENuddVjuQy1C5VOqPtGvLtK1g8LKmSk`

---

## 📋 Workflow Structure

### Overview
```
Telegram Message
    ↓
Extract Data (chat_id, username, message_text)
    ↓
Switch (Route by message)
    ├── /start → Check User → Register or Welcome Back
    ├── /today → Get Schedule → Send Schedule
    ├── /stop → Disable Notifications → Confirm
    ├── /resume → Enable Notifications → Confirm
    ├── /help → Send Help Message
    └── (Plain text) → Register ID → Confirm or Error
```

---

## 🔧 NODE CONFIGURATIONS

### Node 1: Telegram Trigger
**Type:** `Telegram Trigger`
**Configuration:**
- **Credentials:** Create new Telegram API credentials
  - Bot Token: `8422252808:AAF-ENuddVjuQy1C5VOqPtGvLtK1g8LKmSk`
- **Updates:** Listen for `message`
- **Additional Fields:** Leave empty

---

### Node 2: Extract Message Data
**Type:** `Code` (JavaScript)
**Mode:** Run Once for All Items

```javascript
// Extract message details
const message = $input.item.json.message;

if (!message) {
  return [];
}

const chatId = message.chat.id.toString();
const username = message.chat.username || '';
const firstName = message.chat.first_name || '';
const messageText = (message.text || '').trim();

return [{
  json: {
    chatId: chatId,
    username: username,
    firstName: firstName,
    messageText: messageText,
    originalMessage: message
  }
}];
```

---

### Node 3: Route by Command
**Type:** `Switch`
**Mode:** Rules
**Rules:**
1. **Rule 1:** `{{ $json.messageText }}` equals `/start`
2. **Rule 2:** `{{ $json.messageText }}` equals `/today`
3. **Rule 3:** `{{ $json.messageText }}` equals `/stop`
4. **Rule 4:** `{{ $json.messageText }}` equals `/resume`
5. **Rule 5:** `{{ $json.messageText }}` equals `/help`
6. **Fallback:** Everything else (ID registration)

---

## 🌿 BRANCH 1: /start Command

### Node: Check User Exists
**Type:** `HTTP Request`
**Method:** GET
**URL:** `https://attendify20-production.up.railway.app/api/n8n/check-user?telegram_chat_id={{ $json.chatId }}`
**Authentication:** None
**Options:**
- Response Format: JSON
- Include Response Headers: No

---

### Node: Route if User Exists
**Type:** `IF`
**Conditions:**
- `{{ $json.exists }}` equals `true`

**If TRUE (User exists):**
→ Send Welcome Back Message

**If FALSE (New user):**
→ Send Registration Prompt

---

### Node: Send Welcome Back
**Type:** `Telegram`
**Operation:** Send Message
**Resource:** Message
**Chat ID:** `{{ $('Extract Message Data').item.json.chatId }}`
**Text:**
```
👋 Welcome back, {{ $json.user.name }}!

You're already registered for daily notifications.

📅 Your class schedules will be sent every day at 6:00 AM

Commands:
/today - Get today's schedule now
/stop - Disable notifications
/resume - Resume notifications
/help - Show all commands
```

---

### Node: Send Registration Prompt
**Type:** `Telegram`
**Operation:** Send Message
**Chat ID:** `{{ $('Extract Message Data').item.json.chatId }}`
**Text:**
```
👋 Welcome to Attendify!

Please enter your ID:
• Students: Your Student ID (e.g., STU-2025-001)
• Teachers: Your Teacher ID (e.g., TEACH-001)
```

---

## 🌿 BRANCH 2: ID Registration (Fallback)

### Node: Register User API
**Type:** `HTTP Request`
**Method:** POST
**URL:** `https://attendify20-production.up.railway.app/api/n8n/register`
**Authentication:** None
**Send Body:** Yes
**Body Content Type:** JSON
**Body:**
```json
{
  "id": "={{ $json.messageText }}",
  "telegram_chat_id": "={{ $json.chatId }}",
  "telegram_username": "={{ $json.username }}"
}
```

---

### Node: Check Registration Success
**Type:** `IF`
**Conditions:**
- `{{ $json.success }}` equals `true`

**If TRUE:**
→ Send Success Message

**If FALSE:**
→ Send Error Message

---

### Node: Send Registration Success
**Type:** `Telegram`
**Operation:** Send Message
**Chat ID:** `{{ $('Extract Message Data').item.json.chatId }}`
**Text:**
```
✅ Registration successful!

Welcome, {{ $json.user.name }} ({{ $json.user.type }})

📅 You will receive your class schedule every day at 6:00 AM

Commands:
/today - Get today's schedule now
/stop - Disable notifications
/resume - Resume notifications
/help - Show all commands
```

---

### Node: Send Registration Error
**Type:** `Telegram`
**Operation:** Send Message
**Chat ID:** `{{ $('Extract Message Data').item.json.chatId }}`
**Text:**
```
❌ {{ $json.message }}

Please check your ID and try again.

Valid ID formats:
• Student ID: STU-2025-001
• Teacher ID: TEACH-001

Need help? Send /help
```

---

## 🌿 BRANCH 3: /today Command

### Node: Get Today's Schedule
**Type:** `HTTP Request`
**Method:** GET
**URL:** `https://attendify20-production.up.railway.app/api/n8n/today-schedule?telegram_chat_id={{ $json.chatId }}`
**Authentication:** None

---

### Node: Check if Has Classes
**Type:** `IF`
**Conditions:**
- `{{ $json.total_classes }}` greater than `0`

**If TRUE:**
→ Format and Send Schedule

**If FALSE:**
→ Send No Classes Message

---

### Node: Format Schedule Message
**Type:** `Code` (JavaScript)

```javascript
const response = $input.item.json;
const user = response.user;
const classes = response.classes || [];

// Build class list
let classText = '';
if (classes.length > 0) {
  classText = classes.map((cls, index) => {
    if (user.type === 'teacher') {
      return `${index + 1}. ${cls.time} - ${cls.class_name} (${cls.class_code})
   📍 ${cls.location || 'TBA'}
   👥 ${cls.student_count || 0} student(s) enrolled`;
    } else {
      return `${index + 1}. ${cls.time} - ${cls.class_name} (${cls.class_code})
   📍 ${cls.location || 'TBA'}
   👨‍🏫 ${cls.teacher_name || 'TBA'}`;
    }
  }).join('\n\n');
}

// Create message based on user type
let greeting = user.type === 'teacher' ? '👨‍🏫' : '📚';
let message;

if (user.type === 'teacher') {
  message = `${greeting} Good day, ${user.name}!

📅 ${response.date}
📚 You have ${response.total_classes} class(es) to teach today:

${classText}

Have a productive day! 🎓`;
} else {
  message = `${greeting} Good day, ${user.name}!

📅 ${response.date}
📌 You have ${response.total_classes} class(es) today:

${classText}

Have a great day! 🎓`;
}

return [{
  json: {
    chatId: $('Extract Message Data').item.json.chatId,
    message: message
  }
}];
```

---

### Node: Send Schedule
**Type:** `Telegram`
**Operation:** Send Message
**Chat ID:** `{{ $json.chatId }}`
**Text:** `{{ $json.message }}`
**Parse Mode:** Markdown (Legacy)

---

### Node: Send No Classes Message
**Type:** `Telegram`
**Operation:** Send Message
**Chat ID:** `{{ $('Extract Message Data').item.json.chatId }}`
**Text:**
```
😊 Good news, {{ $('Get Today\'s Schedule').item.json.user.name }}!

📅 {{ $('Get Today\'s Schedule').item.json.date }}
🎉 You have no classes scheduled today!

Enjoy your free day! ✨
```

---

## 🌿 BRANCH 4: /stop Command

### Node: Disable Notifications API
**Type:** `HTTP Request`
**Method:** POST
**URL:** `https://attendify20-production.up.railway.app/api/n8n/notifications/disable`
**Authentication:** None
**Send Body:** Yes
**Body Content Type:** JSON
**Body:**
```json
{
  "telegram_chat_id": "={{ $json.chatId }}"
}
```

---

### Node: Send Stop Confirmation
**Type:** `Telegram`
**Operation:** Send Message
**Chat ID:** `{{ $('Extract Message Data').item.json.chatId }}`
**Text:**
```
🔕 Notifications disabled

You will no longer receive daily class schedule notifications.

To resume notifications, send /resume
```

---

## 🌿 BRANCH 5: /resume Command

### Node: Enable Notifications API
**Type:** `HTTP Request`
**Method:** POST
**URL:** `https://attendify20-production.up.railway.app/api/n8n/notifications/enable`
**Authentication:** None
**Send Body:** Yes
**Body Content Type:** JSON
**Body:**
```json
{
  "telegram_chat_id": "={{ $json.chatId }}"
}
```

---

### Node: Send Resume Confirmation
**Type:** `Telegram`
**Operation:** Send Message
**Chat ID:** `{{ $('Extract Message Data').item.json.chatId }}`
**Text:**
```
🔔 Notifications enabled

You will receive your class schedule every day at 6:00 AM

Use /today to get today's schedule now
```

---

## 🌿 BRANCH 6: /help Command

### Node: Send Help Message
**Type:** `Telegram`
**Operation:** Send Message
**Chat ID:** `{{ $json.chatId }}`
**Text:**
```
📚 Attendify Bot - Help

Available Commands:

/start - Register or view status
/today - Get today's class schedule
/stop - Disable daily notifications
/resume - Resume daily notifications
/help - Show this help message

📝 How to Register:
1. Send /start
2. Enter your Student ID or Teacher ID
   Examples:
   • STU-2025-001 (for students)
   • TEACH-001 (for teachers)

📅 Daily Schedule:
• Automatic notifications at 6:00 AM
• Get instant schedule with /today

Need assistance? Contact your administrator.
```

---

## 📊 WORKFLOW VISUAL STRUCTURE

```
┌─────────────────────┐
│ Telegram Trigger    │
└──────────┬──────────┘
           │
           v
┌─────────────────────┐
│ Extract Message     │
└──────────┬──────────┘
           │
           v
┌─────────────────────┐
│  Switch (Route)     │
└──┬──┬──┬──┬──┬──────┘
   │  │  │  │  │  │
   │  │  │  │  │  └─→ (Plain Text) → Register ID
   │  │  │  │  └────→ /help → Help Message
   │  │  │  └───────→ /resume → Enable Notif
   │  │  └──────────→ /stop → Disable Notif
   │  └─────────────→ /today → Get Schedule
   └────────────────→ /start → Check User
```

---

## 🧪 TESTING CHECKLIST

### Test Case 1: New User Registration
1. ✅ Send `/start`
2. ✅ Bot asks for ID
3. ✅ Send `STU-2025-001`
4. ✅ Bot confirms registration
5. ✅ Check database: `telegram_chat_id` updated

### Test Case 2: Existing User
1. ✅ Send `/start` (already registered)
2. ✅ Bot says "Welcome back"

### Test Case 3: Get Today's Schedule
1. ✅ Send `/today`
2. ✅ Bot shows schedule (if classes exist)
3. ✅ Bot says "No classes" (if none)

### Test Case 4: Disable Notifications
1. ✅ Send `/stop`
2. ✅ Bot confirms disabled
3. ✅ Check database: `notification_enabled = false`

### Test Case 5: Resume Notifications
1. ✅ Send `/resume`
2. ✅ Bot confirms enabled
3. ✅ Check database: `notification_enabled = true`

### Test Case 6: Invalid ID
1. ✅ Send `/start`
2. ✅ Send `INVALID-ID`
3. ✅ Bot shows error message

### Test Case 7: Duplicate Registration
1. ✅ Register with one Telegram account
2. ✅ Try same ID with different account
3. ✅ Bot rejects with "already registered" message

### Test Case 8: Help Command
1. ✅ Send `/help`
2. ✅ Bot shows all commands

---

## 🚀 DEPLOYMENT STEPS

### Step 1: Create Telegram Credentials in n8n
1. Go to n8n → Credentials
2. Click "New Credential"
3. Select "Telegram API"
4. Enter Bot Token: `8422252808:AAF-ENuddVjuQy1C5VOqPtGvLtK1g8LKmSk`
5. Save

### Step 2: Import Workflow (or Build Manually)
1. Create new workflow in n8n
2. Add nodes as described above
3. Connect nodes according to flow
4. Configure each node with settings above

### Step 3: Test Endpoints
Test all API endpoints first:
```bash
# Test health
curl https://attendify20-production.up.railway.app/api/n8n/health

# Test check user (should return exists: false for new user)
curl "https://attendify20-production.up.railway.app/api/n8n/check-user?telegram_chat_id=123456789"

# Test registration
curl -X POST https://attendify20-production.up.railway.app/api/n8n/register \
  -H "Content-Type: application/json" \
  -d '{"id":"STU-2025-001","telegram_chat_id":"123456789","telegram_username":"testuser"}'

# Test get schedule
curl "https://attendify20-production.up.railway.app/api/n8n/today-schedule?telegram_chat_id=123456789"
```

### Step 4: Activate Workflow
1. Save workflow
2. Click "Active" toggle
3. Test with real Telegram messages

### Step 5: Configure Webhook (If Needed)
If webhook isn't auto-configured:
1. Get webhook URL from n8n Telegram Trigger
2. Set webhook: `https://api.telegram.org/bot8422252808:AAF-ENuddVjuQy1C5VOqPtGvLtK1g8LKmSk/setWebhook?url=YOUR_N8N_WEBHOOK_URL`

---

## 📝 MESSAGE TEMPLATES (For Easy Copy)

### Welcome Message (New User)
```
👋 Welcome to Attendify!

Please enter your ID:
• Students: Your Student ID (e.g., STU-2025-001)
• Teachers: Your Teacher ID (e.g., TEACH-001)
```

### Success Registration
```
✅ Registration successful!

Welcome, {{ name }} ({{ type }})

📅 You will receive your class schedule every day at 6:00 AM

Commands:
/today - Get today's schedule now
/stop - Disable notifications
/resume - Resume notifications
/help - Show all commands
```

### Error - ID Not Found
```
❌ ID not found in our system.

Please check your ID and try again.

Valid ID formats:
• Student ID: STU-2025-001
• Teacher ID: TEACH-001

Need help? Send /help
```

### Error - Already Registered
```
❌ This ID is already registered with another Telegram account.

If this is your ID, please contact your administrator for assistance.
```

---

## 🔐 SECURITY FEATURES

✅ **Duplicate Prevention:** Same ID cannot be registered with multiple Telegram accounts
✅ **ID Validation:** Searches both student and teacher tables
✅ **User Verification:** Checks telegram_chat_id before operations
✅ **Error Handling:** Graceful error messages for all failure cases
✅ **Logging:** All operations logged to Laravel log

---

## 📱 Bot Commands Summary

| Command | Description | Auth Required |
|---------|-------------|---------------|
| `/start` | Register or show welcome message | No |
| `/today` | Get today's schedule immediately | Yes |
| `/stop` | Disable daily notifications | Yes |
| `/resume` | Resume daily notifications | Yes |
| `/help` | Show all commands and help | No |

---

## 🎯 API Endpoints Summary

| Endpoint | Method | Purpose |
|----------|--------|---------|
| `/api/n8n/check-user` | GET | Check if user exists |
| `/api/n8n/register` | POST | Register user with ID |
| `/api/n8n/today-schedule` | GET | Get today's schedule |
| `/api/n8n/notifications/disable` | POST | Disable notifications |
| `/api/n8n/notifications/enable` | POST | Enable notifications |
| `/api/n8n/schedules/today` | GET | Get all users' schedules (6 AM cron) |

---

## ✅ DONE!

Your Telegram Bot is ready! Users can now:
1. Register by sending their ID
2. Receive daily schedules at 6 AM
3. Get instant schedule with /today
4. Manage notification preferences

🚀 **Next:** Test the workflow and share the bot with your users!
