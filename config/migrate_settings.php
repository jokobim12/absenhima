<?php
/**
 * Migration script untuk membuat tabel settings
 */

require_once "koneksi.php";

echo "=== Migrasi Database Settings ===\n\n";

// Buat tabel settings
$sql = "CREATE TABLE IF NOT EXISTS settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(100) NOT NULL UNIQUE,
    setting_value TEXT,
    setting_type ENUM('text', 'textarea', 'color', 'image', 'select') DEFAULT 'text',
    setting_group VARCHAR(50) DEFAULT 'general',
    setting_label VARCHAR(100),
    setting_options TEXT,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
)";

if (mysqli_query($conn, $sql)) {
    echo "✓ Tabel 'settings' berhasil dibuat\n";
} else {
    echo "✗ Gagal membuat tabel: " . mysqli_error($conn) . "\n";
}

// Insert default settings
$default_settings = [
    // Branding
    ['site_name', 'HIMA Politala', 'text', 'branding', 'Nama Website'],
    ['site_tagline', 'Sistem Absensi Digital HIMA Politala', 'text', 'branding', 'Tagline'],
    ['site_logo', '', 'image', 'branding', 'Logo Website'],
    ['site_favicon', '', 'image', 'branding', 'Favicon'],
    
    // Colors
    ['color_primary', '#1e293b', 'color', 'colors', 'Warna Primary'],
    ['color_secondary', '#3b82f6', 'color', 'colors', 'Warna Secondary'],
    ['color_accent', '#10b981', 'color', 'colors', 'Warna Accent'],
    ['color_background', '#f8fafc', 'color', 'colors', 'Warna Background'],
    
    // Homepage Content
    ['hero_title', 'Sistem Absensi Digital', 'text', 'homepage', 'Judul Hero'],
    ['hero_subtitle', 'HIMA Politala', 'text', 'homepage', 'Subjudul Hero'],
    ['hero_description', 'Absensi modern dengan QR Code dinamis. Cepat, aman, dan efisien untuk setiap kegiatan HIMA.', 'textarea', 'homepage', 'Deskripsi Hero'],
    ['hero_image', '', 'image', 'homepage', 'Gambar Hero'],
    
    // Features
    ['feature_1_title', 'QR Code Dinamis', 'text', 'homepage', 'Fitur 1 - Judul'],
    ['feature_1_desc', 'QR berubah setiap 5 detik untuk keamanan maksimal', 'textarea', 'homepage', 'Fitur 1 - Deskripsi'],
    ['feature_2_title', 'Login dengan Google', 'text', 'homepage', 'Fitur 2 - Judul'],
    ['feature_2_desc', 'Gunakan akun Politala untuk login otomatis', 'textarea', 'homepage', 'Fitur 2 - Deskripsi'],
    ['feature_3_title', 'Realtime & Akurat', 'text', 'homepage', 'Fitur 3 - Judul'],
    ['feature_3_desc', 'Data absensi tercatat secara realtime dan akurat', 'textarea', 'homepage', 'Fitur 3 - Deskripsi'],
    
    // Footer
    ['footer_text', '© 2025 HIMA Politala. All rights reserved.', 'text', 'footer', 'Teks Footer'],
    ['contact_email', 'hima@politala.ac.id', 'text', 'footer', 'Email Kontak'],
    ['contact_instagram', '@himapolitala', 'text', 'footer', 'Instagram'],
];

$stmt = mysqli_prepare($conn, "INSERT IGNORE INTO settings (setting_key, setting_value, setting_type, setting_group, setting_label) VALUES (?, ?, ?, ?, ?)");

foreach ($default_settings as $setting) {
    mysqli_stmt_bind_param($stmt, "sssss", $setting[0], $setting[1], $setting[2], $setting[3], $setting[4]);
    mysqli_stmt_execute($stmt);
}

echo "✓ Default settings berhasil ditambahkan\n";

echo "\n=== Migrasi Selesai ===\n";
?>
