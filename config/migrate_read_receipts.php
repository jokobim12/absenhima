<?php
/**
 * Migrasi untuk fitur Read Receipts (centang baca seperti WhatsApp)
 */
require_once "koneksi.php";

echo "<!DOCTYPE html><html><head><title>Migrasi Read Receipts</title>";
echo "<script src='https://cdn.tailwindcss.com'></script></head>";
echo "<body class='bg-gray-100 p-8'>";
echo "<div class='max-w-2xl mx-auto bg-white rounded-xl shadow-lg p-6'>";
echo "<h2 class='text-2xl font-bold mb-4'>Migrasi Read Receipts</h2>";
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

function tableExists($conn, $table) {
    $result = mysqli_query($conn, "SHOW TABLES LIKE '$table'");
    return mysqli_num_rows($result) > 0;
}

echo "=== MIGRASI READ RECEIPTS ===\n\n";

// Buat tabel message_reads untuk tracking siapa yang sudah baca
if (!tableExists($conn, 'message_reads')) {
    $sql = "CREATE TABLE message_reads (
        id INT AUTO_INCREMENT PRIMARY KEY,
        message_id INT NOT NULL,
        user_id INT NOT NULL,
        read_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY unique_read (message_id, user_id),
        INDEX idx_message_id (message_id),
        INDEX idx_user_id (user_id),
        FOREIGN KEY (message_id) REFERENCES forum_messages(id) ON DELETE CASCADE,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    runQuery($conn, $sql, "Buat tabel message_reads");
} else {
    echo "→ Tabel message_reads... <span class='text-yellow-400'>SKIP (sudah ada)</span>\n";
}

echo "\n==========================================\n";
echo "Migrasi selesai!\n";
echo "==========================================\n";

echo "</pre>";
echo "<div class='mt-4 p-4 bg-green-100 text-green-700 rounded-lg'>";
echo "✅ Migrasi selesai! Fitur read receipts siap digunakan.";
echo "</div>";
echo "<div class='mt-6'>";
echo "<a href='../user/dashboard.php' class='inline-block px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700'>Ke Dashboard</a> ";
echo "<a href='../index.php' class='inline-block px-4 py-2 bg-gray-600 text-white rounded-lg hover:bg-gray-700'>Kembali ke Halaman Utama</a>";
echo "</div>";
echo "</div></body></html>";
?>
