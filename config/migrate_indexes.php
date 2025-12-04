<?php
/**
 * Migration untuk menambahkan database indexes
 * Meningkatkan performa query secara signifikan
 */

require_once 'koneksi.php';

echo "=== Adding Database Indexes ===\n\n";

$indexes = [
    // Tokens table - untuk validasi token
    [
        'table' => 'tokens',
        'name' => 'idx_token',
        'columns' => 'token',
        'comment' => 'Index untuk lookup token saat validasi absen'
    ],
    [
        'table' => 'tokens', 
        'name' => 'idx_event_expired',
        'columns' => 'event_id, expired_at',
        'comment' => 'Composite index untuk query token valid per event'
    ],
    
    // Absen table - untuk cek duplicate absen
    [
        'table' => 'absen',
        'name' => 'idx_user_event',
        'columns' => 'user_id, event_id',
        'comment' => 'Composite index untuk cek absen existing (sudah unique, tapi tambah index biasa)'
    ],
    
    // Users table - untuk lookup NIM
    [
        'table' => 'users',
        'name' => 'idx_nim',
        'columns' => 'nim',
        'comment' => 'Index untuk pencarian berdasarkan NIM'
    ],
    
    // Events table - untuk filter status
    [
        'table' => 'events',
        'name' => 'idx_status',
        'columns' => 'status',
        'comment' => 'Index untuk filter event open/closed'
    ],
];

foreach ($indexes as $idx) {
    $table = $idx['table'];
    $name = $idx['name'];
    $columns = $idx['columns'];
    
    // Cek apakah index sudah ada
    $check = mysqli_query($conn, "SHOW INDEX FROM $table WHERE Key_name = '$name'");
    
    if (mysqli_num_rows($check) > 0) {
        echo "[SKIP] $name already exists on $table\n";
        continue;
    }
    
    // Buat index
    $sql = "CREATE INDEX $name ON $table ($columns)";
    
    if (mysqli_query($conn, $sql)) {
        echo "[OK] Created $name on $table ($columns)\n";
    } else {
        echo "[ERROR] Failed to create $name: " . mysqli_error($conn) . "\n";
    }
}

echo "\n=== Index Migration Complete ===\n";
echo "\nRun 'EXPLAIN SELECT...' to verify query optimization.\n";
?>
