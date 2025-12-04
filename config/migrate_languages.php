<?php
/**
 * Migration script untuk membuat tabel languages
 */

require_once "koneksi.php";

echo "=== Migrasi Database Languages ===\n\n";

// Buat tabel languages
$sql = "CREATE TABLE IF NOT EXISTS languages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    lang_key VARCHAR(100) NOT NULL,
    lang_code VARCHAR(10) NOT NULL,
    lang_value TEXT,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_lang (lang_key, lang_code)
)";

if (mysqli_query($conn, $sql)) {
    echo "✓ Tabel 'languages' berhasil dibuat\n";
} else {
    echo "✗ Gagal membuat tabel: " . mysqli_error($conn) . "\n";
}

// Tambah setting untuk default language
$check = mysqli_query($conn, "SELECT * FROM settings WHERE setting_key = 'default_language'");
if (mysqli_num_rows($check) == 0) {
    mysqli_query($conn, "INSERT INTO settings (setting_key, setting_value, setting_type, setting_group, setting_label) 
                         VALUES ('default_language', 'id', 'select', 'general', 'Bahasa Default')");
    echo "✓ Setting default_language ditambahkan\n";
}

// Default translations
$translations = [
    // Navbar & Header
    'welcome' => ['id' => 'Selamat datang,', 'bjn' => 'Salamat datang,', 'jv' => 'Sugeng rawuh,'],
    'semester' => ['id' => 'Semester', 'bjn' => 'Semester', 'jv' => 'Semester'],
    'attendance' => ['id' => 'Kehadiran', 'bjn' => 'Kahadiran', 'jv' => 'Kehadiran'],
    
    // Dashboard
    'active_event' => ['id' => 'Event Aktif', 'bjn' => 'Event Aktif', 'jv' => 'Event Aktif'],
    'no_active_event' => ['id' => 'Tidak Ada Event Aktif', 'bjn' => 'Kadada Event Aktif', 'jv' => 'Ora Ana Event Aktif'],
    'wait_admin' => ['id' => 'Silakan tunggu admin membuka event absensi.', 'bjn' => 'Tunggu pang admin mambuka event absensi.', 'jv' => 'Monggo nunggu admin mbukak event absensi.'],
    'scan_now' => ['id' => 'Scan QR Absen Sekarang', 'bjn' => 'Scan QR Absen Wayahini', 'jv' => 'Scan QR Absen Saiki'],
    
    // History
    'attendance_history' => ['id' => 'Riwayat Kehadiran', 'bjn' => 'Riwayat Kahadiran', 'jv' => 'Riwayat Kehadiran'],
    'total' => ['id' => 'total', 'bjn' => 'total', 'jv' => 'total'],
    'present' => ['id' => 'Hadir', 'bjn' => 'Hadir', 'jv' => 'Hadir'],
    'no_history' => ['id' => 'Belum ada riwayat kehadiran.', 'bjn' => 'Baluman ada riwayat kahadiran.', 'jv' => 'Durung ana riwayat kehadiran.'],
    
    // Stats
    'statistics' => ['id' => 'Statistik', 'bjn' => 'Statistik', 'jv' => 'Statistik'],
    'total_present' => ['id' => 'Total Hadir', 'bjn' => 'Total Hadir', 'jv' => 'Total Hadir'],
    
    // Menu
    'menu' => ['id' => 'Menu', 'bjn' => 'Menu', 'jv' => 'Menu'],
    'edit_profile' => ['id' => 'Edit Profil', 'bjn' => 'Edit Profil', 'jv' => 'Edit Profil'],
    'change_class' => ['id' => 'Ubah data kelas', 'bjn' => 'Ubah data kelas', 'jv' => 'Ganti data kelas'],
    'scan_qr' => ['id' => 'Scan QR', 'bjn' => 'Scan QR', 'jv' => 'Scan QR'],
    'attend_now' => ['id' => 'Absen sekarang', 'bjn' => 'Absen wayahini', 'jv' => 'Absen saiki'],
    
    // Logout
    'logout_confirm' => ['id' => 'Konfirmasi Logout', 'bjn' => 'Konfirmasi Logout', 'jv' => 'Konfirmasi Logout'],
    'logout_message' => ['id' => 'Apakah Anda yakin ingin keluar?', 'bjn' => 'Ikam yakin handak kaluar?', 'jv' => 'Sampeyan yakin arep metu?'],
    'cancel' => ['id' => 'Batal', 'bjn' => 'Batal', 'jv' => 'Batal'],
    'yes_logout' => ['id' => 'Ya, Logout', 'bjn' => 'Iih, Logout', 'jv' => 'Iya, Logout'],
    
    // Profile
    'complete_class' => ['id' => 'Lengkapi data kelas Anda', 'bjn' => 'Langkapi data kelas Pian', 'jv' => 'Jangkepana data kelas Sampeyan'],
    
    // Homepage
    'login_with_google' => ['id' => 'Masuk dengan Akun Politala', 'bjn' => 'Masuk lawan Akun Politala', 'jv' => 'Mlebu nganggo Akun Politala'],
    'use_politala_email' => ['id' => 'Gunakan email @politala.ac.id', 'bjn' => 'Pakai email @politala.ac.id', 'jv' => 'Nganggo email @politala.ac.id'],
];

$stmt = mysqli_prepare($conn, "INSERT IGNORE INTO languages (lang_key, lang_code, lang_value) VALUES (?, ?, ?)");

$count = 0;
foreach ($translations as $key => $langs) {
    foreach ($langs as $code => $value) {
        mysqli_stmt_bind_param($stmt, "sss", $key, $code, $value);
        mysqli_stmt_execute($stmt);
        $count++;
    }
}

echo "✓ $count terjemahan default ditambahkan\n";

echo "\n=== Migrasi Selesai ===\n";
?>
