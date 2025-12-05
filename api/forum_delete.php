<?php
session_start();
header('Content-Type: application/json');

include "../config/koneksi.php";

$is_admin = isset($_SESSION['admin_id']);
$is_user = isset($_SESSION['user_id']);

if (!$is_admin && !$is_user) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$user_id = $is_user ? intval($_SESSION['user_id']) : 0;
$input = json_decode(file_get_contents('php://input'), true);

if (!$input || empty($input['id'])) {
    http_response_code(400);
    echo json_encode(['error' => 'ID pesan harus diisi']);
    exit;
}

$message_id = intval($input['id']);
$permanent = isset($input['permanent']) && $input['permanent'] === true;

// Cek apakah pesan ada
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

// Admin bisa hapus semua pesan, user hanya bisa hapus pesannya sendiri
if (!$is_admin && $message['user_id'] != $user_id) {
    http_response_code(403);
    echo json_encode(['error' => 'Anda hanya bisa menghapus pesan sendiri']);
    exit;
}

if ($permanent) {
    // Hard delete - hapus permanen dari database
    $stmt = mysqli_prepare($conn, "DELETE FROM forum_messages WHERE id = ?");
    mysqli_stmt_bind_param($stmt, "i", $message_id);
} else {
    // Soft delete - update pesan menjadi "[Pesan dihapus]"
    $stmt = mysqli_prepare($conn, "UPDATE forum_messages SET message = '[Pesan telah dihapus]', is_deleted = 1 WHERE id = ?");
    mysqli_stmt_bind_param($stmt, "i", $message_id);
}

if (mysqli_stmt_execute($stmt)) {
    mysqli_stmt_close($stmt);
    echo json_encode(['success' => true, 'permanent' => $permanent]);
} else {
    http_response_code(500);
    echo json_encode(['error' => 'Gagal menghapus pesan']);
}
?>
