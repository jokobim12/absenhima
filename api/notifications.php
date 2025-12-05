<?php
session_start();
header('Content-Type: application/json');

include "../config/koneksi.php";

// Check auth
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$user_id = intval($_SESSION['user_id']);

// Create table if not exists
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
    INDEX idx_is_read (is_read),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

// GET - Get notifications
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // Get unpaid iuran count
    $unpaid_iuran = $conn->query("
        SELECT COUNT(*) as count, COALESCE(SUM(i.nominal), 0) as total
        FROM iuran i
        LEFT JOIN iuran_payments ip ON ip.iuran_id = i.id AND ip.user_id = $user_id
        WHERE i.status = 'active' AND ip.id IS NULL
    ")->fetch_assoc();
    $unpaid_count = (int)$unpaid_iuran['count'];
    
    // Get unread count
    if (isset($_GET['count'])) {
        $stmt = $conn->prepare("SELECT COUNT(*) as count FROM notifications WHERE user_id = ? AND is_read = 0");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        $notif_count = (int)$result['count'];
        
        // Add unpaid iuran to count
        $total_count = $notif_count + ($unpaid_count > 0 ? 1 : 0);
        echo json_encode(['success' => true, 'count' => $total_count]);
        exit;
    }
    
    // Get notifications list
    $limit = isset($_GET['limit']) ? intval($_GET['limit']) : 20;
    $offset = isset($_GET['offset']) ? intval($_GET['offset']) : 0;
    
    $notifications = [];
    
    // Add unpaid iuran notification at top (if any and offset is 0)
    if ($offset == 0 && $unpaid_count > 0) {
        $notifications[] = [
            'id' => 'iuran',
            'type' => 'iuran',
            'title' => "💰 {$unpaid_count} iuran belum dibayar",
            'message' => 'Total Rp ' . number_format($unpaid_iuran['total'], 0, ',', '.') . ' - Bayar ke bendahara',
            'link' => 'iuran.php',
            'data' => null,
            'is_read' => false,
            'created_at' => date('Y-m-d H:i:s'),
            'is_virtual' => true
        ];
    }
    
    $stmt = $conn->prepare("
        SELECT id, type, title, message, link, data, is_read, created_at 
        FROM notifications 
        WHERE user_id = ? 
        ORDER BY created_at DESC 
        LIMIT ? OFFSET ?
    ");
    $stmt->bind_param("iii", $user_id, $limit, $offset);
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        $row['is_read'] = (bool)$row['is_read'];
        $row['data'] = $row['data'] ? json_decode($row['data'], true) : null;
        $notifications[] = $row;
    }
    
    // Get unread count
    $stmt2 = $conn->prepare("SELECT COUNT(*) as count FROM notifications WHERE user_id = ? AND is_read = 0");
    $stmt2->bind_param("i", $user_id);
    $stmt2->execute();
    $unread = $stmt2->get_result()->fetch_assoc()['count'];
    $total_unread = (int)$unread + ($unpaid_count > 0 ? 1 : 0);
    
    echo json_encode([
        'success' => true, 
        'notifications' => $notifications,
        'unread_count' => $total_unread
    ]);
    exit;
}

// POST - Mark as read or create notification
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    
    // Mark single as read
    if (isset($data['mark_read']) && isset($data['id'])) {
        $id = intval($data['id']);
        $stmt = $conn->prepare("UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?");
        $stmt->bind_param("ii", $id, $user_id);
        $stmt->execute();
        echo json_encode(['success' => true]);
        exit;
    }
    
    // Mark all as read
    if (isset($data['mark_all_read'])) {
        $stmt = $conn->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        echo json_encode(['success' => true]);
        exit;
    }
    
    // Delete old notifications (keep last 100)
    if (isset($data['cleanup'])) {
        $conn->query("DELETE FROM notifications WHERE user_id = $user_id AND id NOT IN (SELECT id FROM (SELECT id FROM notifications WHERE user_id = $user_id ORDER BY created_at DESC LIMIT 100) as t)");
        echo json_encode(['success' => true]);
        exit;
    }
    
    echo json_encode(['error' => 'Invalid action']);
    exit;
}

// Helper function to create notification (called from other scripts)
function createNotification($conn, $user_id, $type, $title, $message = '', $link = null, $data = null) {
    $stmt = $conn->prepare("INSERT INTO notifications (user_id, type, title, message, link, data) VALUES (?, ?, ?, ?, ?, ?)");
    $data_json = $data ? json_encode($data) : null;
    $stmt->bind_param("isssss", $user_id, $type, $title, $message, $link, $data_json);
    return $stmt->execute();
}
?>
