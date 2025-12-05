<?php
// Initialize sticker tables
function initStickerTables($conn) {
    // Tabel sticker packs
    mysqli_query($conn, "CREATE TABLE IF NOT EXISTS sticker_packs (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100) NOT NULL,
        thumbnail VARCHAR(255),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    
    // Tabel stickers (semua stiker)
    mysqli_query($conn, "CREATE TABLE IF NOT EXISTS stickers (
        id INT AUTO_INCREMENT PRIMARY KEY,
        pack_id INT DEFAULT NULL,
        file_url VARCHAR(255) NOT NULL,
        uploaded_by INT DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_pack (pack_id),
        INDEX idx_uploader (uploaded_by)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    
    // Tabel user_stickers (koleksi stiker user)
    mysqli_query($conn, "CREATE TABLE IF NOT EXISTS user_stickers (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        sticker_id INT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY unique_user_sticker (user_id, sticker_id),
        INDEX idx_user (user_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    
    // Insert default pack jika belum ada
    $check = mysqli_query($conn, "SELECT id FROM sticker_packs LIMIT 1");
    if (mysqli_num_rows($check) == 0) {
        mysqli_query($conn, "INSERT INTO sticker_packs (name, thumbnail) VALUES 
            ('Emoji Populer', NULL),
            ('Lucu', NULL),
            ('Reaction', NULL)
        ");
    }
}
?>
