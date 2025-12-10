<?php
session_set_cookie_params([
    'lifetime' => 3600,
    'path' => '/',
    'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
    'httponly' => true,
    'samesite' => 'Lax'
]);
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

include "../config/koneksi.php";

$user_id = intval($_SESSION['user_id']);
$last_notif = intval($_GET['last_notif'] ?? 0);
$last_event = intval($_GET['last_event'] ?? 0);
$last_ann = intval($_GET['last_ann'] ?? 0);
$event_status = $_GET['event_status'] ?? '';

$response = [];

// 1. Check new notification (hanya 1 terbaru)
if ($last_notif > 0) {
    $stmt = $conn->prepare("SELECT id, type, title, message, link, created_at FROM notifications WHERE user_id = ? AND id > ? ORDER BY id DESC LIMIT 1");
    $stmt->bind_param("ii", $user_id, $last_notif);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        $response['new_notification'] = $row;
        $response['last_notif_id'] = (int)$row['id'];
    }
    $stmt->close();
}

// 2. Check event status change
$event_result = $conn->query("SELECT id, nama_event FROM events WHERE status = 'open' LIMIT 1");
$current_event = $event_result->fetch_assoc();

if ($current_event && $event_status !== 'open') {
    $response['event_started'] = $current_event;
    $response['event_status'] = 'open';
    $response['last_event_id'] = (int)$current_event['id'];
} else if (!$current_event && $event_status === 'open') {
    $response['event_closed'] = true;
    $response['event_status'] = 'closed';
}

// 3. Check new announcement
if ($last_ann > 0) {
    $result = $conn->query("SELECT id, title, content, type FROM announcements WHERE is_active = 1 AND id > $last_ann ORDER BY id DESC LIMIT 1");
    if ($row = $result->fetch_assoc()) {
        $response['new_announcement'] = $row;
    }
}

// 4. Unread count (simple query)
$count_result = $conn->query("SELECT COUNT(*) as c FROM notifications WHERE user_id = $user_id AND is_read = 0");
$response['unread_count'] = (int)$count_result->fetch_assoc()['c'];

echo json_encode($response);
