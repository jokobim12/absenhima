<?php
/**
 * Helper functions untuk mengambil settings dari database
 */

// Get single setting value
function getSetting($key, $default = '') {
    global $conn;
    $key = mysqli_real_escape_string($conn, $key);
    $result = mysqli_query($conn, "SELECT setting_value FROM settings WHERE setting_key = '$key' LIMIT 1");
    if ($row = mysqli_fetch_assoc($result)) {
        return $row['setting_value'] ?: $default;
    }
    return $default;
}

// Get multiple settings by group
function getSettingsByGroup($group) {
    global $conn;
    $group = mysqli_real_escape_string($conn, $group);
    $result = mysqli_query($conn, "SELECT * FROM settings WHERE setting_group = '$group' ORDER BY id");
    $settings = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $settings[$row['setting_key']] = $row;
    }
    return $settings;
}

// Get all settings as key-value pairs
function getAllSettings() {
    global $conn;
    $result = mysqli_query($conn, "SELECT setting_key, setting_value FROM settings");
    $settings = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $settings[$row['setting_key']] = $row['setting_value'];
    }
    return $settings;
}

// Update setting value
function updateSetting($key, $value) {
    global $conn;
    $key = mysqli_real_escape_string($conn, $key);
    $value = mysqli_real_escape_string($conn, $value);
    return mysqli_query($conn, "UPDATE settings SET setting_value = '$value' WHERE setting_key = '$key'");
}
?>
