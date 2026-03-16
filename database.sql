-- ============================================================
-- TALENTA DATABASE SCHEMA
-- ============================================================

CREATE DATABASE IF NOT EXISTS talenta_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE talenta_db;

-- ============================================================
-- CLASSES
-- ============================================================
CREATE TABLE IF NOT EXISTS classes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ============================================================
-- USERS
-- ============================================================
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    username VARCHAR(80) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    role ENUM('admin','guru','instruktur','siswa') NOT NULL DEFAULT 'siswa',
    photo VARCHAR(255) DEFAULT NULL,
    class_id INT DEFAULT NULL,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (class_id) REFERENCES classes(id) ON DELETE SET NULL
);

-- ============================================================
-- ATTENDANCE TOKENS
-- ============================================================
CREATE TABLE IF NOT EXISTS attendance_tokens (
    id INT AUTO_INCREMENT PRIMARY KEY,
    token VARCHAR(10) NOT NULL UNIQUE,
    generated_by INT NOT NULL,
    class_id INT DEFAULT NULL,
    valid_date DATE NOT NULL,
    expired_at DATETIME NOT NULL,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (generated_by) REFERENCES users(id),
    FOREIGN KEY (class_id) REFERENCES classes(id) ON DELETE SET NULL
);

-- ============================================================
-- ATTENDANCE RECORDS
-- ============================================================
CREATE TABLE IF NOT EXISTS attendance_records (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    token_id INT NOT NULL,
    attended_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    status ENUM('hadir','izin','alpha') DEFAULT 'hadir',
    FOREIGN KEY (student_id) REFERENCES users(id),
    FOREIGN KEY (token_id) REFERENCES attendance_tokens(id)
);

-- ============================================================
-- JOURNALS
-- ============================================================
CREATE TABLE IF NOT EXISTS journals (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    attendance_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    content TEXT NOT NULL,
    task_file VARCHAR(255) DEFAULT NULL,
    task_submitted TINYINT(1) DEFAULT 0,
    reviewed_by INT DEFAULT NULL,
    review_note TEXT,
    status ENUM('pending','reviewed','approved','revision') DEFAULT 'pending',
    submitted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    reviewed_at TIMESTAMP NULL,
    FOREIGN KEY (student_id) REFERENCES users(id),
    FOREIGN KEY (attendance_id) REFERENCES attendance_records(id),
    FOREIGN KEY (reviewed_by) REFERENCES users(id) ON DELETE SET NULL
);

-- ============================================================
-- LEAVE PERMISSIONS
-- ============================================================
CREATE TABLE IF NOT EXISTS leave_permissions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    attendance_id INT NOT NULL,
    approved_by INT NOT NULL,
    approved_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    reason TEXT,
    FOREIGN KEY (student_id) REFERENCES users(id),
    FOREIGN KEY (attendance_id) REFERENCES attendance_records(id),
    FOREIGN KEY (approved_by) REFERENCES users(id)
);

-- ============================================================
-- QUIZZES
-- ============================================================
CREATE TABLE IF NOT EXISTS quizzes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    type ENUM('harian','bulanan') NOT NULL DEFAULT 'harian',
    created_by INT NOT NULL,
    class_id INT DEFAULT NULL,
    start_time DATETIME NOT NULL,
    end_time DATETIME NOT NULL,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(id),
    FOREIGN KEY (class_id) REFERENCES classes(id) ON DELETE SET NULL
);

-- ============================================================
-- QUIZ QUESTIONS
-- ============================================================
CREATE TABLE IF NOT EXISTS quiz_questions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    quiz_id INT NOT NULL,
    question TEXT NOT NULL,
    option_a VARCHAR(500) NOT NULL,
    option_b VARCHAR(500) NOT NULL,
    option_c VARCHAR(500) NOT NULL,
    option_d VARCHAR(500) NOT NULL,
    correct_answer ENUM('a','b','c','d') NOT NULL,
    FOREIGN KEY (quiz_id) REFERENCES quizzes(id) ON DELETE CASCADE
);

-- ============================================================
-- QUIZ ANSWERS
-- ============================================================
CREATE TABLE IF NOT EXISTS quiz_answers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    quiz_id INT NOT NULL,
    student_id INT NOT NULL,
    answers JSON,
    score DECIMAL(5,2) DEFAULT 0,
    submitted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (quiz_id) REFERENCES quizzes(id),
    FOREIGN KEY (student_id) REFERENCES users(id)
);

-- ============================================================
-- JOURNAL MEDIA (foto & video bukti jurnal)
-- ============================================================
CREATE TABLE IF NOT EXISTS journal_media (
    id INT AUTO_INCREMENT PRIMARY KEY,
    journal_id INT NOT NULL,
    file_name VARCHAR(255) NOT NULL,
    file_type ENUM('photo','video') NOT NULL,
    original_name VARCHAR(255),
    uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (journal_id) REFERENCES journals(id) ON DELETE CASCADE
);

-- ============================================================
-- STUDENT QUIZ ACCESS (untuk aktivasi ulang individual)
-- ============================================================
CREATE TABLE IF NOT EXISTS quiz_access (
    id INT AUTO_INCREMENT PRIMARY KEY,
    quiz_id INT NOT NULL,
    student_id INT NOT NULL,
    granted_by INT NOT NULL,
    granted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    is_used TINYINT(1) DEFAULT 0,
    FOREIGN KEY (quiz_id) REFERENCES quizzes(id),
    FOREIGN KEY (student_id) REFERENCES users(id),
    FOREIGN KEY (granted_by) REFERENCES users(id)
);

-- ============================================================
-- ANNOUNCEMENTS (Pengumuman)
-- ============================================================
CREATE TABLE IF NOT EXISTS announcements (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    content TEXT NOT NULL,
    author_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (author_id) REFERENCES users(id)
);

-- ============================================================
-- LEARNING MATERIALS (Modul Materi)
-- ============================================================
CREATE TABLE IF NOT EXISTS materials (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    file_name VARCHAR(255),
    file_url VARCHAR(255),
    author_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (author_id) REFERENCES users(id)
);

-- ============================================================
-- SEED DATA
-- ============================================================

-- Default Classes
INSERT INTO classes (name, description) VALUES
('Kelas XI RPL 1', 'Rekayasa Perangkat Lunak Kelas 1'),
('Kelas XI RPL 2', 'Rekayasa Perangkat Lunak Kelas 2'),
('Kelas XII RPL 1', 'Rekayasa Perangkat Lunak Kelas Akhir');

-- Default Admin (password: admin123)
INSERT INTO users (name, username, password, role, is_active) VALUES
('Administrator', 'admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin', 1),
('Budi Santoso', 'guru1', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'guru', 1),
('Andi Instruktur', 'instruktur1', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'instruktur', 1),
('Deni Siswa', 'siswa1', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'siswa', 1),
('Rini Siswa', 'siswa2', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'siswa', 1);

-- Assign students to class
UPDATE users SET class_id = 1 WHERE username IN ('siswa1','siswa2','instruktur1');
UPDATE users SET class_id = 1 WHERE username = 'guru1';

-- NOTE: Default password for ALL accounts is: password
-- The hash above is for 'password' using bcrypt
