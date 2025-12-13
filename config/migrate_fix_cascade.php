<?php
/**
 * Migration: Fix CASCADE DELETE pada tabel absen
 * 
 * Masalah: Ketika token dihapus, data absen ikut terhapus karena ON DELETE CASCADE
 * Solusi: Ubah ke ON DELETE SET NULL agar data absen tetap aman
 * 
 * Jalankan file ini SEKALI untuk memperbaiki struktur database
 */

require_once "koneksi.php";

echo "<pre>";
echo "===========================================\n";
echo "Migration: Fix CASCADE DELETE on absen table\n";
echo "===========================================\n\n";

// Step 1: Cek apakah kolom token_id sudah NULLABLE
echo "Step 1: Checking current structure...\n";
$result = mysqli_query($conn, "SHOW COLUMNS FROM absen LIKE 'token_id'");
$column = mysqli_fetch_assoc($result);
echo "  token_id Null: " . $column['Null'] . "\n";
echo "  token_id Type: " . $column['Type'] . "\n\n";

// Step 2: Hapus foreign key lama
echo "Step 2: Dropping old foreign key...\n";

// Cari nama constraint
$result = mysqli_query($conn, "
    SELECT CONSTRAINT_NAME 
    FROM information_schema.KEY_COLUMN_USAGE 
    WHERE TABLE_SCHEMA = DATABASE() 
    AND TABLE_NAME = 'absen' 
    AND COLUMN_NAME = 'token_id' 
    AND REFERENCED_TABLE_NAME = 'tokens'
");

$fk_dropped = false;
while ($row = mysqli_fetch_assoc($result)) {
    $constraint_name = $row['CONSTRAINT_NAME'];
    echo "  Found constraint: $constraint_name\n";
    
    $drop_result = mysqli_query($conn, "ALTER TABLE absen DROP FOREIGN KEY `$constraint_name`");
    if ($drop_result) {
        echo "  Dropped: $constraint_name\n";
        $fk_dropped = true;
    } else {
        echo "  Failed to drop: " . mysqli_error($conn) . "\n";
    }
}

if (!$fk_dropped) {
    echo "  No foreign key found or already dropped\n";
}
echo "\n";

// Step 3: Ubah kolom token_id menjadi NULLABLE
echo "Step 3: Making token_id NULLABLE...\n";
$alter_result = mysqli_query($conn, "ALTER TABLE absen MODIFY COLUMN token_id INT NULL");
if ($alter_result) {
    echo "  Success: token_id is now NULLABLE\n";
} else {
    echo "  Error: " . mysqli_error($conn) . "\n";
}
echo "\n";

// Step 4: Tambah foreign key baru dengan ON DELETE SET NULL
echo "Step 4: Adding new foreign key with SET NULL...\n";
$fk_result = mysqli_query($conn, "
    ALTER TABLE absen 
    ADD CONSTRAINT fk_absen_token 
    FOREIGN KEY (token_id) REFERENCES tokens(id) 
    ON DELETE SET NULL
");
if ($fk_result) {
    echo "  Success: Foreign key added with ON DELETE SET NULL\n";
} else {
    echo "  Error: " . mysqli_error($conn) . "\n";
    echo "  (This might be okay if constraint already exists)\n";
}
echo "\n";

// Step 5: Verifikasi
echo "Step 5: Verifying new structure...\n";
$result = mysqli_query($conn, "SHOW COLUMNS FROM absen LIKE 'token_id'");
$column = mysqli_fetch_assoc($result);
echo "  token_id Null: " . $column['Null'] . "\n";

$result = mysqli_query($conn, "
    SELECT CONSTRAINT_NAME, DELETE_RULE
    FROM information_schema.REFERENTIAL_CONSTRAINTS 
    WHERE CONSTRAINT_SCHEMA = DATABASE() 
    AND TABLE_NAME = 'absen'
    AND CONSTRAINT_NAME LIKE '%token%'
");
while ($row = mysqli_fetch_assoc($result)) {
    echo "  Constraint: " . $row['CONSTRAINT_NAME'] . " - ON DELETE: " . $row['DELETE_RULE'] . "\n";
}

echo "\n===========================================\n";
echo "Migration completed!\n";
echo "===========================================\n";
echo "\nSekarang data absen TIDAK akan terhapus\n";
echo "ketika token di-cleanup.\n";
echo "</pre>";
?>
