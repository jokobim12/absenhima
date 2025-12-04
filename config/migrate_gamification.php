<?php
/**
 * Migration untuk fitur gamifikasi:
 * 1. Badges/Achievements
 * 2. User badges (relasi)
 * 3. Streak tracking
 */

include_once "koneksi.php";

function migrateGamification($conn) {
    $results = [];
    
    // 1. Tabel Badges
    $sql = "CREATE TABLE IF NOT EXISTS badges (
        id INT AUTO_INCREMENT PRIMARY KEY,
        code VARCHAR(50) NOT NULL UNIQUE,
        name VARCHAR(100) NOT NULL,
        description VARCHAR(255),
        icon VARCHAR(50) DEFAULT '🏆',
        color VARCHAR(20) DEFAULT 'yellow',
        requirement_type ENUM('attendance_count', 'streak_days', 'event_type', 'special') NOT NULL,
        requirement_value INT DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    
    if (mysqli_query($conn, $sql)) {
        $results[] = "✓ Tabel badges dibuat";
        
        // Insert default badges
        $badges = [
            ['first_attendance', 'Pemula', 'Hadir di event pertama', '🎯', 'blue', 'attendance_count', 1],
            ['attendance_5', 'Rajin', 'Hadir di 5 event', '⭐', 'yellow', 'attendance_count', 5],
            ['attendance_10', 'Konsisten', 'Hadir di 10 event', '🌟', 'yellow', 'attendance_count', 10],
            ['attendance_25', 'Dedikasi', 'Hadir di 25 event', '💫', 'purple', 'attendance_count', 25],
            ['attendance_50', 'Legendaris', 'Hadir di 50 event', '👑', 'orange', 'attendance_count', 50],
            ['streak_3', 'On Fire', '3 hari berturut-turut hadir', '🔥', 'red', 'streak_days', 3],
            ['streak_7', 'Seminggu Penuh', '7 hari berturut-turut hadir', '💪', 'green', 'streak_days', 7],
            ['streak_14', 'Unstoppable', '14 hari berturut-turut hadir', '🚀', 'blue', 'streak_days', 14],
            ['streak_30', 'Master', '30 hari berturut-turut hadir', '🏅', 'gold', 'streak_days', 30],
            ['early_bird', 'Early Bird', 'Hadir pertama di sebuah event', '🐦', 'cyan', 'special', 0],
        ];
        
        $stmt = mysqli_prepare($conn, "INSERT IGNORE INTO badges (code, name, description, icon, color, requirement_type, requirement_value) VALUES (?, ?, ?, ?, ?, ?, ?)");
        foreach ($badges as $b) {
            mysqli_stmt_bind_param($stmt, "ssssssi", $b[0], $b[1], $b[2], $b[3], $b[4], $b[5], $b[6]);
            mysqli_stmt_execute($stmt);
        }
        mysqli_stmt_close($stmt);
        $results[] = "✓ Default badges ditambahkan";
    }
    
    // 2. Tabel User Badges
    $sql = "CREATE TABLE IF NOT EXISTS user_badges (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        badge_id INT NOT NULL,
        earned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY unique_user_badge (user_id, badge_id),
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        FOREIGN KEY (badge_id) REFERENCES badges(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    
    if (mysqli_query($conn, $sql)) {
        $results[] = "✓ Tabel user_badges dibuat";
    }
    
    // 3. Tambah kolom streak ke users
    $check = mysqli_query($conn, "SHOW COLUMNS FROM users LIKE 'current_streak'");
    if (mysqli_num_rows($check) == 0) {
        mysqli_query($conn, "ALTER TABLE users ADD COLUMN current_streak INT DEFAULT 0");
        mysqli_query($conn, "ALTER TABLE users ADD COLUMN longest_streak INT DEFAULT 0");
        mysqli_query($conn, "ALTER TABLE users ADD COLUMN last_attendance_date DATE DEFAULT NULL");
        $results[] = "✓ Kolom streak ditambahkan ke users";
    } else {
        $results[] = "○ Kolom streak sudah ada";
    }
    
    return $results;
}

// Auto-run
if (basename($_SERVER['PHP_SELF']) == 'migrate_gamification.php') {
    $results = migrateGamification($conn);
    echo "<pre>";
    foreach ($results as $r) echo $r . "\n";
    echo "</pre>";
}
?>
