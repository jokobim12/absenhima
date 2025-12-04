<?php
/**
 * Helper functions untuk mengambil settings dari database
 * Dengan session-based caching untuk performa
 */

// Cache settings di session (5 menit)
define('SETTINGS_CACHE_TTL', 300);

/**
 * Load all settings ke cache
 */
function loadSettingsCache() {
    global $conn;
    
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    // Cek apakah cache masih valid
    if (isset($_SESSION['_settings_cache']) && 
        isset($_SESSION['_settings_cache_time']) &&
        (time() - $_SESSION['_settings_cache_time']) < SETTINGS_CACHE_TTL) {
        return $_SESSION['_settings_cache'];
    }
    
    // Load dari database
    $result = mysqli_query($conn, "SELECT setting_key, setting_value FROM settings");
    $settings = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $settings[$row['setting_key']] = $row['setting_value'];
    }
    
    // Simpan ke cache
    $_SESSION['_settings_cache'] = $settings;
    $_SESSION['_settings_cache_time'] = time();
    
    return $settings;
}

/**
 * Clear settings cache (panggil setelah update)
 */
function clearSettingsCache() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    unset($_SESSION['_settings_cache']);
    unset($_SESSION['_settings_cache_time']);
}

// Get single setting value (dengan cache)
function getSetting($key, $default = '') {
    $cache = loadSettingsCache();
    return isset($cache[$key]) && $cache[$key] !== '' ? $cache[$key] : $default;
}

// Get multiple settings by group (tanpa cache - untuk admin panel)
function getSettingsByGroup($group) {
    global $conn;
    $stmt = mysqli_prepare($conn, "SELECT * FROM settings WHERE setting_group = ? ORDER BY id");
    mysqli_stmt_bind_param($stmt, "s", $group);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $settings = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $settings[$row['setting_key']] = $row;
    }
    mysqli_stmt_close($stmt);
    return $settings;
}

// Get all settings as key-value pairs (dengan cache)
function getAllSettings() {
    return loadSettingsCache();
}

// Update setting value (clear cache setelah update)
function updateSetting($key, $value) {
    global $conn;
    $stmt = mysqli_prepare($conn, "UPDATE settings SET setting_value = ? WHERE setting_key = ?");
    mysqli_stmt_bind_param($stmt, "ss", $value, $key);
    $result = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    
    // Clear cache agar perubahan langsung terlihat
    clearSettingsCache();
    
    return $result;
}
?>
