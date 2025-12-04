<?php
session_start();
header('Content-Type: application/json');

include "../config/koneksi.php";

// Cek tabel exists
$check = mysqli_query($conn, "SHOW TABLES LIKE 'forum_messages'");
if (!$check || mysqli_num_rows($check) == 0) {
    $sql = "CREATE TABLE IF NOT EXISTS forum_messages (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        message TEXT NOT NULL,
        reply_to INT DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_user_id (user_id),
        INDEX idx_created_at (created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    mysqli_query($conn, $sql);
}

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$user_id = intval($_SESSION['user_id']);
$input = json_decode(file_get_contents('php://input'), true);

if (!$input || empty(trim($input['message'] ?? ''))) {
    http_response_code(400);
    echo json_encode(['error' => 'Pesan harus diisi']);
    exit;
}

$message = trim($input['message']);
$reply_to = isset($input['reply_to']) ? intval($input['reply_to']) : null;

if (strlen($message) > 1000) {
    http_response_code(400);
    echo json_encode(['error' => 'Pesan terlalu panjang (max 1000 karakter)']);
    exit;
}

// Simpan pesan
if ($reply_to) {
    $stmt = mysqli_prepare($conn, "INSERT INTO forum_messages (user_id, message, reply_to) VALUES (?, ?, ?)");
    mysqli_stmt_bind_param($stmt, "isi", $user_id, $message, $reply_to);
} else {
    $stmt = mysqli_prepare($conn, "INSERT INTO forum_messages (user_id, message) VALUES (?, ?)");
    mysqli_stmt_bind_param($stmt, "is", $user_id, $message);
}

if (mysqli_stmt_execute($stmt)) {
    $message_id = mysqli_insert_id($conn);
    mysqli_stmt_close($stmt);
    
    $stmt = mysqli_prepare($conn, "SELECT nama, picture FROM users WHERE id = ?");
    mysqli_stmt_bind_param($stmt, "i", $user_id);
    mysqli_stmt_execute($stmt);
    $user = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);
    
    // Get reply info if exists
    $reply_info = null;
    if ($reply_to) {
        $stmt = mysqli_prepare($conn, "SELECT m.message, u.nama FROM forum_messages m JOIN users u ON m.user_id = u.id WHERE m.id = ?");
        mysqli_stmt_bind_param($stmt, "i", $reply_to);
        mysqli_stmt_execute($stmt);
        $reply_info = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
        mysqli_stmt_close($stmt);
    }
    
    echo json_encode([
        'success' => true,
        'message' => [
            'id' => $message_id,
            'user_id' => $user_id,
            'nama' => $user['nama'],
            'picture' => $user['picture'],
            'message' => htmlspecialchars($message),
            'reply_to' => $reply_to,
            'reply_info' => $reply_info,
            'created_at' => date('Y-m-d H:i:s')
        ]
    ]);
} else {
    http_response_code(500);
    echo json_encode(['error' => 'Gagal menyimpan pesan: ' . mysqli_error($conn)]);
}
?>
