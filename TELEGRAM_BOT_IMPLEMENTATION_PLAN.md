# 🤖 Telegram Bot Implementation Plan

## Bot Token
```
8422252808:AAF-ENuddVjuQy1C5VOqPtGvLtK1g8LKmSk
```

---

## 📋 User Flow

### **Flow 1: First-Time Registration**
```
User → /start
Bot  → "👋 Welcome to Attendify!

        Please enter your ID:
        • Students: Your Student ID
        • Teachers: Your Teacher ID"

User → 2021-001
Bot  → (Processing...)
       API searches students table → Found!
       Updates telegram_chat_id, telegram_username, notification_enabled
       
Bot  → "✅ Registration successful!
        
        Welcome, JOSHUA DAVE G. CANTORNA (Student)
        
        📅 You will receive your class schedule every day at 6:00 AM
        
        Commands:
        /today - Get today's schedule now"
```

### **Flow 2: Already Registered User**
```
User → /start
Bot  → "👋 Welcome back, JOSHUA DAVE G. CANTORNA!
        
        You're already registered for daily notifications.
        
        Commands:
        /today - Get today's schedule now"
```

### **Flow 3: Get Today's Schedule**
```
User → /today
Bot  → "📚 Good morning, JOSHUA DAVE G. CANTORNA!

        📅 Friday, December 12, 2025
        📌 You have 3 class(es) today:

        1. 7:00 AM - Society and Culture 2 (GE08)
           📍 CE 14
           👨‍🏫 Ryan Gonzaga

        2. 7:00 AM - Programming I (CS02)
           📍 CE 12
           👨‍🏫 Jose Rizal

        3. 11:00 AM - DSA (CS03)
           📍 ICT-1
           👨‍🏫 Jose Rizal

        Have a great day! 🎓"
```

### **Flow 4: ID Not Found**
```
User → /start
Bot  → "👋 Welcome to Attendify!
        Please enter your ID..."

User → INVALID-123
Bot  → "❌ ID not found in our system.
        
        Please check your ID and try again.
        
        Make sure you're entering:
        • Your Student ID (if you're a student)
        • Your Teacher ID (if you're a teacher)"
```

---

## 🏗️ N8N Workflow Structure

### **Workflow: Telegram Bot Handler**

```
┌─────────────────────┐
│  Telegram Trigger   │ ← Listens for all messages
└──────────┬──────────┘
           │
           v
┌─────────────────────┐
│   Extract Message   │ ← Get: message.text, chat.id, username
└──────────┬──────────┘
           │
           v
┌─────────────────────┐
│   Switch (Route)    │ ← Check message content
└──┬──────┬──────┬────┘
   │      │      │
   v      v      v
/start  /today  (ID)
   │      │      │
   v      v      v
```

#### **Branch 1: /start Command**
```
┌──────────────────────────┐
│ HTTP Request             │
│ GET /api/n8n/check-user  │
│ ?telegram_chat_id=...    │
└──────────┬───────────────┘
           │
           v
┌──────────────────────────┐
│    Switch (If)           │
│    user_exists?          │
└──┬──────────────┬────────┘
   │              │
   YES            NO
   │              │
   v              v
Send           Send
"Welcome       "Please
back!"         enter ID"
```

#### **Branch 2: /today Command**
```
┌──────────────────────────────┐
│ HTTP Request                 │
│ GET /api/n8n/today-schedule  │
│ ?telegram_chat_id=...        │
└──────────┬───────────────────┘
           │
           v
┌──────────────────────────────┐
│   Format Schedule Message    │
└──────────┬───────────────────┘
           │
           v
┌──────────────────────────────┐
│   Send Telegram Message      │
└──────────────────────────────┘
```

#### **Branch 3: ID Registration**
```
┌──────────────────────────────┐
│ HTTP Request                 │
│ POST /api/n8n/register       │
│ Body: {                      │
│   id: message.text           │
│   telegram_chat_id: ...      │
│   telegram_username: ...     │
│ }                            │
└──────────┬───────────────────┘
           │
           v
┌──────────────────────────────┐
│    Switch (Response)         │
│    success = true/false      │
└──┬──────────────┬────────────┘
   │              │
  YES             NO
   │              │
   v              v
Send           Send
"✅            "❌ ID
Registered!"   not found"
```

---

## 🔌 Laravel API Endpoints to Create

### **1. Check if User Exists**
```php
GET /api/n8n/check-user?telegram_chat_id={chat_id}

Response:
{
  "success": true,
  "exists": true,
  "user": {
    "name": "JOSHUA DAVE G. CANTORNA",
    "type": "student",
    "id": 1,
    "student_id": "2021-001"
  }
}

OR

{
  "success": true,
  "exists": false
}
```

### **2. Register User**
```php
POST /api/n8n/register-telegram
Body: {
  "id": "2021-001",
  "telegram_chat_id": "5226305211",
  "telegram_username": "Joshua_Dave"
}

Logic:
1. Search students table WHERE student_id = '2021-001'
2. If not found, search teachers WHERE teacher_id = '2021-001'
3. If found, update:
   - telegram_chat_id
   - telegram_username
   - notification_enabled = true
4. Return user info

Response (Success):
{
  "success": true,
  "message": "Registration successful",
  "user": {
    "type": "student",
    "id": 1,
    "name": "JOSHUA DAVE G. CANTORNA",
    "student_id": "2021-001"
  }
}

Response (Not Found):
{
  "success": false,
  "message": "ID not found in our system"
}

Response (Already Registered):
{
  "success": false,
  "message": "This ID is already registered with another Telegram account",
  "current_user": {
    "telegram_username": "other_user"
  }
}
```

### **3. Get Today's Schedule for User**
```php
GET /api/n8n/today-schedule?telegram_chat_id={chat_id}

Logic:
1. Find user by telegram_chat_id (check students then teachers)
2. If student: Get enrolled classes scheduled for today
3. If teacher: Get classes teaching today
4. Format and return

Response:
{
  "success": true,
  "user": {
    "name": "JOSHUA DAVE G. CANTORNA",
    "type": "student"
  },
  "date": "Friday, December 12, 2025",
  "total_classes": 3,
  "classes": [
    {
      "time": "7:00 AM",
      "class_name": "Society and Culture 2",
      "class_code": "GE08",
      "location": "CE 14",
      "teacher_name": "Ryan Gonzaga"
    },
    ...
  ]
}

Response (No Classes):
{
  "success": true,
  "user": {...},
  "total_classes": 0,
  "message": "No classes scheduled today"
}

Response (User Not Found):
{
  "success": false,
  "message": "User not registered. Please send /start"
}
```

---

## 📝 N8N Workflow Pseudocode

### **Main Handler Node (JavaScript)**
```javascript
// Extract message data
const chatId = $input.item.json.message.chat.id;
const username = $input.item.json.message.chat.username || '';
const messageText = $input.item.json.message.text || '';
const firstName = $input.item.json.message.chat.first_name || '';

// Route based on message
if (messageText === '/start') {
  return {
    json: {
      chatId: chatId,
      command: 'start',
      username: username,
      firstName: firstName
    }
  };
} else if (messageText === '/today') {
  return {
    json: {
      chatId: chatId,
      command: 'today'
    }
  };
} else {
  // Assume it's an ID registration
  return {
    json: {
      chatId: chatId,
      command: 'register',
      id: messageText.trim(),
      username: username
    }
  };
}
```

---

## 🎨 Message Templates

### **Welcome Message (New User)**
```
👋 Welcome to Attendify!

Please enter your ID:
• Students: Your Student ID
• Teachers: Your Teacher ID
```

### **Welcome Back Message**
```
👋 Welcome back, [NAME]!

You're already registered for daily notifications.

Commands:
/today - Get today's schedule now
```

### **Registration Success**
```
✅ Registration successful!

Welcome, [NAME] ([TYPE])

📅 You will receive your class schedule every day at 6:00 AM

Commands:
/today - Get today's schedule now
```

### **ID Not Found**
```
❌ ID not found in our system.

Please check your ID and try again.

Make sure you're entering:
• Your Student ID (if you're a student)
• Your Teacher ID (if you're a teacher)
```

### **Schedule Message (Student)**
```
📚 Good morning, [NAME]!

📅 [DATE]
📌 You have [COUNT] class(es) today:

[CLASS_LIST]

Have a great day! 🎓
```

### **Schedule Message (Teacher)**
```
👨‍🏫 Good morning, [NAME]!

📅 [DATE]
📚 You have [COUNT] class(es) to teach today:

[CLASS_LIST]

Have a productive day! 🎓
```

### **No Classes Today**
```
😊 Good news, [NAME]!

📅 [DATE]
🎉 You have no classes scheduled today!

Enjoy your free day! ✨
```

---

## 🔐 Security Considerations

### **Current Approach (Simple):**
- ✅ No verification required
- ✅ First person to register an ID owns it
- ⚠️ Anyone with an ID can register as that person

### **Potential Issues:**
1. **ID Theft:** Someone could register using another person's ID
2. **Duplicate Registrations:** Same ID registered from multiple Telegram accounts

### **Mitigation (Optional - Future):**
- Check if ID is already linked to another telegram_chat_id
- Allow users to "claim" their ID with email verification
- Add admin approval process

### **Current Plan:**
- Keep it simple for now
- If ID already has a telegram_chat_id, reject with message:
  "This ID is already registered. Contact admin if you need help."

---

## 📊 Database Queries

### **Find User by Telegram Chat ID**
```sql
-- Check students
SELECT s.*, u.name 
FROM students s 
JOIN users u ON u.id = s.user_id
WHERE s.telegram_chat_id = '5226305211'
LIMIT 1;

-- If not found, check teachers
SELECT t.*, u.name 
FROM teachers t 
JOIN users u ON u.id = t.user_id
WHERE t.telegram_chat_id = '5226305211'
LIMIT 1;
```

### **Find User by ID (Student or Teacher)**
```sql
-- Try student first
SELECT s.*, u.name 
FROM students s 
JOIN users u ON u.id = s.user_id
WHERE s.student_id = '2021-001'
LIMIT 1;

-- If not found, try teacher
SELECT t.*, u.name 
FROM teachers t 
JOIN users u ON u.id = t.user_id
WHERE t.teacher_id = 'T-123'
LIMIT 1;
```

### **Update Telegram Info**
```sql
-- For student
UPDATE students 
SET telegram_chat_id = '5226305211',
    telegram_username = 'Joshua_Dave',
    notification_enabled = true
WHERE id = 1;

-- For teacher
UPDATE teachers 
SET telegram_chat_id = '1234567890',
    telegram_username = 'teacher_user',
    notification_enabled = true
WHERE id = 2;
```

---

## ✅ Implementation Checklist

### **Backend (Laravel)**
- [ ] Create N8NController methods:
  - [ ] `checkUser()` - Check if telegram_chat_id exists
  - [ ] `registerTelegram()` - Register user with ID
  - [ ] `getTodayScheduleByChat()` - Get schedule by telegram_chat_id
- [ ] Add routes in `routes/api.php`:
  - [ ] `GET /api/n8n/check-user`
  - [ ] `POST /api/n8n/register-telegram`
  - [ ] `GET /api/n8n/today-schedule`
- [ ] Test all endpoints

### **N8N Workflow**
- [ ] Create new workflow: "Telegram Bot Handler"
- [ ] Add Telegram Trigger node
- [ ] Add message parser node
- [ ] Add Switch node for routing
- [ ] Add HTTP Request nodes for each API
- [ ] Add response formatter nodes
- [ ] Add Telegram Send Message nodes
- [ ] Test each command flow

### **Testing**
- [ ] Test /start with new user
- [ ] Test /start with existing user
- [ ] Test ID registration (student)
- [ ] Test ID registration (teacher)
- [ ] Test invalid ID
- [ ] Test /today command (student with classes)
- [ ] Test /today command (student no classes)
- [ ] Test /today command (teacher)
- [ ] Test /today with unregistered user

---

## 🚀 Deployment Order

1. **Deploy Backend Changes to Railway**
   - Push new API endpoints
   - Test endpoints with curl

2. **Create N8N Workflow**
   - Set up Telegram Trigger
   - Configure API calls
   - Test in n8n

3. **Test End-to-End**
   - Register test user
   - Verify database updates
   - Check /today command
   - Verify 6 AM daily notifications still work

4. **Go Live**
   - Share bot link with users
   - Monitor for issues

---

## 📱 Bot Commands Summary

| Command | Description | Who Can Use |
|---------|-------------|-------------|
| `/start` | Register or show welcome message | Everyone |
| `/today` | Get today's schedule immediately | Registered users |

---

## 🎯 Next Steps

**Once you approve this plan, I will:**

1. ✅ Create the 3 new API endpoints in N8NController
2. ✅ Add routes to api.php
3. ✅ Provide the n8n workflow structure (JSON export)
4. ✅ Provide message templates for easy copy-paste
5. ✅ Test locally
6. ✅ Deploy to Railway

**Ready to proceed? Let me know if you want any changes to this plan!** 🚀
