<?php
session_start();
header('Content-Type: application/json');

include "../config/koneksi.php";

// Admin atau user bisa pin/unpin
$is_admin = isset($_SESSION['admin_id']);
$is_user = isset($_SESSION['user_id']);

if (!$is_admin && !$is_user) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$pinned_by_id = $is_admin ? intval($_SESSION['admin_id']) : intval($_SESSION['user_id']);
$input = json_decode(file_get_contents('php://input'), true);

if (!$input || empty($input['id'])) {
    http_response_code(400);
    echo json_encode(['error' => 'ID pesan harus diisi']);
    exit;
}

$message_id = intval($input['id']);
$action = isset($input['action']) ? $input['action'] : 'toggle';

// Cek apakah pesan ada
$stmt = mysqli_prepare($conn, "SELECT id, is_pinned FROM forum_messages WHERE id = ?");
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

// Toggle atau set pin status
$current_pinned = (bool)$message['is_pinned'];
if ($action === 'pin') {
    $new_pinned = 1;
} elseif ($action === 'unpin') {
    $new_pinned = 0;
} else {
    $new_pinned = $current_pinned ? 0 : 1;
}

if ($new_pinned) {
    $stmt = mysqli_prepare($conn, "UPDATE forum_messages SET is_pinned = 1, pinned_by = ?, pinned_at = NOW() WHERE id = ?");
    mysqli_stmt_bind_param($stmt, "ii", $pinned_by_id, $message_id);
} else {
    $stmt = mysqli_prepare($conn, "UPDATE forum_messages SET is_pinned = 0, pinned_by = NULL, pinned_at = NULL WHERE id = ?");
    mysqli_stmt_bind_param($stmt, "i", $message_id);
}

if (mysqli_stmt_execute($stmt)) {
    mysqli_stmt_close($stmt);
    echo json_encode([
        'success' => true,
        'is_pinned' => (bool)$new_pinned,
        'message' => $new_pinned ? 'Pesan berhasil di-pin' : 'Pesan berhasil di-unpin'
    ]);
} else {
    http_response_code(500);
    echo json_encode(['error' => 'Gagal mengubah status pin']);
}
?>
