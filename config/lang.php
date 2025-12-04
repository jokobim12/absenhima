<?php
/**
 * Language helper functions
 */

// Pastikan dependencies sudah di-include
require_once __DIR__ . '/koneksi.php';
require_once __DIR__ . '/settings.php';

// Cache translations per request untuk performa
$_translationsCache = null;

// Load all translations to cache (single query)
function loadTranslationsCache($lang) {
    global $conn, $_translationsCache;
    
    if ($_translationsCache !== null && isset($_translationsCache['_lang']) && $_translationsCache['_lang'] === $lang) {
        return $_translationsCache;
    }
    
    $stmt = mysqli_prepare($conn, "SELECT lang_key, lang_value FROM languages WHERE lang_code = ?");
    mysqli_stmt_bind_param($stmt, "s", $lang);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    $_translationsCache = ['_lang' => $lang];
    while ($row = mysqli_fetch_assoc($result)) {
        $_translationsCache[$row['lang_key']] = $row['lang_value'];
    }
    mysqli_stmt_close($stmt);
    
    // Load Indonesian as fallback if different language
    if ($lang !== 'id') {
        $stmt = mysqli_prepare($conn, "SELECT lang_key, lang_value FROM languages WHERE lang_code = 'id'");
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        while ($row = mysqli_fetch_assoc($result)) {
            if (!isset($_translationsCache[$row['lang_key']])) {
                $_translationsCache[$row['lang_key']] = $row['lang_value'];
            }
        }
        mysqli_stmt_close($stmt);
    }
    
    return $_translationsCache;
}

// Get translation (optimized dengan cache)
function __($key, $lang = null) {
    // Get default language if not specified
    if (!$lang) {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $lang = $_SESSION['lang'] ?? getSetting('default_language', 'id');
    }
    
    $cache = loadTranslationsCache($lang);
    
    return $cache[$key] ?? $key;
}

// Get all translations for a language
function getAllTranslations($lang = 'id') {
    global $conn;
    
    $stmt = mysqli_prepare($conn, "SELECT lang_key, lang_value FROM languages WHERE lang_code = ?");
    mysqli_stmt_bind_param($stmt, "s", $lang);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    $translations = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $translations[$row['lang_key']] = $row['lang_value'];
    }
    mysqli_stmt_close($stmt);
    
    return $translations;
}

// Get all translations grouped by key
function getTranslationsGrouped() {
    global $conn;
    
    $result = mysqli_query($conn, "SELECT lang_key, lang_code, lang_value FROM languages ORDER BY lang_key, lang_code");
    $translations = [];
    
    while ($row = mysqli_fetch_assoc($result)) {
        if (!isset($translations[$row['lang_key']])) {
            $translations[$row['lang_key']] = [];
        }
        $translations[$row['lang_key']][$row['lang_code']] = $row['lang_value'];
    }
    
    return $translations;
}

// Update translation
function updateTranslation($key, $lang, $value) {
    global $conn;
    
    // Check if exists
    $stmt = mysqli_prepare($conn, "SELECT id FROM languages WHERE lang_key = ? AND lang_code = ?");
    mysqli_stmt_bind_param($stmt, "ss", $key, $lang);
    mysqli_stmt_execute($stmt);
    $check = mysqli_stmt_get_result($stmt);
    $exists = mysqli_num_rows($check) > 0;
    mysqli_stmt_close($stmt);
    
    if ($exists) {
        $stmt = mysqli_prepare($conn, "UPDATE languages SET lang_value = ? WHERE lang_key = ? AND lang_code = ?");
        mysqli_stmt_bind_param($stmt, "sss", $value, $key, $lang);
    } else {
        $stmt = mysqli_prepare($conn, "INSERT INTO languages (lang_key, lang_code, lang_value) VALUES (?, ?, ?)");
        mysqli_stmt_bind_param($stmt, "sss", $key, $lang, $value);
    }
    
    $result = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    
    return $result;
}

// Get current language
function getCurrentLang() {
    return $_SESSION['lang'] ?? getSetting('default_language') ?? 'id';
}

// Set current language
function setCurrentLang($lang) {
    $_SESSION['lang'] = $lang;
}

// Available languages
function getAvailableLanguages() {
    return [
        'id' => 'Indonesia',
        'bjn' => 'Banjar',
        'jv' => 'Jawa'
    ];
}

// Language labels for keys (grouped)
function getTranslationLabels() {
    return [
        // Dashboard
        'welcome' => 'Selamat datang',
        'semester' => 'Semester',
        'attendance' => 'Kehadiran',
        'active_event' => 'Event Aktif',
        'no_active_event' => 'Tidak Ada Event Aktif',
        'wait_admin' => 'Tunggu Admin',
        'scan_now' => 'Scan QR Sekarang',
        'attendance_history' => 'Riwayat Kehadiran',
        'total' => 'Total',
        'present' => 'Hadir',
        'no_history' => 'Belum Ada Riwayat',
        'statistics' => 'Statistik',
        'total_present' => 'Total Hadir',
        'menu' => 'Menu',
        'edit_profile' => 'Edit Profil',
        'change_class' => 'Ubah Kelas',
        'scan_qr' => 'Scan QR',
        'attend_now' => 'Absen Sekarang',
        'logout_confirm' => 'Konfirmasi Logout',
        'logout_message' => 'Pesan Logout',
        'cancel' => 'Batal',
        'yes_logout' => 'Ya Logout',
        'complete_class' => 'Lengkapi Kelas',
        'login_with_google' => 'Login Google',
        'use_politala_email' => 'Gunakan Email Politala',
        // Profile
        'my_profile' => 'Profil Saya',
        'back' => 'Kembali',
        'delete_photo' => 'Hapus Foto',
        'delete_photo_confirm' => 'Hapus Foto Profil?',
        'academic_info' => 'Informasi Akademik',
        'auto_calculated' => 'Dihitung Otomatis',
        'entry_year' => 'Tahun Masuk',
        'current_semester' => 'Semester Saat Ini',
        'class' => 'Kelas',
        'edit_data' => 'Edit Data',
        'class_example' => 'Contoh Kelas',
        'class_hint' => 'Petunjuk Kelas',
        'save_changes' => 'Simpan Perubahan',
        'adjust_photo' => 'Atur Posisi Foto',
        'save' => 'Simpan',
    ];
}

// Homepage translation labels
function getHomepageTranslationLabels() {
    return [
        'hero_title' => 'Judul Hero',
        'hero_subtitle' => 'Subjudul Hero',
        'hero_description' => 'Deskripsi Hero',
        'feature_1_title' => 'Fitur 1 - Judul',
        'feature_1_desc' => 'Fitur 1 - Deskripsi',
        'feature_2_title' => 'Fitur 2 - Judul',
        'feature_2_desc' => 'Fitur 2 - Deskripsi',
        'feature_3_title' => 'Fitur 3 - Judul',
        'feature_3_desc' => 'Fitur 3 - Deskripsi',
    ];
}

// Get homepage translations grouped (optimized single query)
function getHomepageTranslations() {
    global $conn;
    
    $keys = array_keys(getHomepageTranslationLabels());
    $translations = array_fill_keys($keys, []);
    
    // Single query untuk semua keys
    $placeholders = implode(',', array_fill(0, count($keys), '?'));
    $stmt = mysqli_prepare($conn, "SELECT lang_key, lang_code, lang_value FROM languages WHERE lang_key IN ($placeholders)");
    
    // Bind parameters dynamically
    $types = str_repeat('s', count($keys));
    mysqli_stmt_bind_param($stmt, $types, ...$keys);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    while ($row = mysqli_fetch_assoc($result)) {
        $translations[$row['lang_key']][$row['lang_code']] = $row['lang_value'];
    }
    mysqli_stmt_close($stmt);
    
    return $translations;
}
?>
