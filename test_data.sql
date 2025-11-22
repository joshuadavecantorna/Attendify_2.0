-- Add test user
INSERT INTO users (name, email, password, role, telegram_chat_id, notifications_enabled, created_at, updated_at)
VALUES 
('Test Student', 'teststudent@attendify.test', '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'student', '123456789', true, NOW(), NOW())
ON CONFLICT (email) DO NOTHING;

-- Add test teacher user
INSERT INTO users (name, email, password, role, telegram_chat_id, notifications_enabled, created_at, updated_at)
VALUES 
('Test Teacher', 'testteacher@attendify.test', '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'teacher', '987654321', true, NOW(), NOW())
ON CONFLICT (email) DO NOTHING;

-- Get the user IDs (you'll need to run these queries and note the IDs)
-- SELECT id FROM users WHERE email = 'teststudent@attendify.test';
-- SELECT id FROM users WHERE email = 'testteacher@attendify.test';

-- Add to students table (replace USER_ID with actual user_id from above)
-- INSERT INTO students (user_id, created_at, updated_at)
-- VALUES (USER_ID, NOW(), NOW());

-- Add to teachers table (replace USER_ID with actual user_id from above)
-- INSERT INTO teachers (user_id, created_at, updated_at)
-- VALUES (USER_ID, NOW(), NOW());

-- Add test class (replace TEACHER_ID with actual teacher ID)
-- INSERT INTO classes (name, room, teacher_id, created_at, updated_at)
-- VALUES ('Test Class', 'Room 101', TEACHER_ID, NOW(), NOW());

-- Enroll student (replace STUDENT_ID and CLASS_ID)
-- INSERT INTO student_class (student_id, class_id, created_at, updated_at)
-- VALUES (STUDENT_ID, CLASS_ID, NOW(), NOW());
