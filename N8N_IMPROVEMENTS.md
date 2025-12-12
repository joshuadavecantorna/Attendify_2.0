# N8N Automation Improvements - Students & Teachers Support

## What Was Changed

### 1. Database Changes
✅ Added telegram fields to `teachers` table:
- `telegram_chat_id` - Telegram chat ID for sending messages
- `telegram_username` - Telegram username
- `preferred_language` - Language preference (default: 'en')
- `notification_enabled` - Enable/disable notifications (default: false)

### 2. Backend Changes

#### Teacher Model (`app/Models/Teacher.php`)
✅ Updated `$fillable` array to include new telegram fields
✅ Added `notification_enabled` to `$casts` as boolean

#### N8N Controller (`app/Http/Controllers/Api/N8NController.php`)
✅ Updated `getAllTodaySchedules()` method to fetch:
- **Students** with their classes (existing functionality)
- **Teachers** with classes they teach (NEW)
- Combined `all_users` array containing both

**New Response Structure:**
```json
{
  "success": true,
  "date": "2025-12-12",
  "date_formatted": "Friday, December 12, 2025",
  "day_of_week": "Friday",
  "total_students_with_classes": 1,
  "total_teachers_with_classes": 2,
  "total_users": 3,
  "students": [...],
  "teachers": [...],
  "all_users": [...]  // ← Use this in n8n
}
```

### 3. N8N Configuration Changes

#### Updated JavaScript Code
**File:** `n8n-improved-code.js`

**Key Features:**
- ✅ Processes `response.all_users` instead of `response.students`
- ✅ Detects user type (`student` or `teacher`)
- ✅ Different message formats:
  - **Students:** Shows teacher name for each class
  - **Teachers:** Shows student count for each class
- ✅ Different greetings:
  - Students: "📚 Good morning, [name]!"
  - Teachers: "👨‍🏫 Good morning, [name]!"

## How to Update Your N8N Workflow

### Step 1: Update the HTTP Request Node
**No changes needed** - URL stays the same:
```
https://attendify20-production.up.railway.app/api/n8n/schedules/today
```

### Step 2: Update the "Code in JavaScript" Node
1. Open the "Code in JavaScript" node
2. Delete the old code
3. Copy the new code from `n8n-improved-code.js`
4. Paste it into the node
5. Save

### Step 3: Test the Workflow
1. Click "Execute workflow"
2. Check the output - you should now see:
   - Multiple items (one per student/teacher)
   - Each item has `user_type` field
   - Teachers get different messages than students

## How to Enable Telegram Notifications

### For Students
Notifications are **already enabled** via existing setup.

### For Teachers (NEW)
Teachers need to set their Telegram info. You can:

**Option 1: Manual Database Update**
```sql
UPDATE teachers 
SET telegram_chat_id = 'YOUR_CHAT_ID',
    telegram_username = 'username',
    notification_enabled = true
WHERE id = 1;
```

**Option 2: Create a Teacher Telegram Setup Page**
(Future enhancement - similar to student setup)

## Testing

### Test the API Endpoint Directly
```bash
curl https://attendify20-production.up.railway.app/api/n8n/schedules/today
```

### Expected Response
```json
{
  "total_students_with_classes": 5,
  "total_teachers_with_classes": 3,
  "total_users": 8,
  "all_users": [
    {
      "student_id": 1,
      "student_name": "JOSHUA DAVE G. CANTORNA",
      "telegram_chat_id": "5226305211",
      "user_type": "student",
      "total_classes": 3,
      "classes": [...]
    },
    {
      "teacher_id": 2,
      "teacher_name": "Ryan Gonzaga",
      "telegram_chat_id": "1234567890",
      "user_type": "teacher",
      "total_classes": 5,
      "classes": [...]
    }
  ]
}
```

## Example Messages

### Student Message
```
📚 Good morning, JOSHUA DAVE G. CANTORNA!

📅 Friday, December 12, 2025
📌 You have 3 class(es) today:

1. 7:00 AM - Society and Culture 2 (GE08)
   📍 CE 14
   👨‍🏫 Ryan Gonzaga

2. 7:00 AM - Programming I (CS02)
   📍 CE 12
   👨‍🏫 Jose Rizal

Have a great day! 🎓
```

### Teacher Message
```
👨‍🏫 Good morning, Ryan Gonzaga!

📅 Friday, December 12, 2025
📚 You have 5 class(es) to teach today:

1. 7:00 AM - Society and Culture 2 (GE08)
   📍 CE 14
   👥 25 student(s) enrolled

2. 9:00 AM - Programming I (CS02)
   📍 CE 12
   👥 30 student(s) enrolled

Have a productive day! 🎓
```

## Benefits

✅ **Scalable:** Automatically handles all students and teachers
✅ **Role-aware:** Different messages for different user types
✅ **Efficient:** Single API call fetches everyone's schedule
✅ **Flexible:** Easy to add more user types in the future
✅ **Consistent:** Uses same n8n workflow for everyone

## Next Steps

1. ✅ Update n8n workflow with new JavaScript code
2. ⏳ Enable Telegram for teachers who want notifications
3. ⏳ Test with real data
4. ⏳ Consider adding:
   - Admin role support
   - Different time zones
   - Custom notification times per user
   - Weekend/holiday skip logic
