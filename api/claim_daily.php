<?php
session_set_cookie_params([
    'lifetime' => 3600,
    'path' => '/',
    'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
    'httponly' => true,
    'samesite' => 'Lax'
]);
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

// GET - Check claim status
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $stmt = $conn->prepare("SELECT last_active_date, daily_streak, total_points, longest_streak FROM users WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    $today = date('Y-m-d');
    $yesterday = date('Y-m-d', strtotime('-1 day'));
    $last_active = $user['last_active_date'];
    
    $can_claim = ($last_active != $today);
    $will_continue = ($last_active == $yesterday);
    $next_streak = $will_continue ? (intval($user['daily_streak']) + 1) : 1;
    
    echo json_encode([
        'success' => true,
        'can_claim' => $can_claim,
        'current_streak' => intval($user['daily_streak']),
        'next_streak' => $next_streak,
        'will_continue' => $will_continue,
        'total_points' => intval($user['total_points'])
    ]);
    exit;
}

// POST - Claim reward
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    $mission_id = $data['mission_id'] ?? 'daily_login';
    $milestone = intval($data['milestone'] ?? 0);
    
    // Get user data
    $user = $conn->query("SELECT daily_streak, total_points FROM users WHERE id = $user_id")->fetch_assoc();
    $streak = intval($user['daily_streak']);
    
    // Handle different mission types
    if ($mission_id === 'daily_login') {
        // Check if already claimed today
        $already = $conn->query("SELECT id FROM point_history WHERE user_id = $user_id AND activity_type = 'daily_login' AND DATE(created_at) = '$today'")->fetch_assoc();
        if ($already) {
            echo json_encode(['success' => false, 'message' => 'Sudah diklaim hari ini!']);
            exit;
        }
        
        // Add points
        $conn->query("INSERT INTO point_history (user_id, points, activity_type, description) VALUES ($user_id, 1, 'daily_login', 'Login harian')");
        $conn->query("UPDATE users SET total_points = total_points + 1 WHERE id = $user_id");
        
        $new_total = $conn->query("SELECT total_points FROM users WHERE id = $user_id")->fetch_assoc()['total_points'];
        echo json_encode(['success' => true, 'points' => 1, 'total_points' => $new_total, 'message' => '+1 poin!']);
        exit;
    }
    
    // Streak milestone claim
    if (strpos($mission_id, 'streak_') === 0 && $milestone > 0) {
        // Check if eligible
        if ($streak < $milestone) {
            echo json_encode(['success' => false, 'message' => 'Streak belum mencapai target']);
            exit;
        }
        
        // Check if already claimed
        $already = $conn->query("SELECT id FROM point_history WHERE user_id = $user_id AND activity_type = 'streak_milestone' AND description LIKE '%$milestone hari%'")->fetch_assoc();
        if ($already) {
            echo json_encode(['success' => false, 'message' => 'Sudah diklaim!']);
            exit;
        }
        
        // Calculate bonus
        $bonus = $milestone <= 7 ? 5 : ($milestone <= 14 ? 10 : 20);
        $desc = "Bonus streak $milestone hari";
        
        $conn->query("INSERT INTO point_history (user_id, points, activity_type, description) VALUES ($user_id, $bonus, 'streak_milestone', '$desc')");
        $conn->query("UPDATE users SET total_points = total_points + $bonus WHERE id = $user_id");
        
        $new_total = $conn->query("SELECT total_points FROM users WHERE id = $user_id")->fetch_assoc()['total_points'];
        echo json_encode(['success' => true, 'points' => $bonus, 'total_points' => $new_total, 'message' => "+$bonus poin!"]);
        exit;
    }
    
    echo json_encode(['success' => false, 'message' => 'Misi tidak valid']);
    exit;
}

echo json_encode(['success' => false, 'error' => 'Invalid method']);
