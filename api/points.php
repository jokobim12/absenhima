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

// Point values configuration
define('POINTS_DAILY_LOGIN', 1);
define('POINTS_CHAT', 1);
define('POINTS_EVENT_NORMAL', 5);
define('POINTS_EVENT_BIG', 10);
define('POINTS_REACTION', 1);
define('POINTS_POLL_CREATE', 2);
define('POINTS_POLL_VOTE', 1);

// Function to add points
function addPoints($conn, $user_id, $points, $activity_type, $description = '', $reference_id = null) {
    // Check for duplicate (prevent spam)
    if ($activity_type == 'chat') {
        // Limit chat points: max 10 per day
        $stmt = $conn->prepare("SELECT COUNT(*) as cnt FROM point_history WHERE user_id = ? AND activity_type = 'chat' AND DATE(created_at) = CURDATE()");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $count = $stmt->get_result()->fetch_assoc()['cnt'];
        if ($count >= 10) return false; // Max 10 chat points per day
    }
    
    if ($activity_type == 'reaction') {
        // Limit reaction points: max 5 per day
        $stmt = $conn->prepare("SELECT COUNT(*) as cnt FROM point_history WHERE user_id = ? AND activity_type = 'reaction' AND DATE(created_at) = CURDATE()");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $count = $stmt->get_result()->fetch_assoc()['cnt'];
        if ($count >= 5) return false;
    }
    
    // Prevent duplicate for same reference
    if ($reference_id && in_array($activity_type, ['attendance', 'poll_vote'])) {
        $stmt = $conn->prepare("SELECT id FROM point_history WHERE user_id = ? AND activity_type = ? AND reference_id = ?");
        $stmt->bind_param("isi", $user_id, $activity_type, $reference_id);
        $stmt->execute();
        if ($stmt->get_result()->num_rows > 0) return false;
    }
    
    // Insert point history
    $stmt = $conn->prepare("INSERT INTO point_history (user_id, points, activity_type, description, reference_id) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("iissi", $user_id, $points, $activity_type, $description, $reference_id);
    $stmt->execute();
    
    // Update user total points
    $stmt = $conn->prepare("UPDATE users SET total_points = total_points + ? WHERE id = ?");
    $stmt->bind_param("ii", $points, $user_id);
    $stmt->execute();
    
    return true;
}

// Function to check and update daily login
function checkDailyLogin($conn, $user_id) {
    $stmt = $conn->prepare("SELECT last_active_date, daily_streak, longest_streak FROM users WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    
    $today = date('Y-m-d');
    $lastActive = $user['last_active_date'];
    $streak = intval($user['daily_streak']);
    $longestStreak = intval($user['longest_streak']);
    
    if ($lastActive == $today) {
        // Already logged in today
        return ['already' => true, 'streak' => $streak];
    }
    
    $yesterday = date('Y-m-d', strtotime('-1 day'));
    
    if ($lastActive == $yesterday) {
        // Consecutive day - increase streak
        $streak++;
    } else {
        // Streak broken - reset to 1
        $streak = 1;
    }
    
    // Update longest streak
    if ($streak > $longestStreak) {
        $longestStreak = $streak;
    }
    
    // Update user
    $stmt = $conn->prepare("UPDATE users SET last_active_date = ?, daily_streak = ?, longest_streak = ? WHERE id = ?");
    $stmt->bind_param("siii", $today, $streak, $longestStreak, $user_id);
    $stmt->execute();
    
    // Add daily login points
    addPoints($conn, $user_id, POINTS_DAILY_LOGIN, 'daily_login', 'Login harian');
    
    // Bonus for streak milestones
    if ($streak == 7) {
        addPoints($conn, $user_id, 5, 'streak_bonus', 'Bonus streak 7 hari');
    } elseif ($streak == 30) {
        addPoints($conn, $user_id, 20, 'streak_bonus', 'Bonus streak 30 hari');
    }
    
    return ['already' => false, 'streak' => $streak, 'points' => POINTS_DAILY_LOGIN];
}

// GET - Get leaderboard or user stats
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $action = $_GET['action'] ?? 'leaderboard';
    
    if ($action === 'leaderboard') {
        $limit = intval($_GET['limit'] ?? 10);
        $stmt = $conn->prepare("
            SELECT u.id, u.nama, u.picture, u.total_points, u.daily_streak,
                   (SELECT COUNT(*) + 1 FROM users u2 WHERE u2.total_points > u.total_points) as rank
            FROM users u 
            ORDER BY u.total_points DESC, u.daily_streak DESC
            LIMIT ?
        ");
        $stmt->bind_param("i", $limit);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $leaderboard = [];
        while ($row = $result->fetch_assoc()) {
            $leaderboard[] = $row;
        }
        
        // Get current user rank
        $stmt = $conn->prepare("SELECT (SELECT COUNT(*) + 1 FROM users u2 WHERE u2.total_points > u.total_points) as rank, total_points, daily_streak FROM users u WHERE id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $myRank = $stmt->get_result()->fetch_assoc();
        
        echo json_encode([
            'success' => true,
            'leaderboard' => $leaderboard,
            'my_rank' => $myRank
        ]);
        exit;
    }
    
    if ($action === 'my_stats') {
        $stmt = $conn->prepare("
            SELECT total_points, daily_streak, longest_streak, last_active_date,
                   (SELECT COUNT(*) + 1 FROM users u2 WHERE u2.total_points > users.total_points) as rank
            FROM users WHERE id = ?
        ");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $stats = $stmt->get_result()->fetch_assoc();
        
        // Get point breakdown
        $stmt = $conn->prepare("
            SELECT activity_type, SUM(points) as total, COUNT(*) as count
            FROM point_history 
            WHERE user_id = ?
            GROUP BY activity_type
        ");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $breakdown = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        
        // Recent activity
        $stmt = $conn->prepare("
            SELECT points, activity_type, description, created_at
            FROM point_history 
            WHERE user_id = ?
            ORDER BY created_at DESC
            LIMIT 10
        ");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $recent = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        
        echo json_encode([
            'success' => true,
            'stats' => $stats,
            'breakdown' => $breakdown,
            'recent' => $recent
        ]);
        exit;
    }
    
    if ($action === 'check_daily') {
        $result = checkDailyLogin($conn, $user_id);
        echo json_encode(['success' => true, 'data' => $result]);
        exit;
    }
}

// POST - Add points for activity
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    $activity = $data['activity'] ?? '';
    $reference_id = $data['reference_id'] ?? null;
    
    $points = 0;
    $description = '';
    
    switch ($activity) {
        case 'chat':
            $points = POINTS_CHAT;
            $description = 'Kirim pesan di forum';
            break;
        case 'reaction':
            $points = POINTS_REACTION;
            $description = 'Memberi reaksi';
            break;
        case 'poll_create':
            $points = POINTS_POLL_CREATE;
            $description = 'Membuat polling';
            break;
        case 'poll_vote':
            $points = POINTS_POLL_VOTE;
            $description = 'Vote polling';
            break;
        default:
            echo json_encode(['error' => 'Invalid activity']);
            exit;
    }
    
    $added = addPoints($conn, $user_id, $points, $activity, $description, $reference_id);
    echo json_encode(['success' => true, 'added' => $added, 'points' => $added ? $points : 0]);
    exit;
}

echo json_encode(['error' => 'Invalid request']);
?>
