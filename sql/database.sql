-- Create database
CREATE DATABASE IF NOT EXISTS ptm_portal CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE ptm_portal;

-- Users table (extends existing parent portal)
CREATE TABLE users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    email VARCHAR(255) UNIQUE NOT NULL,
    name VARCHAR(255) NOT NULL,
    role ENUM('admin', 'teacher', 'parent') NOT NULL,
    google_id VARCHAR(255) UNIQUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Teachers table
CREATE TABLE teachers (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    subject VARCHAR(100),
    grade_level VARCHAR(50),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Parents table
CREATE TABLE parents (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    phone VARCHAR(20),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Students table
CREATE TABLE students (
    id INT PRIMARY KEY AUTO_INCREMENT,
    parent_id INT NOT NULL,
    name VARCHAR(255) NOT NULL,
    grade VARCHAR(20),
    class VARCHAR(50),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (parent_id) REFERENCES parents(id) ON DELETE CASCADE
);

-- Teacher availability table
CREATE TABLE availability (
    id INT PRIMARY KEY AUTO_INCREMENT,
    teacher_id INT NOT NULL,
    day_of_week ENUM('monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'),
    start_time TIME NOT NULL,
    end_time TIME NOT NULL,
    is_available BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (teacher_id) REFERENCES teachers(id) ON DELETE CASCADE
);

-- Meetings table
CREATE TABLE meetings (
    id INT PRIMARY KEY AUTO_INCREMENT,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    teacher_id INT NOT NULL,
    parent_id INT NOT NULL,
    student_id INT,
    meeting_date DATE NOT NULL,
    start_time TIME NOT NULL,
    end_time TIME NOT NULL,
    duration INT NOT NULL, -- in minutes
    status ENUM('scheduled', 'completed', 'cancelled', 'rescheduled') DEFAULT 'scheduled',
    google_meet_link VARCHAR(500),
    google_event_id VARCHAR(255),
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (teacher_id) REFERENCES teachers(id),
    FOREIGN KEY (parent_id) REFERENCES parents(id),
    FOREIGN KEY (student_id) REFERENCES students(id)
);

-- Create indexes for better performance
CREATE INDEX idx_meetings_teacher_date ON meetings(teacher_id, meeting_date);
CREATE INDEX idx_meetings_parent_date ON meetings(parent_id, meeting_date);
CREATE INDEX idx_meetings_status ON meetings(status);