<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

include "../config/koneksi.php";

$user_id = intval($_SESSION['user_id']);
$today = date('Y-m-d');

// Only POST allowed
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

// Check if already spun today
$check = mysqli_prepare($conn, "SELECT id FROM point_history WHERE user_id = ? AND activity_type = 'spin_wheel' AND DATE(created_at) = ?");
mysqli_stmt_bind_param($check, "is", $user_id, $today);
mysqli_stmt_execute($check);
$already = mysqli_stmt_get_result($check)->fetch_assoc();
mysqli_stmt_close($check);

if ($already) {
    echo json_encode(['success' => false, 'error' => 'Sudah spin hari ini']);
    exit;
}

// Prize distribution (weighted random)
$prizes = [
    ['value' => 1, 'weight' => 30],
    ['value' => 2, 'weight' => 25],
    ['value' => 3, 'weight' => 20],
    ['value' => 5, 'weight' => 15],
    ['value' => 7, 'weight' => 7],
    ['value' => 10, 'weight' => 3]
];

// Calculate total weight
$totalWeight = array_sum(array_column($prizes, 'weight'));
$rand = mt_rand(1, $totalWeight);

$prize = 1;
$cumulative = 0;
foreach ($prizes as $p) {
    $cumulative += $p['weight'];
    if ($rand <= $cumulative) {
        $prize = $p['value'];
        break;
    }
}

// Save to point_history
$desc = "Spin Wheel (+$prize poin)";
$stmt = mysqli_prepare($conn, "INSERT INTO point_history (user_id, points, activity_type, description) VALUES (?, ?, 'spin_wheel', ?)");
mysqli_stmt_bind_param($stmt, "iis", $user_id, $prize, $desc);
mysqli_stmt_execute($stmt);
mysqli_stmt_close($stmt);

// Update user total points
$stmt = mysqli_prepare($conn, "UPDATE users SET total_points = total_points + ? WHERE id = ?");
mysqli_stmt_bind_param($stmt, "ii", $prize, $user_id);
mysqli_stmt_execute($stmt);
mysqli_stmt_close($stmt);

// Get new total
$result = mysqli_query($conn, "SELECT total_points FROM users WHERE id = $user_id");
$total = mysqli_fetch_assoc($result)['total_points'];

echo json_encode([
    'success' => true,
    'prize' => $prize,
    'total_points' => intval($total)
]);
