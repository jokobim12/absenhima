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

// Create table if not exists
$conn->query("CREATE TABLE IF NOT EXISTS typing_status (
    user_id INT PRIMARY KEY,
    user_name VARCHAR(100),
    typing_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_typing_at (typing_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// POST - Update typing status
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get user name
    $stmt = $conn->prepare("SELECT nama FROM users WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    $user_name = $user['nama'] ?? 'User';
    
    // Update typing status
    $stmt = $conn->prepare("INSERT INTO typing_status (user_id, user_name, typing_at) VALUES (?, ?, NOW()) ON DUPLICATE KEY UPDATE user_name = ?, typing_at = NOW()");
    $stmt->bind_param("iss", $user_id, $user_name, $user_name);
    $stmt->execute();
    
    echo json_encode(['success' => true]);
    exit;
}

// GET - Get who is typing (exclude self, only last 3 seconds)
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $stmt = $conn->prepare("SELECT user_name FROM typing_status WHERE user_id != ? AND typing_at > DATE_SUB(NOW(), INTERVAL 3 SECOND) LIMIT 3");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $typing = [];
    while ($row = $result->fetch_assoc()) {
        $typing[] = $row['user_name'];
    }
    
    echo json_encode(['success' => true, 'typing' => $typing]);
    exit;
}
?>
