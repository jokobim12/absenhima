<?php
/**
 * MIGRASI DATABASE LENGKAP
 * Jalankan file ini sekali setelah deploy untuk setup semua tabel
 * URL: /config/migrate_all.php
 */

require_once "koneksi.php";

echo "<!DOCTYPE html><html><head><title>Migrasi Database</title>";
echo "<script src='https://cdn.tailwindcss.com'></script></head>";
echo "<body class='bg-gray-100 p-8'>";
echo "<div class='max-w-3xl mx-auto bg-white rounded-xl shadow-lg p-6'>";
echo "<h2 class='text-2xl font-bold mb-4'>Migrasi Database Lengkap</h2>";
echo "<pre class='bg-gray-900 text-green-400 p-4 rounded-lg text-sm overflow-x-auto max-h-96'>";

function runQuery($conn, $sql, $name) {
    echo "→ $name... ";
    if (mysqli_query($conn, $sql)) {
        echo "<span class='text-green-400'>OK</span>\n";
        return true;
    } else {
        $error = mysqli_error($conn);
        if (strpos($error, 'Duplicate') !== false || strpos($error, 'already exists') !== false) {
            echo "<span class='text-yellow-400'>SKIP (sudah ada)</span>\n";
        } else {
            echo "<span class='text-red-400'>ERROR: $error</span>\n";
        }
        return false;
    }
}

function columnExists($conn, $table, $column) {
    $result = mysqli_query($conn, "SHOW COLUMNS FROM $table LIKE '$column'");
    return $result && mysqli_num_rows($result) > 0;
}

function tableExists($conn, $table) {
    $result = mysqli_query($conn, "SHOW TABLES LIKE '$table'");
    return $result && mysqli_num_rows($result) > 0;
}

echo "=== SETUP TABEL UTAMA ===\n\n";

// Users table modifications
if (!columnExists($conn, 'users', 'google_id')) {
    runQuery($conn, "ALTER TABLE users ADD COLUMN google_id VARCHAR(100) DEFAULT NULL", "Tambah kolom google_id");
}
if (!columnExists($conn, 'users', 'picture')) {
    runQuery($conn, "ALTER TABLE users ADD COLUMN picture VARCHAR(500) DEFAULT NULL", "Tambah kolom picture");
}
if (!columnExists($conn, 'users', 'email')) {
    runQuery($conn, "ALTER TABLE users ADD COLUMN email VARCHAR(100) DEFAULT NULL AFTER nim", "Tambah kolom email");
}
if (!columnExists($conn, 'users', 'current_streak')) {
    runQuery($conn, "ALTER TABLE users ADD COLUMN current_streak INT DEFAULT 0", "Tambah kolom current_streak");
}
if (!columnExists($conn, 'users', 'longest_streak')) {
    runQuery($conn, "ALTER TABLE users ADD COLUMN longest_streak INT DEFAULT 0", "Tambah kolom longest_streak");
}
if (!columnExists($conn, 'users', 'last_attendance_date')) {
    runQuery($conn, "ALTER TABLE users ADD COLUMN last_attendance_date DATE DEFAULT NULL", "Tambah kolom last_attendance_date");
}

echo "\n=== TABEL PERMISSIONS (Izin/Sakit) ===\n\n";

if (!tableExists($conn, 'permissions')) {
    $sql = "CREATE TABLE permissions (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        event_id INT DEFAULT NULL,
        type ENUM('izin', 'sakit') NOT NULL,
        reason TEXT NOT NULL,
        attachment VARCHAR(255) DEFAULT NULL,
        status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
        admin_notes TEXT DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_user_id (user_id),
        INDEX idx_status (status)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    runQuery($conn, $sql, "Buat tabel permissions");
} else {
    echo "→ Tabel permissions... <span class='text-yellow-400'>SKIP (sudah ada)</span>\n";
}

echo "\n=== TABEL ANNOUNCEMENTS (Pengumuman) ===\n\n";

if (!tableExists($conn, 'announcements')) {
    $sql = "CREATE TABLE announcements (
        id INT AUTO_INCREMENT PRIMARY KEY,
        title VARCHAR(255) NOT NULL,
        content TEXT NOT NULL,
        type ENUM('info', 'warning', 'success', 'danger') DEFAULT 'info',
        is_pinned TINYINT(1) DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    runQuery($conn, $sql, "Buat tabel announcements");
} else {
    echo "→ Tabel announcements... <span class='text-yellow-400'>SKIP (sudah ada)</span>\n";
}

echo "\n=== TABEL BADGES (Gamification) ===\n\n";

if (!tableExists($conn, 'badges')) {
    $sql = "CREATE TABLE badges (
        id INT AUTO_INCREMENT PRIMARY KEY,
        code VARCHAR(50) NOT NULL UNIQUE,
        name VARCHAR(100) NOT NULL,
        description TEXT,
        icon VARCHAR(100) DEFAULT '🏆',
        requirement_type ENUM('attendance_count', 'streak_days', 'special') NOT NULL,
        requirement_value INT DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    runQuery($conn, $sql, "Buat tabel badges");
    
    // Insert default badges
    $badges = [
        ['first_step', 'Langkah Pertama', 'Hadir di event pertama', '🎯', 'attendance_count', 1],
        ['regular', 'Anggota Aktif', 'Hadir di 5 event', '⭐', 'attendance_count', 5],
        ['dedicated', 'Dedikasi Tinggi', 'Hadir di 10 event', '🌟', 'attendance_count', 10],
        ['veteran', 'Veteran', 'Hadir di 25 event', '🏅', 'attendance_count', 25],
        ['legend', 'Legendaris', 'Hadir di 50 event', '👑', 'attendance_count', 50],
        ['streak_3', 'On Fire', 'Streak 3 hari berturut-turut', '🔥', 'streak_days', 3],
        ['streak_7', 'Konsisten', 'Streak 7 hari berturut-turut', '💪', 'streak_days', 7],
        ['streak_14', 'Tak Terbendung', 'Streak 14 hari berturut-turut', '🚀', 'streak_days', 14],
        ['early_bird', 'Early Bird', 'Jadi yang pertama hadir di event', '🐦', 'special', 0],
    ];
    foreach ($badges as $b) {
        mysqli_query($conn, "INSERT IGNORE INTO badges (code, name, description, icon, requirement_type, requirement_value) 
            VALUES ('{$b[0]}', '{$b[1]}', '{$b[2]}', '{$b[3]}', '{$b[4]}', {$b[5]})");
    }
    echo "→ Insert default badges... <span class='text-green-400'>OK</span>\n";
} else {
    echo "→ Tabel badges... <span class='text-yellow-400'>SKIP (sudah ada)</span>\n";
}

if (!tableExists($conn, 'user_badges')) {
    $sql = "CREATE TABLE user_badges (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        badge_id INT NOT NULL,
        earned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY unique_user_badge (user_id, badge_id),
        INDEX idx_user_id (user_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    runQuery($conn, $sql, "Buat tabel user_badges");
} else {
    echo "→ Tabel user_badges... <span class='text-yellow-400'>SKIP (sudah ada)</span>\n";
}

echo "\n=== TABEL FORUM ===\n\n";

if (!tableExists($conn, 'forum_messages')) {
    $sql = "CREATE TABLE forum_messages (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        message TEXT NOT NULL,
        image_url VARCHAR(255) DEFAULT NULL,
        reply_to INT DEFAULT NULL,
        is_deleted TINYINT(1) DEFAULT 0,
        is_edited TINYINT(1) DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_user_id (user_id),
        INDEX idx_created_at (created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    runQuery($conn, $sql, "Buat tabel forum_messages");
} else {
    echo "→ Tabel forum_messages... <span class='text-yellow-400'>SKIP (sudah ada)</span>\n";
    if (!columnExists($conn, 'forum_messages', 'image_url')) {
        runQuery($conn, "ALTER TABLE forum_messages ADD COLUMN image_url VARCHAR(255) DEFAULT NULL AFTER message", "Tambah kolom image_url");
    }
    if (!columnExists($conn, 'forum_messages', 'is_deleted')) {
        runQuery($conn, "ALTER TABLE forum_messages ADD COLUMN is_deleted TINYINT(1) DEFAULT 0", "Tambah kolom is_deleted");
    }
    if (!columnExists($conn, 'forum_messages', 'is_edited')) {
        runQuery($conn, "ALTER TABLE forum_messages ADD COLUMN is_edited TINYINT(1) DEFAULT 0", "Tambah kolom is_edited");
    }
}

echo "\n=== TABEL PUSH NOTIFICATIONS ===\n\n";

if (!tableExists($conn, 'push_subscriptions')) {
    $sql = "CREATE TABLE push_subscriptions (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        endpoint TEXT NOT NULL,
        p256dh VARCHAR(255) NOT NULL,
        auth VARCHAR(255) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_user_id (user_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    runQuery($conn, $sql, "Buat tabel push_subscriptions");
} else {
    echo "→ Tabel push_subscriptions... <span class='text-yellow-400'>SKIP (sudah ada)</span>\n";
}

if (!tableExists($conn, 'vapid_keys')) {
    $sql = "CREATE TABLE vapid_keys (
        id INT AUTO_INCREMENT PRIMARY KEY,
        public_key TEXT NOT NULL,
        private_key TEXT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    runQuery($conn, $sql, "Buat tabel vapid_keys");
} else {
    echo "→ Tabel vapid_keys... <span class='text-yellow-400'>SKIP (sudah ada)</span>\n";
}

echo "\n=== TABEL SETTINGS ===\n\n";

if (!tableExists($conn, 'settings')) {
    $sql = "CREATE TABLE settings (
        id INT AUTO_INCREMENT PRIMARY KEY,
        setting_key VARCHAR(100) NOT NULL UNIQUE,
        setting_value TEXT,
        setting_group VARCHAR(50) DEFAULT 'general',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    runQuery($conn, $sql, "Buat tabel settings");
    
    // Default settings
    mysqli_query($conn, "INSERT IGNORE INTO settings (setting_key, setting_value, setting_group) VALUES 
        ('app_name', 'SADHATI', 'general'),
        ('app_tagline', 'Sistem Absensi Digital', 'general'),
        ('primary_color', '#1e293b', 'appearance')
    ");
    echo "→ Insert default settings... <span class='text-green-400'>OK</span>\n";
} else {
    echo "→ Tabel settings... <span class='text-yellow-400'>SKIP (sudah ada)</span>\n";
}

echo "\n=== TABEL TRANSLATIONS ===\n\n";

if (!tableExists($conn, 'translations')) {
    $sql = "CREATE TABLE translations (
        id INT AUTO_INCREMENT PRIMARY KEY,
        lang VARCHAR(5) NOT NULL,
        trans_key VARCHAR(100) NOT NULL,
        trans_value TEXT,
        UNIQUE KEY unique_trans (lang, trans_key)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    runQuery($conn, $sql, "Buat tabel translations");
} else {
    echo "→ Tabel translations... <span class='text-yellow-400'>SKIP (sudah ada)</span>\n";
}

echo "\n=== SETUP FOLDER UPLOADS ===\n\n";

$folders = ['uploads/forum', 'uploads/profiles', 'uploads/settings', 'uploads/permissions'];
foreach ($folders as $folder) {
    $path = dirname(__DIR__) . '/' . $folder;
    if (!is_dir($path)) {
        if (mkdir($path, 0755, true)) {
            echo "→ Buat folder $folder... <span class='text-green-400'>OK</span>\n";
        } else {
            echo "→ Buat folder $folder... <span class='text-red-400'>GAGAL</span>\n";
        }
    } else {
        echo "→ Folder $folder... <span class='text-yellow-400'>SKIP (sudah ada)</span>\n";
    }
}

echo "\n==========================================\n";
echo "<span class='text-green-400 font-bold'>MIGRASI SELESAI!</span>\n";
echo "==========================================\n";

echo "</pre>";
echo "<div class='mt-4 p-4 bg-green-100 text-green-700 rounded-lg'>";
echo "✅ Database siap digunakan!";
echo "</div>";
echo "<div class='mt-6 flex gap-3'>";
echo "<a href='../admin/index.php' class='inline-block px-4 py-2 bg-gray-900 text-white rounded-lg hover:bg-gray-800'>Ke Admin Panel</a>";
echo "<a href='../index.php' class='inline-block px-4 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300'>Ke Homepage</a>";
echo "</div>";
echo "</div></body></html>";
?>
