<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    exit;
}

// SSE headers
header('Content-Type: text/event-stream');
header('Cache-Control: no-cache');
header('Connection: keep-alive');
header('X-Accel-Buffering: no');

// Disable output buffering
if (ob_get_level()) ob_end_clean();

include "../config/koneksi.php";

$user_id = intval($_SESSION['user_id']);
$last_notif_id = isset($_GET['last_id']) ? intval($_GET['last_id']) : 0;
$last_event_id = isset($_GET['last_event']) ? intval($_GET['last_event']) : 0;
$last_announcement_id = isset($_GET['last_ann']) ? intval($_GET['last_ann']) : 0;
$current_event_status = isset($_GET['event_status']) ? $_GET['event_status'] : '';

// Get initial IDs if not provided
if ($last_notif_id == 0) {
    $result = $conn->query("SELECT MAX(id) as max_id FROM notifications WHERE user_id = $user_id");
    if ($row = $result->fetch_assoc()) {
        $last_notif_id = (int)$row['max_id'];
    }
}

if ($last_announcement_id == 0) {
    $result = $conn->query("SELECT MAX(id) as max_id FROM announcements WHERE is_active = 1");
    if ($row = $result->fetch_assoc()) {
        $last_announcement_id = (int)$row['max_id'];
    }
}

// Get current active event
$current_event = null;
$event_result = $conn->query("SELECT id, nama_event, status FROM events WHERE status = 'open' LIMIT 1");
if ($row = $event_result->fetch_assoc()) {
    $current_event = $row;
    $last_event_id = (int)$row['id'];
}

// Send initial connection message
echo "data: " . json_encode([
    'type' => 'connected', 
    'last_notif_id' => $last_notif_id,
    'last_event_id' => $last_event_id,
    'last_announcement_id' => $last_announcement_id,
    'current_event' => $current_event
]) . "\n\n";
flush();

$start_time = time();
$max_execution = 25;

while ((time() - $start_time) < $max_execution) {
    // 1. Check for new notifications
    $stmt = $conn->prepare("
        SELECT id, type, title, message, link, data, is_read, created_at 
        FROM notifications 
        WHERE user_id = ? AND id > ?
        ORDER BY id ASC
    ");
    $stmt->bind_param("ii", $user_id, $last_notif_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        $row['is_read'] = (bool)$row['is_read'];
        $row['data'] = $row['data'] ? json_decode($row['data'], true) : null;
        
        echo "data: " . json_encode(['type' => 'notification', 'notification' => $row]) . "\n\n";
        flush();
        
        $last_notif_id = (int)$row['id'];
    }
    $stmt->close();
    
    // 2. Check for event status changes
    $event_result = $conn->query("SELECT id, nama_event, deskripsi, lokasi, waktu_mulai, status FROM events WHERE status = 'open' LIMIT 1");
    $new_event = $event_result->fetch_assoc();
    
    if ($new_event && (!$current_event || $current_event['id'] != $new_event['id'])) {
        // New event started!
        echo "data: " . json_encode(['type' => 'event_started', 'event' => $new_event]) . "\n\n";
        flush();
        $current_event = $new_event;
        $last_event_id = (int)$new_event['id'];
    } else if (!$new_event && $current_event) {
        // Event closed
        echo "data: " . json_encode(['type' => 'event_closed', 'event_id' => $current_event['id']]) . "\n\n";
        flush();
        $current_event = null;
    }
    
    // 3. Check for new announcements
    $ann_result = $conn->query("
        SELECT id, title, content, type, is_pinned, created_at 
        FROM announcements 
        WHERE is_active = 1 AND id > $last_announcement_id
        ORDER BY id ASC
    ");
    
    while ($row = $ann_result->fetch_assoc()) {
        echo "data: " . json_encode(['type' => 'announcement', 'announcement' => $row]) . "\n\n";
        flush();
        $last_announcement_id = (int)$row['id'];
    }
    
    // 4. Get unread notification count
    $count_result = $conn->query("SELECT COUNT(*) as count FROM notifications WHERE user_id = $user_id AND is_read = 0");
    $unread_count = 0;
    if ($row = $count_result->fetch_assoc()) {
        $unread_count = (int)$row['count'];
    }
    
    // Send heartbeat
    echo "data: " . json_encode([
        'type' => 'heartbeat', 
        'unread_count' => $unread_count,
        'last_notif_id' => $last_notif_id,
        'last_event_id' => $last_event_id,
        'last_announcement_id' => $last_announcement_id
    ]) . "\n\n";
    flush();
    
    if (connection_aborted()) break;
    
    sleep(2);
}

echo "data: " . json_encode(['type' => 'reconnect']) . "\n\n";
flush();
?>
