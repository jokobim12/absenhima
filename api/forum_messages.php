<?php
session_start();
header('Content-Type: application/json');

include "../config/koneksi.php";

if (!isset($_SESSION['user_id']) && !isset($_SESSION['admin_id'])) {
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

// Cek kolom is_pinned
$checkPin = mysqli_query($conn, "SHOW COLUMNS FROM forum_messages LIKE 'is_pinned'");
if (mysqli_num_rows($checkPin) == 0) {
    mysqli_query($conn, "ALTER TABLE forum_messages ADD COLUMN is_pinned TINYINT(1) DEFAULT 0 AFTER is_edited");
    mysqli_query($conn, "ALTER TABLE forum_messages ADD COLUMN pinned_by INT DEFAULT NULL AFTER is_pinned");
    mysqli_query($conn, "ALTER TABLE forum_messages ADD COLUMN pinned_at TIMESTAMP NULL DEFAULT NULL AFTER pinned_by");
}

// Cek kolom voice_url dan voice_duration
$checkVoice = mysqli_query($conn, "SHOW COLUMNS FROM forum_messages LIKE 'voice_url'");
if (mysqli_num_rows($checkVoice) == 0) {
    mysqli_query($conn, "ALTER TABLE forum_messages ADD COLUMN voice_url VARCHAR(255) DEFAULT NULL AFTER file_name");
    mysqli_query($conn, "ALTER TABLE forum_messages ADD COLUMN voice_duration INT DEFAULT 0 AFTER voice_url");
}

// Cek apakah user adalah admin
$is_admin = isset($_SESSION['admin_id']);
$current_user_id = $_SESSION['user_id'] ?? $_SESSION['admin_id'] ?? 0;

// Pastikan tabel message_reads ada
$checkReads = mysqli_query($conn, "SHOW TABLES LIKE 'message_reads'");
if (!$checkReads || mysqli_num_rows($checkReads) == 0) {
    mysqli_query($conn, "CREATE TABLE IF NOT EXISTS message_reads (
        id INT AUTO_INCREMENT PRIMARY KEY,
        message_id INT NOT NULL,
        user_id INT NOT NULL,
        read_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY unique_read (message_id, user_id),
        INDEX idx_message_id (message_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}

// Hitung online users (aktif dalam 5 menit)
$online_count = 1;
$onlineCheck = mysqli_query($conn, "SHOW TABLES LIKE 'user_online_status'");
if ($onlineCheck && mysqli_num_rows($onlineCheck) > 0) {
    $onlineResult = mysqli_query($conn, "
        SELECT COUNT(DISTINCT user_id) as total 
        FROM user_online_status 
        WHERE last_seen > DATE_SUB(NOW(), INTERVAL 5 MINUTE)
    ");
    if ($onlineResult && $row = mysqli_fetch_assoc($onlineResult)) {
        $online_count = max(1, (int)$row['total']);
    }
}

// Ambil pesan
if ($last_id > 0) {
    $stmt = mysqli_prepare($conn, "
        SELECT m.id, m.user_id, m.message, m.image_url, m.file_url, m.file_name, m.voice_url, m.voice_duration, m.reply_to, m.is_deleted, m.is_edited, m.is_pinned, m.pinned_at, m.created_at, u.nama, u.picture 
        FROM forum_messages m 
        JOIN users u ON m.user_id = u.id 
        WHERE m.id > ?
        ORDER BY m.id ASC
    ");
    mysqli_stmt_bind_param($stmt, "i", $last_id);
} else {
    $result = mysqli_query($conn, "
        SELECT m.id, m.user_id, m.message, m.image_url, m.file_url, m.file_name, m.voice_url, m.voice_duration, m.reply_to, m.is_deleted, m.is_edited, m.is_pinned, m.pinned_at, m.created_at, u.nama, u.picture 
        FROM forum_messages m 
        JOIN users u ON m.user_id = u.id 
        ORDER BY m.is_pinned DESC, m.id DESC
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

// Get read counts for all messages
$read_counts = [];
if (!empty($messages)) {
    $msg_ids = array_column($messages, 'id');
    $msg_ids_str = implode(',', $msg_ids);
    $readResult = mysqli_query($conn, "
        SELECT message_id, COUNT(DISTINCT user_id) as read_count
        FROM message_reads 
        WHERE message_id IN ($msg_ids_str)
        GROUP BY message_id
    ");
    if ($readResult) {
        while ($r = mysqli_fetch_assoc($readResult)) {
            $read_counts[$r['message_id']] = (int)$r['read_count'];
        }
    }
}

// Format messages
$formatted = [];
$pinned = [];
$regular = [];

foreach ($messages as $row) {
    $msg_read_count = $read_counts[$row['id']] ?? 0;
    $msg = [
        'id' => $row['id'],
        'user_id' => $row['user_id'],
        'nama' => $row['nama'],
        'picture' => $row['picture'],
        'message' => htmlspecialchars($row['message']),
        'image_url' => $row['image_url'] ?? null,
        'file_url' => $row['file_url'] ?? null,
        'file_name' => $row['file_name'] ?? null,
        'voice_url' => $row['voice_url'] ?? null,
        'voice_duration' => (int)($row['voice_duration'] ?? 0),
        'reply_to' => $row['reply_to'],
        'reply_info' => $row['reply_to'] ? ($reply_data[$row['reply_to']] ?? null) : null,
        'is_deleted' => (bool)($row['is_deleted'] ?? 0),
        'is_edited' => (bool)($row['is_edited'] ?? 0),
        'is_pinned' => (bool)($row['is_pinned'] ?? 0),
        'pinned_at' => $row['pinned_at'] ?? null,
        'created_at' => $row['created_at'],
        'read_count' => $msg_read_count,
        'online_count' => $online_count,
        'all_read' => ($msg_read_count >= $online_count)
    ];
    
    if ($msg['is_pinned']) {
        $pinned[] = $msg;
    } else {
        $regular[] = $msg;
    }
}

// Reverse regular messages if initial load, keep pinned at top
if ($last_id == 0) {
    $regular = array_reverse($regular);
    $formatted = array_merge($pinned, $regular);
} else {
    $formatted = array_merge($pinned, $regular);
}

echo json_encode(['messages' => $formatted, 'is_admin' => $is_admin]);
?>
