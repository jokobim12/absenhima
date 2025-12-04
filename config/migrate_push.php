<?php
/**
 * Script Migrasi Database untuk Push Notifications
 * Jalankan: http://localhost/absenhima/config/migrate_push.php
 */

require_once "koneksi.php";

echo "<!DOCTYPE html><html><head><title>Migrasi Push Notifications</title>";
echo "<script src='https://cdn.tailwindcss.com'></script></head>";
echo "<body class='bg-gray-100 p-8'>";
echo "<div class='max-w-2xl mx-auto bg-white rounded-xl shadow-lg p-6'>";
echo "<h2 class='text-2xl font-bold mb-4'>Migrasi Push Notifications</h2>";
echo "<pre class='bg-gray-900 text-green-400 p-4 rounded-lg text-sm overflow-x-auto'>";

$success = 0;
$errors = 0;

function runQuery($conn, $sql, $name) {
    global $success, $errors;
    echo "→ $name... ";
    if (mysqli_query($conn, $sql)) {
        echo "<span class='text-green-400'>OK</span>\n";
        $success++;
        return true;
    } else {
        $error = mysqli_error($conn);
        if (strpos($error, 'Duplicate') !== false || strpos($error, 'already exists') !== false) {
            echo "<span class='text-yellow-400'>SKIP (sudah ada)</span>\n";
        } else {
            echo "<span class='text-red-400'>ERROR: $error</span>\n";
            $errors++;
        }
        return false;
    }
}

function tableExists($conn, $table) {
    $result = mysqli_query($conn, "SHOW TABLES LIKE '$table'");
    return mysqli_num_rows($result) > 0;
}

echo "=== MIGRASI PUSH NOTIFICATIONS ===\n\n";

// Buat tabel push_subscriptions
if (!tableExists($conn, 'push_subscriptions')) {
    $sql = "CREATE TABLE push_subscriptions (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        endpoint TEXT NOT NULL,
        p256dh VARCHAR(255) NOT NULL,
        auth VARCHAR(255) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        UNIQUE KEY unique_endpoint (user_id, endpoint(255))
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    runQuery($conn, $sql, "Buat tabel push_subscriptions");
} else {
    echo "→ Tabel push_subscriptions... <span class='text-yellow-400'>SKIP (sudah ada)</span>\n";
}

// Buat tabel untuk menyimpan VAPID keys
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

echo "\n==========================================\n";
echo "HASIL: $success berhasil, $errors error\n";
echo "==========================================\n";

echo "</pre>";

if ($errors == 0) {
    echo "<div class='mt-4 p-4 bg-green-100 text-green-700 rounded-lg'>";
    echo "✅ Migrasi selesai! Tabel push notifications siap digunakan.";
    echo "</div>";
} else {
    echo "<div class='mt-4 p-4 bg-red-100 text-red-700 rounded-lg'>";
    echo "⚠️ Ada beberapa error, periksa kembali.";
    echo "</div>";
}

echo "<div class='mt-6'>";
echo "<a href='../admin/settings.php' class='inline-block px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700'>Ke Settings Admin</a> ";
echo "<a href='../index.php' class='inline-block px-4 py-2 bg-gray-600 text-white rounded-lg hover:bg-gray-700'>Kembali ke Halaman Utama</a>";
echo "</div>";
echo "</div></body></html>";
?>
