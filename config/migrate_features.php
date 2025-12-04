<?php
/**
 * Migration untuk fitur-fitur baru:
 * 1. Pengumuman (announcements)
 * 2. Izin/Sakit (permissions)
 * 3. Lokasi Event (event location for GPS)
 */

include_once "koneksi.php";

function migrateFeatures($conn) {
    $results = [];
    
    // 1. Tabel Pengumuman
    $sql = "CREATE TABLE IF NOT EXISTS announcements (
        id INT AUTO_INCREMENT PRIMARY KEY,
        title VARCHAR(255) NOT NULL,
        content TEXT NOT NULL,
        type ENUM('info', 'warning', 'success', 'danger') DEFAULT 'info',
        is_pinned TINYINT(1) DEFAULT 0,
        is_active TINYINT(1) DEFAULT 1,
        created_by INT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        expires_at DATETIME DEFAULT NULL,
        INDEX idx_active (is_active),
        INDEX idx_pinned (is_pinned)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    
    if (mysqli_query($conn, $sql)) {
        $results[] = "✓ Tabel announcements dibuat";
    } else {
        $results[] = "✗ Error announcements: " . mysqli_error($conn);
    }
    
    // 2. Tabel Izin/Sakit
    $sql = "CREATE TABLE IF NOT EXISTS permissions (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        event_id INT DEFAULT NULL,
        type ENUM('izin', 'sakit') NOT NULL,
        reason TEXT NOT NULL,
        attachment VARCHAR(500) DEFAULT NULL,
        status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
        admin_note TEXT DEFAULT NULL,
        reviewed_by INT DEFAULT NULL,
        reviewed_at DATETIME DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_user (user_id),
        INDEX idx_event (event_id),
        INDEX idx_status (status),
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    
    if (mysqli_query($conn, $sql)) {
        $results[] = "✓ Tabel permissions dibuat";
    } else {
        $results[] = "✗ Error permissions: " . mysqli_error($conn);
    }
    
    // 3. Tambah kolom lokasi ke events
    $check = mysqli_query($conn, "SHOW COLUMNS FROM events LIKE 'latitude'");
    if (mysqli_num_rows($check) == 0) {
        mysqli_query($conn, "ALTER TABLE events ADD COLUMN latitude DECIMAL(10, 8) DEFAULT NULL");
        mysqli_query($conn, "ALTER TABLE events ADD COLUMN longitude DECIMAL(11, 8) DEFAULT NULL");
        mysqli_query($conn, "ALTER TABLE events ADD COLUMN radius INT DEFAULT 100 COMMENT 'Radius dalam meter'");
        mysqli_query($conn, "ALTER TABLE events ADD COLUMN require_location TINYINT(1) DEFAULT 0");
        $results[] = "✓ Kolom lokasi ditambahkan ke events";
    } else {
        $results[] = "○ Kolom lokasi sudah ada";
    }
    
    // 4. Tambah kolom lokasi ke absen
    $check = mysqli_query($conn, "SHOW COLUMNS FROM absen LIKE 'latitude'");
    if (mysqli_num_rows($check) == 0) {
        mysqli_query($conn, "ALTER TABLE absen ADD COLUMN latitude DECIMAL(10, 8) DEFAULT NULL");
        mysqli_query($conn, "ALTER TABLE absen ADD COLUMN longitude DECIMAL(11, 8) DEFAULT NULL");
        mysqli_query($conn, "ALTER TABLE absen ADD COLUMN location_verified TINYINT(1) DEFAULT NULL");
        $results[] = "✓ Kolom lokasi ditambahkan ke absen";
    } else {
        $results[] = "○ Kolom lokasi absen sudah ada";
    }
    
    // 5. Tabel untuk read announcements (tracking siapa sudah baca)
    $sql = "CREATE TABLE IF NOT EXISTS announcement_reads (
        id INT AUTO_INCREMENT PRIMARY KEY,
        announcement_id INT NOT NULL,
        user_id INT NOT NULL,
        read_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY unique_read (announcement_id, user_id),
        FOREIGN KEY (announcement_id) REFERENCES announcements(id) ON DELETE CASCADE,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    
    if (mysqli_query($conn, $sql)) {
        $results[] = "✓ Tabel announcement_reads dibuat";
    } else {
        $results[] = "✗ Error announcement_reads: " . mysqli_error($conn);
    }
    
    return $results;
}

// Auto-run jika diakses langsung
if (basename($_SERVER['PHP_SELF']) == 'migrate_features.php') {
    $results = migrateFeatures($conn);
    echo "<pre>";
    foreach ($results as $r) {
        echo $r . "\n";
    }
    echo "</pre>";
}
?>
