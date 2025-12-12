// ==================================================
// IMPROVED N8N CODE - Handles both Students and Teachers
// ==================================================
// Get API response
const response = $input.item.json;

// Check if all_users exist (this combines students and teachers)
if (!response.all_users || response.all_users.length === 0) {
  return [];
}

// Format message for each user (student or teacher)
return response.all_users.map(user => {
  const isTeacher = user.user_type === 'teacher';
  const userName = isTeacher ? user.teacher_name : user.student_name;
  
  // Build class list
  let classText = '';
  if (user.classes && user.classes.length > 0) {
    classText = user.classes.map((cls, index) => {
      if (isTeacher) {
        // Teacher view: show class with student count
        return `${index + 1}. ${cls.time} - ${cls.class_name} (${cls.class_code})
   📍 ${cls.location || 'TBA'}
   👥 ${cls.student_count || 0} student(s) enrolled`;
      } else {
        // Student view: show class with teacher name
        return `${index + 1}. ${cls.time} - ${cls.class_name} (${cls.class_code})
   📍 ${cls.location || 'TBA'}
   👨‍🏫 ${cls.teacher_name || 'TBA'}`;
      }
    }).join('\n\n');
  } else {
    classText = 'No classes scheduled today.';
  }

  // Get the date - use user's date or parent response date
  const dateFormatted = user.date || response.date_formatted || 'Today';

  // Create the full message with different greeting for teachers
  let message;
  if (isTeacher) {
    message = `👨‍🏫 Good morning, ${userName}!

📅 ${dateFormatted}
📚 You have ${user.total_classes || 0} class(es) to teach today:

${classText}

Have a productive day! 🎓`;
  } else {
    message = `📚 Good morning, ${userName}!

📅 ${dateFormatted}
📌 You have ${user.total_classes || 0} class(es) today:

${classText}

Have a great day! 🎓`;
  }

  return {
    json: {
      chatId: user.telegram_chat_id,
      message: message,
      user_name: userName,
      user_type: user.user_type,
      user_id: isTeacher ? user.teacher_id : user.student_id
    }
  };
});
