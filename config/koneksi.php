<?php
/**
 * Database Connection
 * Credentials diambil dari environment variable atau database settings
 */

// Cek environment variable dulu, fallback ke default untuk development
$host = getenv('DB_HOST') ?: 'localhost';
$user = getenv('DB_USER') ?: 'root';
$pass = getenv('DB_PASS') !== false ? getenv('DB_PASS') : '1234'; // fallback untuk dev
$db   = getenv('DB_NAME') ?: 'absenhima';

$conn = mysqli_connect($host, $user, $pass, $db);

if (!$conn) {
    // Jangan tampilkan detail error di production
    if (getenv('APP_ENV') === 'production') {
        die("Database connection failed. Please contact administrator.");
    } else {
        die("Koneksi gagal: " . mysqli_connect_error());
    }
}

// Set charset untuk keamanan
mysqli_set_charset($conn, "utf8mb4");
?>
