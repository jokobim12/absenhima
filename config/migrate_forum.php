<?php
/**
 * Migrasi untuk fitur Forum Diskusi Event
 */
require_once "koneksi.php";

echo "<!DOCTYPE html><html><head><title>Migrasi Forum</title>";
echo "<script src='https://cdn.tailwindcss.com'></script></head>";
echo "<body class='bg-gray-100 p-8'>";
echo "<div class='max-w-2xl mx-auto bg-white rounded-xl shadow-lg p-6'>";
echo "<h2 class='text-2xl font-bold mb-4'>Migrasi Forum Diskusi</h2>";
echo "<pre class='bg-gray-900 text-green-400 p-4 rounded-lg text-sm overflow-x-auto'>";

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
    return mysqli_num_rows($result) > 0;
}

function tableExists($conn, $table) {
    $result = mysqli_query($conn, "SHOW TABLES LIKE '$table'");
    return mysqli_num_rows($result) > 0;
}

echo "=== MIGRASI FORUM DISKUSI ===\n\n";

// Tambah kolom deskripsi ke events
if (!columnExists($conn, 'events', 'deskripsi')) {
    runQuery($conn, "ALTER TABLE events ADD COLUMN deskripsi TEXT DEFAULT NULL AFTER nama_event", "Tambah kolom deskripsi ke events");
}

// Tambah kolom lokasi ke events
if (!columnExists($conn, 'events', 'lokasi')) {
    runQuery($conn, "ALTER TABLE events ADD COLUMN lokasi VARCHAR(255) DEFAULT NULL AFTER deskripsi", "Tambah kolom lokasi ke events");
}

// Tambah kolom waktu_mulai ke events
if (!columnExists($conn, 'events', 'waktu_mulai')) {
    runQuery($conn, "ALTER TABLE events ADD COLUMN waktu_mulai DATETIME DEFAULT NULL AFTER lokasi", "Tambah kolom waktu_mulai ke events");
}

// Buat tabel event_messages untuk forum diskusi
if (!tableExists($conn, 'event_messages')) {
    $sql = "CREATE TABLE event_messages (
        id INT AUTO_INCREMENT PRIMARY KEY,
        event_id INT NOT NULL,
        user_id INT NOT NULL,
        message TEXT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_event_id (event_id),
        INDEX idx_created_at (created_at),
        FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    runQuery($conn, $sql, "Buat tabel event_messages");
}

echo "\n==========================================\n";
echo "Migrasi selesai!\n";
echo "==========================================\n";

echo "</pre>";
echo "<div class='mt-4 p-4 bg-green-100 text-green-700 rounded-lg'>";
echo "✅ Migrasi selesai! Fitur forum diskusi siap digunakan.";
echo "</div>";
echo "<div class='mt-6'>";
echo "<a href='../admin/events.php' class='inline-block px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700'>Ke Kelola Event</a> ";
echo "<a href='../index.php' class='inline-block px-4 py-2 bg-gray-600 text-white rounded-lg hover:bg-gray-700'>Kembali ke Halaman Utama</a>";
echo "</div>";
echo "</div></body></html>";
?>
