<?php
session_start();
header('Content-Type: application/json');

// Check admin session
if (!isset($_SESSION['admin_id'])) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

include "../config/koneksi.php";

$user_id = intval($_GET['user_id'] ?? 0);

if ($user_id <= 0) {
    echo json_encode(['success' => false, 'error' => 'Invalid user ID']);
    exit;
}

$stmt = mysqli_prepare($conn, "SELECT points, activity_type, description, DATE_FORMAT(created_at, '%d %b %Y %H:%i') as created_at FROM point_history WHERE user_id = ? ORDER BY created_at DESC LIMIT 50");
mysqli_stmt_bind_param($stmt, "i", $user_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

$history = [];
while ($row = mysqli_fetch_assoc($result)) {
    $history[] = $row;
}

echo json_encode(['success' => true, 'history' => $history]);
