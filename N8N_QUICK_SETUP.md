# 📱 N8N Quick Setup Guide for Attendify Telegram Bot

## 🎯 Quick Overview

You'll create 1 workflow with these main parts:
1. **Telegram Trigger** - Listens for messages
2. **Extract Message** - Parses chat data
3. **Route Command** - Decides what to do
4. **Action Nodes** - Responds to user

---

## 🚀 EASY SETUP METHOD

### Method 1: Add Nodes Manually (Recommended for Learning)

#### Step 1: Add Telegram Trigger
1. Click **"+"** to add node
2. Search **"Telegram Trigger"**
3. Select it
4. Choose your Telegram credential (created earlier)
5. **Updates:** Select "message"
6. Click **"Execute Node"** to test

#### Step 2: Add Code Node (Extract Message)
1. Click **"+"** after Telegram Trigger
2. Search **"Code"**
3. Select **"Code"** node
4. Paste this code:

```javascript
// Extract message data
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
    messageText: messageText
  }
}];
```

#### Step 3: Add Switch Node (Route Commands)
1. Click **"+"** after Code node
2. Search **"Switch"**
3. Configure 6 outputs:

**Output 1: /start**
- Condition: `{{ $json.messageText }}` equals `/start`
- Name: "start_command"

**Output 2: /today**
- Condition: `{{ $json.messageText }}` equals `/today`
- Name: "today_command"

**Output 3: /stop**
- Condition: `{{ $json.messageText }}` equals `/stop`
- Name: "stop_command"

**Output 4: /resume**
- Condition: `{{ $json.messageText }}` equals `/resume`
- Name: "resume_command"

**Output 5: /help**
- Condition: `{{ $json.messageText }}` equals `/help`
- Name: "help_command"

**Output 6: Fallback** (ID Registration)
- Keep as "Otherwise" or "Fallback"

---

## 📋 NOW ADD RESPONSE NODES

### For /start Command:

#### Node: Check User API
1. Add **HTTP Request** node from "start_command" output
2. **Method:** GET
3. **URL:** 
```
https://attendify20-production.up.railway.app/api/n8n/check-user?telegram_chat_id={{ $('Extract Message Data').item.json.chatId }}
```
4. **Authentication:** None
5. **Response Format:** JSON

#### Node: IF User Exists
1. Add **IF** node after Check User API
2. **Condition:** `{{ $json.exists }}` equals `true`

**If TRUE (User exists):**
Add Telegram node:
- **Operation:** Send Message
- **Chat ID:** `{{ $('Extract Message Data').item.json.chatId }}`
- **Text:**
```
👋 Welcome back!

You're already registered for daily notifications.

📅 Your class schedules will be sent every day at 6:00 AM

Commands:
/today - Get today's schedule now
/stop - Disable notifications
/resume - Resume notifications
/help - Show all commands
```

**If FALSE (New user):**
Add Telegram node:
- **Operation:** Send Message
- **Chat ID:** `{{ $('Extract Message Data').item.json.chatId }}`
- **Text:**
```
👋 Welcome to Attendify!

Please enter your ID:
• Students: Your Student ID (e.g., STU-2025-001)
• Teachers: Your Teacher ID (e.g., TEACH-001)
```

---

### For ID Registration (Fallback):

#### Node: Register API
1. Add **HTTP Request** node from "Fallback" output
2. **Method:** POST
3. **URL:** `https://attendify20-production.up.railway.app/api/n8n/register`
4. **Send Body:** Yes
5. **Body Content Type:** JSON
6. **Specify Body:** Using Fields
7. **Fields:**
   - **id:** `{{ $('Extract Message Data').item.json.messageText }}`
   - **telegram_chat_id:** `{{ $('Extract Message Data').item.json.chatId }}`
   - **telegram_username:** `{{ $('Extract Message Data').item.json.username }}`

#### Node: Check Registration Success
1. Add **IF** node
2. **Condition:** `{{ $json.success }}` equals `true`

**If TRUE:**
Add Telegram node:
- **Text:**
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

**If FALSE:**
Add Telegram node:
- **Text:**
```
❌ {{ $json.message }}

Please check your ID and try again.

Valid ID formats:
• Student ID: STU-2025-001
• Teacher ID: TEACH-001

Need help? Send /help
```

---

### For /today Command:

#### Node: Get Schedule API
1. Add **HTTP Request** from "today_command" output
2. **Method:** GET
3. **URL:**
```
https://attendify20-production.up.railway.app/api/n8n/today-schedule?telegram_chat_id={{ $('Extract Message Data').item.json.chatId }}
```

#### Node: Check Has Classes
1. Add **IF** node
2. **Condition:** `{{ $json.total_classes }}` greater than `0`

**If TRUE (Has Classes):**
Add **Code** node to format message:
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

const greeting = user.type === 'teacher' ? '👨‍🏫' : '📚';
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

Then add Telegram node:
- **Chat ID:** `{{ $json.chatId }}`
- **Text:** `{{ $json.message }}`

**If FALSE (No Classes):**
Add Telegram node:
- **Text:**
```
😊 Good news!

📅 {{ $('Get Schedule API').item.json.date }}
🎉 You have no classes scheduled today!

Enjoy your free day! ✨
```

---

### For /stop Command:

#### Node: Disable Notifications API
1. Add **HTTP Request** from "stop_command" output
2. **Method:** POST
3. **URL:** `https://attendify20-production.up.railway.app/api/n8n/notifications/disable`
4. **Send Body:** Yes
5. **Body Content Type:** JSON
6. **Fields:**
   - **telegram_chat_id:** `{{ $('Extract Message Data').item.json.chatId }}`

Then add Telegram node:
- **Text:**
```
🔕 Notifications disabled

You will no longer receive daily class schedule notifications.

To resume notifications, send /resume
```

---

### For /resume Command:

#### Node: Enable Notifications API
1. Add **HTTP Request** from "resume_command" output
2. **Method:** POST
3. **URL:** `https://attendify20-production.up.railway.app/api/n8n/notifications/enable`
4. **Send Body:** Yes
5. **Body Content Type:** JSON
6. **Fields:**
   - **telegram_chat_id:** `{{ $('Extract Message Data').item.json.chatId }}`

Then add Telegram node:
- **Text:**
```
🔔 Notifications enabled

You will receive your class schedule every day at 6:00 AM

Use /today to get today's schedule now
```

---

### For /help Command:

Add Telegram node from "help_command" output:
- **Chat ID:** `{{ $('Extract Message Data').item.json.chatId }}`
- **Text:**
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

## ✅ FINAL STEPS

### Step 1: Save Workflow
Click **"Save"** button (top right)

### Step 2: Activate Workflow
Toggle **"Active"** switch (top right) to ON

### Step 3: Test!
1. Open Telegram
2. Search for your bot (check @BotFather for bot username)
3. Send: `/start`
4. Bot should respond with welcome message
5. Send your test ID (e.g., `STU-2025-001`)
6. Bot should confirm registration

---

## 🧪 TESTING CHECKLIST

- [ ] Send `/start` → Should ask for ID
- [ ] Send `STU-2025-001` → Should register
- [ ] Send `/today` → Should show schedule
- [ ] Send `/stop` → Should disable notifications
- [ ] Send `/resume` → Should enable notifications
- [ ] Send `/help` → Should show help
- [ ] Send invalid ID → Should show error
- [ ] Try registering same ID twice → Should reject

---

## 🐛 TROUBLESHOOTING

**Bot doesn't respond:**
- Check workflow is "Active" (green toggle)
- Check Telegram Trigger is working (test it)
- Check n8n execution log for errors

**API errors:**
- Verify Railway URL is correct
- Test API endpoints with curl
- Check n8n execution details

**Wrong responses:**
- Check Switch node conditions
- Verify node connections
- Check data passing between nodes

---

## 📞 NEED HELP?

Check execution logs in n8n:
1. Click "Executions" in left sidebar
2. Find failed executions (red)
3. Click to see details
4. Check which node failed

---

## 🎉 YOU'RE DONE!

Once everything works:
1. Share bot link with users
2. They send `/start` and their ID
3. They get daily schedules at 6 AM
4. They can use `/today` anytime

**Bot Link Format:** `t.me/YOUR_BOT_USERNAME`
(Find username in @BotFather)
