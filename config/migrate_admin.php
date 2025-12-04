<?php
/**
 * Migration script untuk menambahkan kolom picture ke tabel admin
 */

require_once "koneksi.php";

echo "=== Migrasi Database Admin ===\n\n";

// Cek apakah kolom picture sudah ada
$result = mysqli_query($conn, "SHOW COLUMNS FROM admin LIKE 'picture'");

if (mysqli_num_rows($result) == 0) {
    // Tambahkan kolom picture
    $sql = "ALTER TABLE admin ADD COLUMN picture VARCHAR(500) DEFAULT NULL AFTER password";
    
    if (mysqli_query($conn, $sql)) {
        echo "✓ Kolom 'picture' berhasil ditambahkan ke tabel admin\n";
    } else {
        echo "✗ Gagal menambahkan kolom: " . mysqli_error($conn) . "\n";
    }
} else {
    echo "- Kolom 'picture' sudah ada\n";
}

echo "\n=== Migrasi Selesai ===\n";
?>
