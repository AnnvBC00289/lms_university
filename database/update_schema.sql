-- Update database schema for LMS University
-- Add missing columns to existing tables

USE lms_university;

-- Add missing columns to courses table
ALTER TABLE courses 
ADD COLUMN start_date DATE NULL AFTER max_students,
ADD COLUMN end_date DATE NULL AFTER start_date,
ADD COLUMN updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER created_at;

-- Update status enum to include 'archived'
ALTER TABLE courses 
MODIFY COLUMN status ENUM('active', 'inactive', 'archived') DEFAULT 'active';

-- Add missing columns to assignments table
ALTER TABLE assignments 
ADD COLUMN instructions TEXT NULL AFTER description,
ADD COLUMN assignment_type ENUM('project', 'homework', 'essay', 'lab', 'presentation', 'quiz', 'other') DEFAULT 'homework' AFTER instructions,
ADD COLUMN allow_late_submission BOOLEAN DEFAULT FALSE AFTER assignment_type,
ADD COLUMN late_penalty DECIMAL(5,2) DEFAULT 0.00 AFTER allow_late_submission,
ADD COLUMN instructor_id INT NULL AFTER created_by,
ADD COLUMN status ENUM('active', 'inactive', 'archived') DEFAULT 'active' AFTER instructor_id,
ADD COLUMN updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER created_at;

-- Add foreign key constraint for instructor_id in assignments
ALTER TABLE assignments 
ADD CONSTRAINT fk_assignments_instructor 
FOREIGN KEY (instructor_id) REFERENCES users(id) ON DELETE CASCADE;

-- Update enrollments table status enum
ALTER TABLE enrollments 
MODIFY COLUMN status ENUM('enrolled', 'pending', 'suspended', 'completed', 'dropped') DEFAULT 'enrolled';

-- Add missing columns to assignment_submissions table
ALTER TABLE assignment_submissions 
ADD COLUMN status ENUM('submitted', 'graded', 'returned') DEFAULT 'submitted' AFTER file_path;

-- Update messages table to match the messages.php structure
ALTER TABLE messages 
ADD COLUMN message_content TEXT NULL AFTER content,
ADD COLUMN status ENUM('unread', 'read') DEFAULT 'unread' AFTER message_content;

-- Update the existing content column name to be backward compatible
UPDATE messages SET message_content = content WHERE message_content IS NULL;

-- Add student_id column to users table for student identification
ALTER TABLE users 
ADD COLUMN student_id VARCHAR(20) UNIQUE NULL AFTER role;

-- Create indexes for better performance
CREATE INDEX idx_courses_instructor ON courses(instructor_id);
CREATE INDEX idx_courses_status ON courses(status);
CREATE INDEX idx_assignments_course ON assignments(course_id);
CREATE INDEX idx_assignments_instructor ON assignments(instructor_id);
CREATE INDEX idx_enrollments_student ON enrollments(student_id);
CREATE INDEX idx_enrollments_course ON enrollments(course_id);
CREATE INDEX idx_enrollments_status ON enrollments(status);
CREATE INDEX idx_messages_sender ON messages(sender_id);
CREATE INDEX idx_messages_recipient ON messages(recipient_id);
CREATE INDEX idx_messages_status ON messages(status);

-- Insert some sample data for testing
INSERT INTO courses (title, description, instructor_id, course_code, credits, semester, year, max_students, start_date, end_date, status) VALUES
('Introduction to Web Development', 'Learn HTML, CSS, JavaScript and modern web development techniques', 2, 'CS101', 3, 'Fall', 2024, 30, '2024-09-01', '2024-12-15', 'active'),
('Database Management Systems', 'Comprehensive course covering SQL, database design, and management', 2, 'CS201', 4, 'Fall', 2024, 25, '2024-09-01', '2024-12-15', 'active'),
('Software Engineering', 'Learn software development lifecycle, project management, and best practices', 2, 'CS301', 3, 'Spring', 2024, 20, '2024-01-15', '2024-05-15', 'active');

-- Update existing assignments with instructor_id
UPDATE assignments SET instructor_id = 2 WHERE instructor_id IS NULL;

-- Insert some sample assignments
INSERT INTO assignments (course_id, title, description, instructions, assignment_type, due_date, max_points, allow_late_submission, late_penalty, created_by, instructor_id, status) VALUES
(1, 'HTML Portfolio Project', 'Create a personal portfolio website using HTML and CSS', 'Build a responsive portfolio site with at least 5 pages: Home, About, Portfolio, Contact, and Resume. Use modern CSS techniques and ensure mobile responsiveness.', 'project', '2024-10-15 23:59:59', 100.00, 1, 10.00, 2, 2, 'active'),
(1, 'JavaScript Calculator', 'Build a functional calculator using JavaScript', 'Create a calculator that can perform basic arithmetic operations. Include error handling and a clean user interface.', 'homework', '2024-11-01 23:59:59', 80.00, 1, 5.00, 2, 2, 'active'),
(2, 'Database Design Project', 'Design and implement a database for a library management system', 'Create an ERD, normalize tables, write SQL queries, and implement the database with sample data.', 'project', '2024-11-20 23:59:59', 120.00, 0, 0.00, 2, 2, 'active');
