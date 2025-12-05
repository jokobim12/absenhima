<?php
session_start();
header('Content-Type: application/json');

include "../config/koneksi.php";

// Create table if not exists
$conn->query("CREATE TABLE IF NOT EXISTS online_status (
    user_id INT PRIMARY KEY,
    last_seen TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_last_seen (last_seen)
) ENGINE=InnoDB");

// Clean old entries (offline > 2 minutes)
$conn->query("DELETE FROM online_status WHERE last_seen < DATE_SUB(NOW(), INTERVAL 2 MINUTE)");

// POST - Update online status
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SESSION['user_id'])) {
    $user_id = intval($_SESSION['user_id']);
    $stmt = $conn->prepare("INSERT INTO online_status (user_id, last_seen) VALUES (?, NOW()) ON DUPLICATE KEY UPDATE last_seen = NOW()");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    echo json_encode(['success' => true]);
    exit;
}

// GET - Get online users count and list
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // Get online users (active in last 2 minutes)
    $result = $conn->query("
        SELECT o.user_id, u.nama, u.picture 
        FROM online_status o 
        JOIN users u ON o.user_id = u.id 
        WHERE o.last_seen > DATE_SUB(NOW(), INTERVAL 2 MINUTE)
        ORDER BY o.last_seen DESC
        LIMIT 50
    ");
    
    $users = [];
    while ($row = $result->fetch_assoc()) {
        $users[] = [
            'id' => $row['user_id'],
            'nama' => $row['nama'],
            'picture' => $row['picture']
        ];
    }
    
    echo json_encode([
        'success' => true, 
        'count' => count($users),
        'users' => $users
    ]);
    exit;
}

echo json_encode(['error' => 'Invalid request']);
?>
