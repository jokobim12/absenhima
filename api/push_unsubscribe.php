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

if (!$input || !isset($input['endpoint'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid request']);
    exit;
}

$endpoint = $input['endpoint'];

$stmt = mysqli_prepare($conn, "DELETE FROM push_subscriptions WHERE user_id = ? AND endpoint = ?");
mysqli_stmt_bind_param($stmt, "is", $user_id, $endpoint);

if (mysqli_stmt_execute($stmt)) {
    echo json_encode(['success' => true, 'message' => 'Subscription removed']);
} else {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to remove subscription']);
}
mysqli_stmt_close($stmt);
?>
