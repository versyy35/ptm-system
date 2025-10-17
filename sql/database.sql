-- ============================================================================
-- PTM Portal - Refactored Database Schema
-- Optimized for simple queries with denormalized names
-- ============================================================================

CREATE DATABASE IF NOT EXISTS ptm_portal CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE ptm_portal;

-- ============================================================================
-- CORE TABLES
-- ============================================================================

-- Users table (authentication and base info)
CREATE TABLE users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    email VARCHAR(255) UNIQUE,
    name VARCHAR(255) NOT NULL,
    role ENUM('admin', 'teacher', 'parent') NOT NULL,
    google_id VARCHAR(255) UNIQUE,
    msp_user_id VARCHAR(255) UNIQUE,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    INDEX idx_users_email (email),
    INDEX idx_users_role (role),
    INDEX idx_users_msp_id (msp_user_id),
    INDEX idx_users_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Teachers table
CREATE TABLE teachers (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    name VARCHAR(255) NOT NULL,              -- DENORMALIZED for performance
    email VARCHAR(255),                       -- DENORMALIZED for convenience
    isams_staff_id INT UNIQUE,                -- ISAMS sync identifier
    subject VARCHAR(100),
    grade_level VARCHAR(50),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_teachers_user_id (user_id),
    INDEX idx_teachers_isams_id (isams_staff_id),
    INDEX idx_teachers_name (name),
    INDEX idx_teachers_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Parents table
CREATE TABLE parents (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    name VARCHAR(255) NOT NULL,              -- DENORMALIZED for performance
    email VARCHAR(255),                       -- DENORMALIZED for convenience
    phone VARCHAR(20),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_parents_user_id (user_id),
    INDEX idx_parents_name (name),
    INDEX idx_parents_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Students table
CREATE TABLE students (
    id INT PRIMARY KEY AUTO_INCREMENT,
    parent_id INT,                            -- Nullable (students may not have parent in system)
    isams_pupil_id INT UNIQUE,                -- ISAMS sync identifier
    name VARCHAR(255) NOT NULL,
    grade VARCHAR(20),
    class VARCHAR(50),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (parent_id) REFERENCES parents(id) ON DELETE CASCADE,
    INDEX idx_students_parent_id (parent_id),
    INDEX idx_students_isams_id (isams_pupil_id),
    INDEX idx_students_name (name),
    INDEX idx_students_grade (grade),
    INDEX idx_students_class (class)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- ISAMS SYNC TABLES
-- ============================================================================

-- Subjects table (from ISAMS)
CREATE TABLE subjects (
    id INT PRIMARY KEY AUTO_INCREMENT,
    isams_subject_id INT UNIQUE,              -- ISAMS sync identifier
    name VARCHAR(255),
    code VARCHAR(50),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    INDEX idx_subjects_isams_id (isams_subject_id),
    INDEX idx_subjects_code (code),
    INDEX idx_subjects_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Teaching Sets table (classes from ISAMS)
CREATE TABLE teaching_sets (
    id INT PRIMARY KEY AUTO_INCREMENT,
    isams_set_id INT UNIQUE,                  -- ISAMS sync identifier
    subject_id INT,
    subject_name VARCHAR(255),                -- DENORMALIZED for performance
    teacher_id INT,
    teacher_name VARCHAR(255),                -- DENORMALIZED for performance
    year_group VARCHAR(50),
    set_code VARCHAR(100),
    set_name VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (subject_id) REFERENCES subjects(id),
    FOREIGN KEY (teacher_id) REFERENCES teachers(id),
    INDEX idx_teaching_sets_isams_id (isams_set_id),
    INDEX idx_teaching_sets_subject_id (subject_id),
    INDEX idx_teaching_sets_teacher_id (teacher_id),
    INDEX idx_teaching_sets_year_group (year_group),
    INDEX idx_teaching_sets_code (set_code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Enrollments table (student enrollment in teaching sets)
CREATE TABLE enrollments (
    id INT PRIMARY KEY AUTO_INCREMENT,
    student_id INT,
    teaching_set_id INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (student_id) REFERENCES students(id),
    FOREIGN KEY (teaching_set_id) REFERENCES teaching_sets(id),
    UNIQUE KEY unique_enrollment (student_id, teaching_set_id),
    INDEX idx_enrollments_student_id (student_id),
    INDEX idx_enrollments_teaching_set_id (teaching_set_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- MEETING TABLES
-- ============================================================================

-- Teacher availability table
CREATE TABLE availability (
    id INT PRIMARY KEY AUTO_INCREMENT,
    teacher_id INT NOT NULL,
    day_of_week ENUM('monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'),
    start_time TIME NOT NULL,
    end_time TIME NOT NULL,
    is_available TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (teacher_id) REFERENCES teachers(id) ON DELETE CASCADE,
    INDEX idx_availability_teacher_day (teacher_id, day_of_week),
    INDEX idx_availability_teacher_id (teacher_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Meetings table
CREATE TABLE meetings (
    id INT PRIMARY KEY AUTO_INCREMENT,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    teacher_id INT NOT NULL,
    teacher_name VARCHAR(255),                -- DENORMALIZED for performance
    teacher_email VARCHAR(255),               -- DENORMALIZED for notifications
    parent_id INT NOT NULL,
    parent_name VARCHAR(255),                 -- DENORMALIZED for performance
    parent_email VARCHAR(255),                -- DENORMALIZED for notifications
    student_id INT,
    student_name VARCHAR(255),                -- DENORMALIZED for performance
    meeting_date DATE NOT NULL,
    start_time TIME NOT NULL,
    end_time TIME NOT NULL,
    duration INT NOT NULL,                    -- in minutes
    status ENUM('scheduled', 'completed', 'cancelled', 'rescheduled') DEFAULT 'scheduled',
    google_meet_link VARCHAR(500),
    google_event_id VARCHAR(255),
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (teacher_id) REFERENCES teachers(id),
    FOREIGN KEY (parent_id) REFERENCES parents(id),
    FOREIGN KEY (student_id) REFERENCES students(id),
    INDEX idx_meetings_teacher_date (teacher_id, meeting_date),
    INDEX idx_meetings_parent_date (parent_id, meeting_date),
    INDEX idx_meetings_student_id (student_id),
    INDEX idx_meetings_status (status),
    INDEX idx_meetings_date (meeting_date),
    INDEX idx_meetings_date_time (meeting_date, start_time)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- TRIGGERS TO MAINTAIN DENORMALIZED DATA
-- ============================================================================

DELIMITER //

-- Sync teacher name changes to teachers table
CREATE TRIGGER sync_teacher_name_from_user
AFTER UPDATE ON users
FOR EACH ROW
BEGIN
    IF NEW.name != OLD.name AND NEW.role = 'teacher' THEN
        UPDATE teachers SET name = NEW.name, email = NEW.email WHERE user_id = NEW.id;
    END IF;
END//

-- Sync parent name changes to parents table
CREATE TRIGGER sync_parent_name_from_user
AFTER UPDATE ON users
FOR EACH ROW
BEGIN
    IF NEW.name != OLD.name AND NEW.role = 'parent' THEN
        UPDATE parents SET name = NEW.name, email = NEW.email WHERE user_id = NEW.id;
    END IF;
END//

-- Sync teacher changes to teaching_sets
CREATE TRIGGER sync_teacher_to_teaching_sets
AFTER UPDATE ON teachers
FOR EACH ROW
BEGIN
    IF NEW.name != OLD.name OR NEW.email != OLD.email THEN
        UPDATE teaching_sets 
        SET teacher_name = NEW.name 
        WHERE teacher_id = NEW.id;
    END IF;
END//

-- Sync subject changes to teaching_sets
CREATE TRIGGER sync_subject_to_teaching_sets
AFTER UPDATE ON subjects
FOR EACH ROW
BEGIN
    IF NEW.name != OLD.name THEN
        UPDATE teaching_sets 
        SET subject_name = NEW.name 
        WHERE subject_id = NEW.id;
    END IF;
END//

-- Sync teacher changes to meetings
CREATE TRIGGER sync_teacher_to_meetings
AFTER UPDATE ON teachers
FOR EACH ROW
BEGIN
    IF NEW.name != OLD.name OR NEW.email != OLD.email THEN
        UPDATE meetings 
        SET teacher_name = NEW.name, teacher_email = NEW.email 
        WHERE teacher_id = NEW.id;
    END IF;
END//

-- Sync parent changes to meetings
CREATE TRIGGER sync_parent_to_meetings
AFTER UPDATE ON parents
FOR EACH ROW
BEGIN
    IF NEW.name != OLD.name OR NEW.email != OLD.email THEN
        UPDATE meetings 
        SET parent_name = NEW.name, parent_email = NEW.email 
        WHERE parent_id = NEW.id;
    END IF;
END//

-- Sync student changes to meetings
CREATE TRIGGER sync_student_to_meetings
AFTER UPDATE ON students
FOR EACH ROW
BEGIN
    IF NEW.name != OLD.name THEN
        UPDATE meetings 
        SET student_name = NEW.name 
        WHERE student_id = NEW.id;
    END IF;
END//

DELIMITER ;

-- ============================================================================
-- VIEWS FOR COMMON QUERIES
-- ============================================================================

-- View: Teacher schedule (no joins needed in application!)
CREATE VIEW v_teacher_schedule AS
SELECT 
    m.id,
    m.title,
    m.description,
    m.meeting_date,
    m.start_time,
    m.end_time,
    m.duration,
    m.status,
    m.teacher_id,
    m.teacher_name,
    m.teacher_email,
    m.parent_name,
    m.parent_email,
    m.student_name,
    m.google_meet_link,
    m.notes
FROM meetings m
WHERE m.status IN ('scheduled', 'rescheduled')
ORDER BY m.meeting_date, m.start_time;

-- View: Parent schedule
CREATE VIEW v_parent_schedule AS
SELECT 
    m.id,
    m.title,
    m.meeting_date,
    m.start_time,
    m.end_time,
    m.status,
    m.parent_id,
    m.parent_name,
    m.teacher_name,
    m.teacher_email,
    m.student_name,
    m.google_meet_link
FROM meetings m
WHERE m.status IN ('scheduled', 'rescheduled')
ORDER BY m.meeting_date, m.start_time;

-- View: Teaching set roster with minimal joins
CREATE VIEW v_teaching_set_roster AS
SELECT 
    ts.id as teaching_set_id,
    ts.set_name,
    ts.set_code,
    ts.year_group,
    ts.teacher_name,
    ts.subject_name,
    s.id as student_id,
    s.name as student_name,
    s.grade,
    s.class
FROM teaching_sets ts
LEFT JOIN enrollments e ON e.teaching_set_id = ts.id
LEFT JOIN students s ON s.id = e.student_id
ORDER BY ts.set_name, s.name;

-- View: Parent's children with their classes
CREATE VIEW v_parent_children_classes AS
SELECT 
    p.id as parent_id,
    p.name as parent_name,
    p.email as parent_email,
    s.id as student_id,
    s.name as student_name,
    s.grade,
    s.class,
    ts.set_name,
    ts.teacher_name,
    ts.subject_name
FROM parents p
JOIN students s ON s.parent_id = p.id
LEFT JOIN enrollments e ON e.student_id = s.id
LEFT JOIN teaching_sets ts ON ts.id = e.teaching_set_id
ORDER BY p.name, s.name, ts.set_name;

-- View: All teachers with contact info
CREATE VIEW v_teachers_directory AS
SELECT 
    t.id,
    t.name,
    t.email,
    t.isams_staff_id,
    t.subject,
    t.grade_level,
    u.is_active
FROM teachers t
JOIN users u ON u.id = t.user_id
ORDER BY t.name;

-- ============================================================================
-- EXAMPLE QUERIES (Copy these to your PHP files!)
-- ============================================================================

/*
-- Get all meetings for a teacher (NO JOINS!)
SELECT * FROM meetings 
WHERE teacher_id = 1 
AND meeting_date >= CURDATE()
ORDER BY meeting_date, start_time;

-- Get all teaching sets for a teacher (NO JOINS!)
SELECT * FROM teaching_sets 
WHERE teacher_id = 3
ORDER BY set_name;

-- Get parent's upcoming meetings (NO JOINS!)
SELECT teacher_name, student_name, meeting_date, start_time, google_meet_link
FROM meetings
WHERE parent_id = 10 
AND meeting_date >= CURDATE()
AND status = 'scheduled'
ORDER BY meeting_date, start_time;

-- Get all students in a teaching set (SINGLE JOIN!)
SELECT s.name as student_name, s.grade, s.class
FROM enrollments e
JOIN students s ON s.id = e.student_id
WHERE e.teaching_set_id = 5
ORDER BY s.name;

-- Get parent's children and their classes (USE VIEW!)
SELECT * FROM v_parent_children_classes
WHERE parent_id = 10;

-- Get teacher's full schedule for next week (USE VIEW!)
SELECT * FROM v_teacher_schedule
WHERE teacher_id = 2
AND meeting_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY);
*/