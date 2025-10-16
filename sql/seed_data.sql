USE ptm_portal;

-- Insert sample users
INSERT INTO users (email, name, role) VALUES
('admin@school.edu', 'School Administrator', 'admin'),
('john.math@school.edu', 'John Smith', 'teacher'),
('sara.science@school.edu', 'Sara Johnson', 'teacher'),
('mike.english@school.edu', 'Mike Brown', 'teacher'),
('parent.david@email.com', 'David Wilson', 'parent'),
('parent.sarah@email.com', 'Sarah Miller', 'parent');

-- Insert teachers
INSERT INTO teachers (user_id, subject, grade_level) VALUES
(2, 'Mathematics', 'Grade 10'),
(3, 'Science', 'Grade 9'),
(4, 'English', 'Grade 11');

-- Insert parents
INSERT INTO parents (user_id, phone) VALUES
(5, '+1234567890'),
(6, '+0987654321');

-- Insert students
INSERT INTO students (parent_id, name, grade, class) VALUES
(1, 'Emma Wilson', 'Grade 10', '10A'),
(1, 'Noah Wilson', 'Grade 9', '9B'),
(2, 'Olivia Miller', 'Grade 11', '11A');

-- Insert teacher availability
INSERT INTO availability (teacher_id, day_of_week, start_time, end_time) VALUES
(1, 'monday', '14:00:00', '16:00:00'),
(1, 'wednesday', '14:00:00', '16:00:00'),
(2, 'tuesday', '15:00:00', '17:00:00'),
(2, 'thursday', '15:00:00', '17:00:00'),
(3, 'monday', '13:00:00', '15:00:00'),
(3, 'friday', '14:00:00', '16:00:00');