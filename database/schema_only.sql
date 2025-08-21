-- Update database schema for LMS University - Schema changes only
-- Add missing columns to existing tables

USE lms_university;

-- Add missing columns to courses table (ignore if already exists)
SET @exist := (SELECT count(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA='lms_university' AND TABLE_NAME='courses' AND COLUMN_NAME='start_date');
SET @sqlstmt := IF(@exist=0,'ALTER TABLE courses ADD COLUMN start_date DATE NULL AFTER max_students','SELECT ''Column start_date already exists'' as Info');
PREPARE stmt FROM @sqlstmt;
EXECUTE stmt;

SET @exist := (SELECT count(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA='lms_university' AND TABLE_NAME='courses' AND COLUMN_NAME='end_date');
SET @sqlstmt := IF(@exist=0,'ALTER TABLE courses ADD COLUMN end_date DATE NULL AFTER start_date','SELECT ''Column end_date already exists'' as Info');
PREPARE stmt FROM @sqlstmt;
EXECUTE stmt;

SET @exist := (SELECT count(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA='lms_university' AND TABLE_NAME='courses' AND COLUMN_NAME='updated_at');
SET @sqlstmt := IF(@exist=0,'ALTER TABLE courses ADD COLUMN updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER created_at','SELECT ''Column updated_at already exists'' as Info');
PREPARE stmt FROM @sqlstmt;
EXECUTE stmt;

-- Update status enum to include 'archived' (ignore if already updated)
SET @exist := (SELECT count(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA='lms_university' AND TABLE_NAME='courses' AND COLUMN_NAME='status' AND COLUMN_TYPE LIKE '%archived%');
SET @sqlstmt := IF(@exist=0,'ALTER TABLE courses MODIFY COLUMN status ENUM(''active'', ''inactive'', ''archived'') DEFAULT ''active''','SELECT ''Status enum already updated'' as Info');
PREPARE stmt FROM @sqlstmt;
EXECUTE stmt;

-- Add missing columns to assignments table
SET @exist := (SELECT count(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA='lms_university' AND TABLE_NAME='assignments' AND COLUMN_NAME='instructions');
SET @sqlstmt := IF(@exist=0,'ALTER TABLE assignments ADD COLUMN instructions TEXT NULL AFTER description','SELECT ''Column instructions already exists'' as Info');
PREPARE stmt FROM @sqlstmt;
EXECUTE stmt;

SET @exist := (SELECT count(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA='lms_university' AND TABLE_NAME='assignments' AND COLUMN_NAME='assignment_type');
SET @sqlstmt := IF(@exist=0,'ALTER TABLE assignments ADD COLUMN assignment_type ENUM(''project'', ''homework'', ''essay'', ''lab'', ''presentation'', ''quiz'', ''other'') DEFAULT ''homework'' AFTER instructions','SELECT ''Column assignment_type already exists'' as Info');
PREPARE stmt FROM @sqlstmt;
EXECUTE stmt;

SET @exist := (SELECT count(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA='lms_university' AND TABLE_NAME='assignments' AND COLUMN_NAME='allow_late_submission');
SET @sqlstmt := IF(@exist=0,'ALTER TABLE assignments ADD COLUMN allow_late_submission BOOLEAN DEFAULT FALSE AFTER assignment_type','SELECT ''Column allow_late_submission already exists'' as Info');
PREPARE stmt FROM @sqlstmt;
EXECUTE stmt;

SET @exist := (SELECT count(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA='lms_university' AND TABLE_NAME='assignments' AND COLUMN_NAME='late_penalty');
SET @sqlstmt := IF(@exist=0,'ALTER TABLE assignments ADD COLUMN late_penalty DECIMAL(5,2) DEFAULT 0.00 AFTER allow_late_submission','SELECT ''Column late_penalty already exists'' as Info');
PREPARE stmt FROM @sqlstmt;
EXECUTE stmt;

SET @exist := (SELECT count(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA='lms_university' AND TABLE_NAME='assignments' AND COLUMN_NAME='instructor_id');
SET @sqlstmt := IF(@exist=0,'ALTER TABLE assignments ADD COLUMN instructor_id INT NULL AFTER created_by','SELECT ''Column instructor_id already exists'' as Info');
PREPARE stmt FROM @sqlstmt;
EXECUTE stmt;

SET @exist := (SELECT count(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA='lms_university' AND TABLE_NAME='assignments' AND COLUMN_NAME='status');
SET @sqlstmt := IF(@exist=0,'ALTER TABLE assignments ADD COLUMN status ENUM(''active'', ''inactive'', ''archived'') DEFAULT ''active'' AFTER instructor_id','SELECT ''Column status already exists'' as Info');
PREPARE stmt FROM @sqlstmt;
EXECUTE stmt;

SET @exist := (SELECT count(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA='lms_university' AND TABLE_NAME='assignments' AND COLUMN_NAME='updated_at');
SET @sqlstmt := IF(@exist=0,'ALTER TABLE assignments ADD COLUMN updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER created_at','SELECT ''Column updated_at already exists'' as Info');
PREPARE stmt FROM @sqlstmt;
EXECUTE stmt;

-- Update enrollments table status enum
SET @exist := (SELECT count(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA='lms_university' AND TABLE_NAME='enrollments' AND COLUMN_NAME='status' AND COLUMN_TYPE LIKE '%pending%');
SET @sqlstmt := IF(@exist=0,'ALTER TABLE enrollments MODIFY COLUMN status ENUM(''enrolled'', ''pending'', ''suspended'', ''completed'', ''dropped'') DEFAULT ''enrolled''','SELECT ''Enrollments status enum already updated'' as Info');
PREPARE stmt FROM @sqlstmt;
EXECUTE stmt;

-- Add missing columns to assignment_submissions table
SET @exist := (SELECT count(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA='lms_university' AND TABLE_NAME='assignment_submissions' AND COLUMN_NAME='status');
SET @sqlstmt := IF(@exist=0,'ALTER TABLE assignment_submissions ADD COLUMN status ENUM(''submitted'', ''graded'', ''returned'') DEFAULT ''submitted'' AFTER file_path','SELECT ''Column status already exists'' as Info');
PREPARE stmt FROM @sqlstmt;
EXECUTE stmt;

-- Update messages table
SET @exist := (SELECT count(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA='lms_university' AND TABLE_NAME='messages' AND COLUMN_NAME='message_content');
SET @sqlstmt := IF(@exist=0,'ALTER TABLE messages ADD COLUMN message_content TEXT NULL AFTER content','SELECT ''Column message_content already exists'' as Info');
PREPARE stmt FROM @sqlstmt;
EXECUTE stmt;

SET @exist := (SELECT count(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA='lms_university' AND TABLE_NAME='messages' AND COLUMN_NAME='status');
SET @sqlstmt := IF(@exist=0,'ALTER TABLE messages ADD COLUMN status ENUM(''unread'', ''read'') DEFAULT ''unread'' AFTER message_content','SELECT ''Column status already exists'' as Info');
PREPARE stmt FROM @sqlstmt;
EXECUTE stmt;

-- Add student_id column to users table
SET @exist := (SELECT count(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA='lms_university' AND TABLE_NAME='users' AND COLUMN_NAME='student_id');
SET @sqlstmt := IF(@exist=0,'ALTER TABLE users ADD COLUMN student_id VARCHAR(20) UNIQUE NULL AFTER role','SELECT ''Column student_id already exists'' as Info');
PREPARE stmt FROM @sqlstmt;
EXECUTE stmt;

SELECT 'Database schema update completed successfully!' as Result;
