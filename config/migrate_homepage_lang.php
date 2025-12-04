<?php
/**
 * Migration script untuk menambahkan terjemahan halaman beranda
 */

require_once "koneksi.php";

echo "=== Migrasi Terjemahan Halaman Beranda ===\n\n";

// Default translations for homepage
$translations = [
    'hero_title' => [
        'id' => 'Sistem Absensi Digital',
        'bjn' => 'Sistem Absensi Digital',
        'jv' => 'Sistem Absensi Digital'
    ],
    'hero_subtitle' => [
        'id' => 'HIMA Politala',
        'bjn' => 'HIMA Politala',
        'jv' => 'HIMA Politala'
    ],
    'hero_description' => [
        'id' => 'Absensi modern dengan QR Code dinamis yang selalu berubah. Cukup scan untuk mencatat kehadiran.',
        'bjn' => 'Absensi modern lawan QR Code dinamis nang salalu barubah. Cukup scan gasan mancatat kahadiran.',
        'jv' => 'Absensi modern nganggo QR Code dinamis sing tansah owah. Cukup scan kanggo nyathet kehadiran.'
    ],
    'feature_1_title' => [
        'id' => 'QR Code Dinamis',
        'bjn' => 'QR Code Dinamis',
        'jv' => 'QR Code Dinamis'
    ],
    'feature_1_desc' => [
        'id' => 'QR berubah setiap 5 detik untuk keamanan maksimal',
        'bjn' => 'QR barubah saban 5 detik gasan kaamanan maksimal',
        'jv' => 'QR owah saben 5 detik kanggo keamanan maksimal'
    ],
    'feature_2_title' => [
        'id' => 'Login dengan Google',
        'bjn' => 'Login lawan Google',
        'jv' => 'Login nganggo Google'
    ],
    'feature_2_desc' => [
        'id' => 'Gunakan akun Politala untuk login otomatis',
        'bjn' => 'Pakai akun Politala gasan login otomatis',
        'jv' => 'Nganggo akun Politala kanggo login otomatis'
    ],
    'feature_3_title' => [
        'id' => 'Realtime & Akurat',
        'bjn' => 'Realtime & Akurat',
        'jv' => 'Realtime & Akurat'
    ],
    'feature_3_desc' => [
        'id' => 'Data absensi tercatat secara realtime dan akurat',
        'bjn' => 'Data absensi tacatat sacara realtime wan akurat',
        'jv' => 'Data absensi kecathet kanthi realtime lan akurat'
    ],
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

echo "✓ $count terjemahan beranda ditambahkan\n";

echo "\n=== Migrasi Selesai ===\n";
?>
