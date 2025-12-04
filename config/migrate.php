<?php
/**
 * Script Migrasi Database untuk SSO & Fix Struktur
 * Jalankan: http://localhost/absenhima/config/migrate.php
 */

require_once "koneksi.php";

echo "<!DOCTYPE html><html><head><title>Migrasi Database</title>";
echo "<script src='https://cdn.tailwindcss.com'></script></head>";
echo "<body class='bg-gray-100 p-8'>";
echo "<div class='max-w-2xl mx-auto bg-white rounded-xl shadow-lg p-6'>";
echo "<h2 class='text-2xl font-bold mb-4'>Migrasi Database Absensi HIMA</h2>";
echo "<pre class='bg-gray-900 text-green-400 p-4 rounded-lg text-sm overflow-x-auto'>";

$success = 0;
$errors = 0;

// Fungsi helper
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

function columnExists($conn, $table, $column) {
    $result = mysqli_query($conn, "SHOW COLUMNS FROM $table LIKE '$column'");
    return mysqli_num_rows($result) > 0;
}

echo "=== MIGRASI TABEL USERS ===\n\n";

// Tambah kolom ke tabel users
if (!columnExists($conn, 'users', 'email')) {
    runQuery($conn, "ALTER TABLE users ADD COLUMN email VARCHAR(100) DEFAULT NULL AFTER nim", "Tambah kolom email");
}
if (!columnExists($conn, 'users', 'google_id')) {
    runQuery($conn, "ALTER TABLE users ADD COLUMN google_id VARCHAR(100) DEFAULT NULL AFTER password", "Tambah kolom google_id");
}
if (!columnExists($conn, 'users', 'picture')) {
    runQuery($conn, "ALTER TABLE users ADD COLUMN picture VARCHAR(500) DEFAULT NULL AFTER google_id", "Tambah kolom picture");
}

// Modify kolom users
runQuery($conn, "ALTER TABLE users MODIFY COLUMN username VARCHAR(50) DEFAULT NULL", "Modify username (nullable)");
runQuery($conn, "ALTER TABLE users MODIFY COLUMN password VARCHAR(255) DEFAULT ''", "Modify password (default empty)");
runQuery($conn, "ALTER TABLE users MODIFY COLUMN kelas VARCHAR(20) NOT NULL DEFAULT '-'", "Modify kelas (default -)");
runQuery($conn, "ALTER TABLE users MODIFY COLUMN semester VARCHAR(10) NOT NULL DEFAULT '1'", "Modify semester (default 1)");
runQuery($conn, "ALTER TABLE users MODIFY COLUMN nim VARCHAR(50) NOT NULL", "Modify nim (lebih panjang)");

echo "\n=== MIGRASI TABEL ABSEN ===\n\n";

// Tambah created_at ke tabel absen jika belum ada
if (!columnExists($conn, 'absen', 'created_at')) {
    runQuery($conn, "ALTER TABLE absen ADD COLUMN created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP", "Tambah kolom created_at ke absen");
}

echo "\n=== TAMBAH UNIQUE KEYS ===\n\n";

// Cek dan tambah unique keys
$checkEmail = mysqli_query($conn, "SHOW INDEX FROM users WHERE Key_name = 'unique_email'");
if (mysqli_num_rows($checkEmail) == 0) {
    runQuery($conn, "ALTER TABLE users ADD UNIQUE KEY unique_email (email)", "Tambah unique key email");
}

$checkGoogle = mysqli_query($conn, "SHOW INDEX FROM users WHERE Key_name = 'unique_google'");
if (mysqli_num_rows($checkGoogle) == 0) {
    runQuery($conn, "ALTER TABLE users ADD UNIQUE KEY unique_google (google_id)", "Tambah unique key google_id");
}

echo "\n==========================================\n";
echo "HASIL: $success berhasil, $errors error\n";
echo "==========================================\n";

echo "</pre>";

if ($errors == 0) {
    echo "<div class='mt-4 p-4 bg-green-100 text-green-700 rounded-lg'>";
    echo "✅ Migrasi selesai! Database siap digunakan.";
    echo "</div>";
} else {
    echo "<div class='mt-4 p-4 bg-red-100 text-red-700 rounded-lg'>";
    echo "⚠️ Ada beberapa error, periksa kembali.";
    echo "</div>";
}

// Tampilkan struktur tabel
echo "<h3 class='text-lg font-bold mt-6 mb-2'>Struktur Tabel Users:</h3>";
echo "<div class='overflow-x-auto'><table class='w-full text-sm border'>";
echo "<tr class='bg-gray-100'><th class='border p-2'>Field</th><th class='border p-2'>Type</th><th class='border p-2'>Null</th><th class='border p-2'>Default</th></tr>";
$result = mysqli_query($conn, "DESCRIBE users");
while ($row = mysqli_fetch_assoc($result)) {
    echo "<tr><td class='border p-2'>{$row['Field']}</td><td class='border p-2'>{$row['Type']}</td><td class='border p-2'>{$row['Null']}</td><td class='border p-2'>" . ($row['Default'] ?? 'NULL') . "</td></tr>";
}
echo "</table></div>";

echo "<h3 class='text-lg font-bold mt-6 mb-2'>Struktur Tabel Absen:</h3>";
echo "<div class='overflow-x-auto'><table class='w-full text-sm border'>";
echo "<tr class='bg-gray-100'><th class='border p-2'>Field</th><th class='border p-2'>Type</th><th class='border p-2'>Null</th><th class='border p-2'>Default</th></tr>";
$result = mysqli_query($conn, "DESCRIBE absen");
while ($row = mysqli_fetch_assoc($result)) {
    echo "<tr><td class='border p-2'>{$row['Field']}</td><td class='border p-2'>{$row['Type']}</td><td class='border p-2'>{$row['Null']}</td><td class='border p-2'>" . ($row['Default'] ?? 'NULL') . "</td></tr>";
}
echo "</table></div>";

echo "<div class='mt-6'><a href='../index.php' class='inline-block px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700'>Kembali ke Halaman Utama</a></div>";
echo "</div></body></html>";
?>
