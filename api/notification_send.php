<?php
session_start();
header('Content-Type: application/json');

include "../config/koneksi.php";

// Check auth - admin only for broadcasting, user can send test to self
if (!isset($_SESSION['user_id']) && !isset($_SESSION['admin_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);

// Create notifications table if not exists
$conn->query("CREATE TABLE IF NOT EXISTS notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    type VARCHAR(50) NOT NULL,
    title VARCHAR(255) NOT NULL,
    message TEXT,
    link VARCHAR(255) DEFAULT NULL,
    data JSON DEFAULT NULL,
    is_read TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user_id (user_id),
    INDEX idx_is_read (is_read)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

// Test notification to self
if (isset($data['test']) && isset($_SESSION['user_id'])) {
    $user_id = intval($_SESSION['user_id']);
    $stmt = $conn->prepare("INSERT INTO notifications (user_id, type, title, message, link) VALUES (?, 'system', 'Test Notifikasi', 'Ini adalah notifikasi test. Fitur notifikasi berjalan dengan baik!', 'dashboard.php')");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    echo json_encode(['success' => true, 'message' => 'Test notification sent']);
    exit;
}

// Admin broadcast to all users
if (isset($data['broadcast']) && isset($_SESSION['admin_id'])) {
    $title = trim($data['title'] ?? '');
    $message = trim($data['message'] ?? '');
    $link = trim($data['link'] ?? '');
    
    if (empty($title)) {
        http_response_code(400);
        echo json_encode(['error' => 'Title is required']);
        exit;
    }
    
    // Get all user IDs
    $result = $conn->query("SELECT id FROM users");
    $count = 0;
    
    while ($user = $result->fetch_assoc()) {
        $stmt = $conn->prepare("INSERT INTO notifications (user_id, type, title, message, link) VALUES (?, 'announcement', ?, ?, ?)");
        $stmt->bind_param("isss", $user['id'], $title, $message, $link);
        $stmt->execute();
        $count++;
    }
    
    echo json_encode(['success' => true, 'message' => "Broadcast sent to $count users"]);
    exit;
}

// Send to specific user (admin only)
if (isset($data['user_id']) && isset($_SESSION['admin_id'])) {
    $target_user = intval($data['user_id']);
    $type = $data['type'] ?? 'system';
    $title = trim($data['title'] ?? '');
    $message = trim($data['message'] ?? '');
    $link = trim($data['link'] ?? '');
    
    if (empty($title)) {
        http_response_code(400);
        echo json_encode(['error' => 'Title is required']);
        exit;
    }
    
    $stmt = $conn->prepare("INSERT INTO notifications (user_id, type, title, message, link) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("issss", $target_user, $type, $title, $message, $link);
    $stmt->execute();
    
    echo json_encode(['success' => true]);
    exit;
}

echo json_encode(['error' => 'Invalid request']);
?>
