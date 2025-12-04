<?php
/**
 * Language helper functions
 */

// Get translation
function __($key, $lang = null) {
    global $conn;
    
    // Get default language if not specified
    if (!$lang) {
        $lang = $_SESSION['lang'] ?? getSetting('default_language') ?? 'id';
    }
    
    $key = mysqli_real_escape_string($conn, $key);
    $lang = mysqli_real_escape_string($conn, $lang);
    
    $result = mysqli_query($conn, "SELECT lang_value FROM languages WHERE lang_key = '$key' AND lang_code = '$lang' LIMIT 1");
    
    if ($row = mysqli_fetch_assoc($result)) {
        return $row['lang_value'];
    }
    
    // Fallback to Indonesian
    if ($lang != 'id') {
        $result = mysqli_query($conn, "SELECT lang_value FROM languages WHERE lang_key = '$key' AND lang_code = 'id' LIMIT 1");
        if ($row = mysqli_fetch_assoc($result)) {
            return $row['lang_value'];
        }
    }
    
    return $key; // Return key if no translation found
}

// Get all translations for a language
function getAllTranslations($lang = 'id') {
    global $conn;
    $lang = mysqli_real_escape_string($conn, $lang);
    
    $result = mysqli_query($conn, "SELECT lang_key, lang_value FROM languages WHERE lang_code = '$lang'");
    $translations = [];
    
    while ($row = mysqli_fetch_assoc($result)) {
        $translations[$row['lang_key']] = $row['lang_value'];
    }
    
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
    
    $key = mysqli_real_escape_string($conn, $key);
    $lang = mysqli_real_escape_string($conn, $lang);
    $value = mysqli_real_escape_string($conn, $value);
    
    // Check if exists
    $check = mysqli_query($conn, "SELECT id FROM languages WHERE lang_key = '$key' AND lang_code = '$lang'");
    
    if (mysqli_num_rows($check) > 0) {
        return mysqli_query($conn, "UPDATE languages SET lang_value = '$value' WHERE lang_key = '$key' AND lang_code = '$lang'");
    } else {
        return mysqli_query($conn, "INSERT INTO languages (lang_key, lang_code, lang_value) VALUES ('$key', '$lang', '$value')");
    }
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

// Get homepage translations grouped
function getHomepageTranslations() {
    global $conn;
    
    $keys = array_keys(getHomepageTranslationLabels());
    $translations = [];
    
    foreach ($keys as $key) {
        $key_escaped = mysqli_real_escape_string($conn, $key);
        $result = mysqli_query($conn, "SELECT lang_code, lang_value FROM languages WHERE lang_key = '$key_escaped'");
        
        $translations[$key] = [];
        while ($row = mysqli_fetch_assoc($result)) {
            $translations[$key][$row['lang_code']] = $row['lang_value'];
        }
    }
    
    return $translations;
}
?>
