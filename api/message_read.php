<?php
session_start();
header('Content-Type: application/json');

include "../config/koneksi.php";

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$user_id = $_SESSION['user_id'];

// Pastikan tabel ada
$check = mysqli_query($conn, "SHOW TABLES LIKE 'message_reads'");
if (!$check || mysqli_num_rows($check) == 0) {
    $sql = "CREATE TABLE IF NOT EXISTS message_reads (
        id INT AUTO_INCREMENT PRIMARY KEY,
        message_id INT NOT NULL,
        user_id INT NOT NULL,
        read_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY unique_read (message_id, user_id),
        INDEX idx_message_id (message_id),
        INDEX idx_user_id (user_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    mysqli_query($conn, $sql);
}

// POST: Mark messages as read
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    $message_ids = $data['message_ids'] ?? [];
    
    if (empty($message_ids)) {
        echo json_encode(['success' => false, 'error' => 'No message IDs provided']);
        exit;
    }
    
    $success_count = 0;
    foreach ($message_ids as $msg_id) {
        $msg_id = intval($msg_id);
        // Insert ignore untuk skip jika sudah ada
        $stmt = mysqli_prepare($conn, "INSERT IGNORE INTO message_reads (message_id, user_id) VALUES (?, ?)");
        mysqli_stmt_bind_param($stmt, "ii", $msg_id, $user_id);
        if (mysqli_stmt_execute($stmt)) {
            $success_count++;
        }
        mysqli_stmt_close($stmt);
    }
    
    echo json_encode(['success' => true, 'marked' => $success_count]);
    exit;
}

// GET: Get read status for messages
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $message_ids = isset($_GET['ids']) ? explode(',', $_GET['ids']) : [];
    
    if (empty($message_ids)) {
        echo json_encode(['success' => false, 'error' => 'No message IDs provided']);
        exit;
    }
    
    // Sanitize IDs
    $message_ids = array_map('intval', $message_ids);
    $ids_str = implode(',', $message_ids);
    
    // Hitung total users yang sedang online (aktif dalam 5 menit terakhir)
    $online_result = mysqli_query($conn, "
        SELECT COUNT(DISTINCT user_id) as total 
        FROM user_online_status 
        WHERE last_seen > DATE_SUB(NOW(), INTERVAL 5 MINUTE)
    ");
    $online_count = 1; // minimal 1 (pengirim sendiri)
    if ($online_result && $row = mysqli_fetch_assoc($online_result)) {
        $online_count = max(1, (int)$row['total']);
    }
    
    // Ambil read count per message
    $result = mysqli_query($conn, "
        SELECT message_id, COUNT(DISTINCT user_id) as read_count
        FROM message_reads 
        WHERE message_id IN ($ids_str)
        GROUP BY message_id
    ");
    
    $read_status = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $read_status[$row['message_id']] = [
            'read_count' => (int)$row['read_count'],
            'online_count' => $online_count,
            'all_read' => ((int)$row['read_count'] >= $online_count)
        ];
    }
    
    // Untuk message yang belum ada yang baca
    foreach ($message_ids as $id) {
        if (!isset($read_status[$id])) {
            $read_status[$id] = [
                'read_count' => 0,
                'online_count' => $online_count,
                'all_read' => false
            ];
        }
    }
    
    echo json_encode(['success' => true, 'read_status' => $read_status]);
    exit;
}

http_response_code(405);
echo json_encode(['error' => 'Method not allowed']);
?>
