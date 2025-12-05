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
$year = intval($_GET['year'] ?? date('Y'));

// Get attendance data for the year
$stmt = $conn->prepare("
    SELECT DATE(COALESCE(waktu, created_at)) as date, COUNT(*) as count
    FROM absen 
    WHERE user_id = ? AND YEAR(COALESCE(waktu, created_at)) = ?
    GROUP BY DATE(COALESCE(waktu, created_at))
");
$stmt->bind_param("ii", $user_id, $year);
$stmt->execute();
$result = $stmt->get_result();

$attendance = [];
while ($row = $result->fetch_assoc()) {
    $attendance[$row['date']] = (int)$row['count'];
}

// Get total stats
$stmt = $conn->prepare("SELECT COUNT(*) as total FROM absen WHERE user_id = ? AND YEAR(COALESCE(waktu, created_at)) = ?");
$stmt->bind_param("ii", $user_id, $year);
$stmt->execute();
$total = $stmt->get_result()->fetch_assoc()['total'];

// Get streak info
$stmt = $conn->prepare("SELECT current_streak, longest_streak FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$streaks = $stmt->get_result()->fetch_assoc();

echo json_encode([
    'success' => true,
    'year' => $year,
    'attendance' => $attendance,
    'total' => (int)$total,
    'current_streak' => (int)($streaks['current_streak'] ?? 0),
    'longest_streak' => (int)($streaks['longest_streak'] ?? 0)
]);
?>
