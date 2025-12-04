<?php
session_start();
header('Content-Type: application/json');

include "../config/koneksi.php";

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$user_id = intval($_SESSION['user_id']);
$input = json_decode(file_get_contents('php://input'), true);

if (!$input || empty($input['id']) || empty(trim($input['message'] ?? ''))) {
    http_response_code(400);
    echo json_encode(['error' => 'ID dan pesan harus diisi']);
    exit;
}

$message_id = intval($input['id']);
$new_message = trim($input['message']);

if (strlen($new_message) > 1000) {
    http_response_code(400);
    echo json_encode(['error' => 'Pesan terlalu panjang (max 1000 karakter)']);
    exit;
}

// Cek apakah pesan milik user ini dan belum dihapus
$stmt = mysqli_prepare($conn, "SELECT id, user_id, is_deleted FROM forum_messages WHERE id = ?");
mysqli_stmt_bind_param($stmt, "i", $message_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$message = mysqli_fetch_assoc($result);
mysqli_stmt_close($stmt);

if (!$message) {
    http_response_code(404);
    echo json_encode(['error' => 'Pesan tidak ditemukan']);
    exit;
}

if ($message['user_id'] != $user_id) {
    http_response_code(403);
    echo json_encode(['error' => 'Anda hanya bisa mengedit pesan sendiri']);
    exit;
}

if ($message['is_deleted']) {
    http_response_code(400);
    echo json_encode(['error' => 'Pesan yang sudah dihapus tidak bisa diedit']);
    exit;
}

// Update pesan
$stmt = mysqli_prepare($conn, "UPDATE forum_messages SET message = ?, is_edited = 1 WHERE id = ?");
mysqli_stmt_bind_param($stmt, "si", $new_message, $message_id);

if (mysqli_stmt_execute($stmt)) {
    mysqli_stmt_close($stmt);
    echo json_encode([
        'success' => true,
        'message' => htmlspecialchars($new_message)
    ]);
} else {
    http_response_code(500);
    echo json_encode(['error' => 'Gagal mengedit pesan']);
}
?>
