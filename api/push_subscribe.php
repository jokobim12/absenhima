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

if (!$input || !isset($input['endpoint']) || !isset($input['keys'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid subscription data']);
    exit;
}

$endpoint = $input['endpoint'];
$p256dh = $input['keys']['p256dh'] ?? '';
$auth = $input['keys']['auth'] ?? '';

if (empty($endpoint) || empty($p256dh) || empty($auth)) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing required keys']);
    exit;
}

// Hapus subscription lama untuk user ini dengan endpoint yang sama (upsert)
$stmt = mysqli_prepare($conn, "DELETE FROM push_subscriptions WHERE user_id = ? AND endpoint = ?");
mysqli_stmt_bind_param($stmt, "is", $user_id, $endpoint);
mysqli_stmt_execute($stmt);
mysqli_stmt_close($stmt);

// Insert subscription baru
$stmt = mysqli_prepare($conn, "INSERT INTO push_subscriptions (user_id, endpoint, p256dh, auth) VALUES (?, ?, ?, ?)");
mysqli_stmt_bind_param($stmt, "isss", $user_id, $endpoint, $p256dh, $auth);

if (mysqli_stmt_execute($stmt)) {
    echo json_encode(['success' => true, 'message' => 'Subscription saved']);
} else {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to save subscription']);
}
mysqli_stmt_close($stmt);
?>
