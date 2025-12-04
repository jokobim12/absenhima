<?php
session_start();
header('Content-Type: application/json');

include "../config/koneksi.php";

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$last_id = isset($_GET['last_id']) ? intval($_GET['last_id']) : 0;

// Cek apakah tabel ada, buat jika belum
$check = mysqli_query($conn, "SHOW TABLES LIKE 'forum_messages'");
if (!$check || mysqli_num_rows($check) == 0) {
    $sql = "CREATE TABLE IF NOT EXISTS forum_messages (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        message TEXT NOT NULL,
        reply_to INT DEFAULT NULL,
        is_deleted TINYINT(1) DEFAULT 0,
        is_edited TINYINT(1) DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_user_id (user_id),
        INDEX idx_created_at (created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    mysqli_query($conn, $sql);
    echo json_encode(['messages' => []]);
    exit;
}

// Cek kolom is_deleted dan is_edited
$checkDel = mysqli_query($conn, "SHOW COLUMNS FROM forum_messages LIKE 'is_deleted'");
if (mysqli_num_rows($checkDel) == 0) {
    mysqli_query($conn, "ALTER TABLE forum_messages ADD COLUMN is_deleted TINYINT(1) DEFAULT 0 AFTER reply_to");
}
$checkEdit = mysqli_query($conn, "SHOW COLUMNS FROM forum_messages LIKE 'is_edited'");
if (mysqli_num_rows($checkEdit) == 0) {
    mysqli_query($conn, "ALTER TABLE forum_messages ADD COLUMN is_edited TINYINT(1) DEFAULT 0 AFTER is_deleted");
}

// Cek kolom image_url
$checkImg = mysqli_query($conn, "SHOW COLUMNS FROM forum_messages LIKE 'image_url'");
if (mysqli_num_rows($checkImg) == 0) {
    mysqli_query($conn, "ALTER TABLE forum_messages ADD COLUMN image_url VARCHAR(255) DEFAULT NULL AFTER message");
}

// Ambil pesan
if ($last_id > 0) {
    $stmt = mysqli_prepare($conn, "
        SELECT m.id, m.user_id, m.message, m.image_url, m.reply_to, m.is_deleted, m.is_edited, m.created_at, u.nama, u.picture 
        FROM forum_messages m 
        JOIN users u ON m.user_id = u.id 
        WHERE m.id > ?
        ORDER BY m.id ASC
    ");
    mysqli_stmt_bind_param($stmt, "i", $last_id);
} else {
    $result = mysqli_query($conn, "
        SELECT m.id, m.user_id, m.message, m.image_url, m.reply_to, m.is_deleted, m.is_edited, m.created_at, u.nama, u.picture 
        FROM forum_messages m 
        JOIN users u ON m.user_id = u.id 
        ORDER BY m.id DESC
        LIMIT 50
    ");
}

if ($last_id > 0) {
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
}

$messages = [];
$reply_ids = [];

while ($row = mysqli_fetch_assoc($result)) {
    $messages[] = $row;
    if ($row['reply_to']) {
        $reply_ids[] = $row['reply_to'];
    }
}

if ($last_id > 0) {
    mysqli_stmt_close($stmt);
}

// Get reply info
$reply_data = [];
if (!empty($reply_ids)) {
    $ids = implode(',', array_unique($reply_ids));
    $replyResult = mysqli_query($conn, "
        SELECT m.id, m.message, u.nama 
        FROM forum_messages m 
        JOIN users u ON m.user_id = u.id 
        WHERE m.id IN ($ids)
    ");
    while ($r = mysqli_fetch_assoc($replyResult)) {
        $reply_data[$r['id']] = ['nama' => $r['nama'], 'message' => $r['message']];
    }
}

// Format messages
$formatted = [];
foreach ($messages as $row) {
    $formatted[] = [
        'id' => $row['id'],
        'user_id' => $row['user_id'],
        'nama' => $row['nama'],
        'picture' => $row['picture'],
        'message' => htmlspecialchars($row['message']),
        'image_url' => $row['image_url'] ?? null,
        'reply_to' => $row['reply_to'],
        'reply_info' => $row['reply_to'] ? ($reply_data[$row['reply_to']] ?? null) : null,
        'is_deleted' => (bool)($row['is_deleted'] ?? 0),
        'is_edited' => (bool)($row['is_edited'] ?? 0),
        'created_at' => $row['created_at']
    ];
}

// Reverse if initial load
if ($last_id == 0) {
    $formatted = array_reverse($formatted);
}

echo json_encode(['messages' => $formatted]);
?>
