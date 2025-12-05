<?php
session_start();
header('Content-Type: application/json');

include "../config/koneksi.php";

// Check auth - support both user and admin
$user_id = null;
$user_name = 'Unknown';
if (isset($_SESSION['user_id'])) {
    $user_id = intval($_SESSION['user_id']);
    $stmt = $conn->prepare("SELECT nama FROM users WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        $user_name = $row['nama'];
    }
} elseif (isset($_SESSION['admin_id'])) {
    $user_id = 'admin_' . intval($_SESSION['admin_id']);
    $user_name = 'Admin';
}

if (!$user_id) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

// Create table if not exists
$conn->query("CREATE TABLE IF NOT EXISTS forum_reactions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    message_id INT NOT NULL,
    user_id VARCHAR(50) NOT NULL,
    user_name VARCHAR(100) NOT NULL DEFAULT 'Unknown',
    emoji VARCHAR(20) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_reaction (message_id, user_id, emoji),
    INDEX idx_message (message_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

// Add user_name column if not exists (MySQL compatible way)
$tableExists = $conn->query("SHOW TABLES LIKE 'forum_reactions'");
if ($tableExists && $tableExists->num_rows > 0) {
    $checkCol = $conn->query("SHOW COLUMNS FROM forum_reactions LIKE 'user_name'");
    if ($checkCol && $checkCol->num_rows == 0) {
        @$conn->query("ALTER TABLE forum_reactions ADD COLUMN user_name VARCHAR(100) NOT NULL DEFAULT 'Unknown' AFTER user_id");
    }
}

// GET - Get reactions for messages
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $message_ids = isset($_GET['ids']) ? $_GET['ids'] : '';
    
    // Get detail for specific message and emoji
    if (isset($_GET['detail']) && isset($_GET['emoji'])) {
        $msg_id = intval($_GET['detail']);
        $emoji = $_GET['emoji'];
        
        $stmt = $conn->prepare("SELECT user_id, user_name, created_at FROM forum_reactions WHERE message_id = ? AND emoji = ? ORDER BY created_at ASC");
        $stmt->bind_param("is", $msg_id, $emoji);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $users = [];
        while ($row = $result->fetch_assoc()) {
            $users[] = [
                'user_id' => $row['user_id'],
                'name' => $row['user_name'],
                'is_me' => ($row['user_id'] == (string)$user_id)
            ];
        }
        
        echo json_encode(['success' => true, 'emoji' => $emoji, 'users' => $users, 'message_id' => $msg_id]);
        exit;
    }
    
    if (empty($message_ids)) {
        echo json_encode(['success' => true, 'reactions' => []]);
        exit;
    }
    
    // Sanitize IDs
    $ids = array_map('intval', explode(',', $message_ids));
    $ids_str = implode(',', $ids);
    
    $result = $conn->query("
        SELECT message_id, emoji, COUNT(*) as count, 
               GROUP_CONCAT(user_id) as users,
               GROUP_CONCAT(user_name SEPARATOR '||') as names
        FROM forum_reactions 
        WHERE message_id IN ($ids_str)
        GROUP BY message_id, emoji
    ");
    
    $reactions = [];
    while ($row = $result->fetch_assoc()) {
        $msg_id = $row['message_id'];
        if (!isset($reactions[$msg_id])) {
            $reactions[$msg_id] = [];
        }
        $userIds = explode(',', $row['users']);
        $names = explode('||', $row['names']);
        
        $reactions[$msg_id][] = [
            'emoji' => $row['emoji'],
            'count' => (int)$row['count'],
            'users' => $userIds,
            'names' => $names,
            'reacted' => in_array((string)$user_id, $userIds)
        ];
    }
    
    echo json_encode(['success' => true, 'reactions' => $reactions, 'current_user_id' => (string)$user_id]);
    exit;
}

// POST - Add/Remove reaction
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    $message_id = intval($data['message_id'] ?? 0);
    $emoji = $data['emoji'] ?? '';
    
    if (!$message_id || !$emoji) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid data']);
        exit;
    }
    
    // Validate emoji (only allow specific emojis)
    $allowed = ['👍', '❤️', '😂', '😮', '😢', '😡', '🎉', '🔥', '👏', '💯'];
    if (!in_array($emoji, $allowed)) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid emoji']);
        exit;
    }
    
    $uid = (string)$user_id;
    
    // Check if removing specific reaction
    if (isset($data['remove']) && $data['remove'] === true) {
        $stmt = $conn->prepare("DELETE FROM forum_reactions WHERE message_id = ? AND user_id = ? AND emoji = ?");
        $stmt->bind_param("iss", $message_id, $uid, $emoji);
        $stmt->execute();
        echo json_encode(['success' => true, 'action' => 'removed']);
        exit;
    }
    
    // Check if already reacted
    $stmt = $conn->prepare("SELECT id FROM forum_reactions WHERE message_id = ? AND user_id = ? AND emoji = ?");
    $stmt->bind_param("iss", $message_id, $uid, $emoji);
    $stmt->execute();
    $existing = $stmt->get_result()->fetch_assoc();
    
    if ($existing) {
        // Already reacted, do nothing (user must use detail modal to remove)
        echo json_encode(['success' => true, 'action' => 'exists']);
    } else {
        // Add reaction with user name
        $stmt = $conn->prepare("INSERT INTO forum_reactions (message_id, user_id, user_name, emoji) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("isss", $message_id, $uid, $user_name, $emoji);
        $stmt->execute();
        echo json_encode(['success' => true, 'action' => 'added']);
    }
    exit;
}
?>
