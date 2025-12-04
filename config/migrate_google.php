<?php
/**
 * Migration untuk menambahkan Google OAuth settings ke database
 */

require_once 'koneksi.php';

$settings = [
    [
        'setting_key' => 'google_client_id',
        'setting_value' => '',
        'setting_label' => 'Google Client ID',
        'setting_type' => 'text',
        'setting_group' => 'google'
    ],
    [
        'setting_key' => 'google_client_secret',
        'setting_value' => '',
        'setting_label' => 'Google Client Secret',
        'setting_type' => 'text',
        'setting_group' => 'google'
    ],
    [
        'setting_key' => 'google_redirect_uri',
        'setting_value' => '',
        'setting_label' => 'Google Redirect URI',
        'setting_type' => 'text',
        'setting_group' => 'google'
    ]
];

echo "Menambahkan Google OAuth settings...\n";

foreach ($settings as $setting) {
    $check = mysqli_query($conn, "SELECT id FROM settings WHERE setting_key = '{$setting['setting_key']}'");
    
    if (mysqli_num_rows($check) == 0) {
        $sql = "INSERT INTO settings (setting_key, setting_value, setting_label, setting_type, setting_group) 
                VALUES ('{$setting['setting_key']}', '{$setting['setting_value']}', '{$setting['setting_label']}', '{$setting['setting_type']}', '{$setting['setting_group']}')";
        
        if (mysqli_query($conn, $sql)) {
            echo "- Added: {$setting['setting_key']}\n";
        } else {
            echo "- Error adding {$setting['setting_key']}: " . mysqli_error($conn) . "\n";
        }
    } else {
        echo "- Exists: {$setting['setting_key']}\n";
    }
}

echo "\nMigration selesai!\n";
echo "Silakan set Google credentials melalui Admin Panel > Settings > Google OAuth\n";
?>
