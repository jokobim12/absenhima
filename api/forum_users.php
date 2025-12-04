<?php
session_start();
header('Content-Type: application/json');

include "../config/koneksi.php";

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$search = isset($_GET['q']) ? trim($_GET['q']) : '';

if (strlen($search) < 1) {
    echo json_encode(['users' => []]);
    exit;
}

$search = '%' . $search . '%';
$stmt = mysqli_prepare($conn, "SELECT id, nama, picture FROM users WHERE nama LIKE ? ORDER BY nama ASC LIMIT 10");
mysqli_stmt_bind_param($stmt, "s", $search);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

$users = [];
while ($row = mysqli_fetch_assoc($result)) {
    $users[] = [
        'id' => $row['id'],
        'nama' => $row['nama'],
        'picture' => $row['picture']
    ];
}
mysqli_stmt_close($stmt);

echo json_encode(['users' => $users]);
?>
