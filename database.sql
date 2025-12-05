-- =============================================
-- Database Schema untuk Sistem Absensi HIMA
-- Dengan dukungan Google SSO
-- =============================================

CREATE DATABASE IF NOT EXISTS absenhima;
USE absenhima;

-- Tabel Admin
CREATE TABLE IF NOT EXISTS admin (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Tabel Users (Mahasiswa) - dengan SSO fields
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nama VARCHAR(100) NOT NULL,
    nim VARCHAR(20) NOT NULL,
    email VARCHAR(100) DEFAULT NULL,
    kelas VARCHAR(20) NOT NULL DEFAULT '-',
    semester VARCHAR(10) NOT NULL DEFAULT '1',
    username VARCHAR(50) DEFAULT NULL,
    password VARCHAR(255) DEFAULT '',
    google_id VARCHAR(100) DEFAULT NULL,
    picture VARCHAR(500) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_nim (nim),
    UNIQUE KEY unique_email (email),
    UNIQUE KEY unique_google (google_id)
);

-- Tabel Events
CREATE TABLE IF NOT EXISTS events (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nama_event VARCHAR(200) NOT NULL,
    status ENUM('open', 'closed') DEFAULT 'closed',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Tabel Tokens (QR Dinamis, 1 token = 1 orang)
CREATE TABLE IF NOT EXISTS tokens (
    id INT AUTO_INCREMENT PRIMARY KEY,
    event_id INT NOT NULL,
    token VARCHAR(64) NOT NULL,
    expired_at DATETIME NOT NULL,
    is_used TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE
);

-- Tabel Absensi
CREATE TABLE IF NOT EXISTS absen (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    event_id INT NOT NULL,
    token_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE,
    FOREIGN KEY (token_id) REFERENCES tokens(id) ON DELETE CASCADE,
    UNIQUE KEY unique_absen (user_id, event_id)
);

-- =============================================
-- Insert Admin Default (password: admin123)
-- =============================================
INSERT INTO admin (username, password) VALUES 
('admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi');

-- =============================================
-- Migration: Jika tabel users sudah ada, jalankan ini
-- untuk menambahkan kolom SSO
-- =============================================
-- ALTER TABLE users ADD COLUMN email VARCHAR(100) DEFAULT NULL AFTER nim;
-- ALTER TABLE users ADD COLUMN google_id VARCHAR(100) DEFAULT NULL AFTER password;
-- ALTER TABLE users ADD COLUMN picture VARCHAR(500) DEFAULT NULL AFTER google_id;
-- ALTER TABLE users ADD UNIQUE KEY unique_email (email);
-- ALTER TABLE users ADD UNIQUE KEY unique_google (google_id);
-- ALTER TABLE users MODIFY COLUMN username VARCHAR(50) DEFAULT NULL;
-- ALTER TABLE users MODIFY COLUMN password VARCHAR(255) DEFAULT '';
-- ALTER TABLE users MODIFY COLUMN kelas VARCHAR(20) NOT NULL DEFAULT '-';
-- ALTER TABLE users MODIFY COLUMN semester VARCHAR(10) NOT NULL DEFAULT '1';
