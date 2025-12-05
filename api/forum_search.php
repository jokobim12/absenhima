<?php
session_start();
header('Content-Type: application/json');

include "../config/koneksi.php";

// Check auth
if (!isset($_SESSION['user_id']) && !isset($_SESSION['admin_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$query = trim($_GET['q'] ?? '');
$limit = min(50, max(10, intval($_GET['limit'] ?? 20)));

if (strlen($query) < 2) {
    echo json_encode(['success' => true, 'messages' => [], 'query' => $query]);
    exit;
}

$search = '%' . $query . '%';

$stmt = $conn->prepare("
    SELECT fm.*, u.nama, u.picture 
    FROM forum_messages fm
    LEFT JOIN users u ON fm.user_id = u.id
    WHERE fm.message LIKE ? AND fm.is_deleted = 0
    ORDER BY fm.created_at DESC
    LIMIT ?
");
$stmt->bind_param("si", $search, $limit);
$stmt->execute();
$result = $stmt->get_result();

$messages = [];
while ($row = $result->fetch_assoc()) {
    $messages[] = [
        'id' => (int)$row['id'],
        'user_id' => (int)$row['user_id'],
        'nama' => $row['nama'],
        'picture' => $row['picture'],
        'message' => $row['message'],
        'image_url' => $row['image_url'],
        'created_at' => $row['created_at'],
        'is_pinned' => (bool)$row['is_pinned']
    ];
}

echo json_encode([
    'success' => true, 
    'messages' => $messages, 
    'query' => $query,
    'count' => count($messages)
]);
?>
