<?php
/**
 * Migration script untuk menambahkan pengaturan hero image
 */

require_once "koneksi.php";

echo "=== Migrasi Pengaturan Hero Image ===\n\n";

// Hero image settings
$settings = [
    ['hero_image_size', '100', 'text', 'homepage', 'Ukuran Gambar Hero (%)'],
    ['hero_image_fit', 'contain', 'select', 'homepage', 'Mode Tampilan'],
    ['hero_image_position', 'center', 'select', 'homepage', 'Posisi Gambar'],
];

$stmt = mysqli_prepare($conn, "INSERT IGNORE INTO settings (setting_key, setting_value, setting_type, setting_group, setting_label) VALUES (?, ?, ?, ?, ?)");

$count = 0;
foreach ($settings as $setting) {
    mysqli_stmt_bind_param($stmt, "sssss", $setting[0], $setting[1], $setting[2], $setting[3], $setting[4]);
    if (mysqli_stmt_execute($stmt)) {
        $count++;
    }
}

echo "✓ $count pengaturan hero image ditambahkan\n";

echo "\n=== Migrasi Selesai ===\n";
?>
