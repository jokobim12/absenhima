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

// Create tables if not exist
$conn->query("CREATE TABLE IF NOT EXISTS forum_polls (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    question VARCHAR(500) NOT NULL,
    options TEXT NOT NULL,
    is_multiple TINYINT(1) DEFAULT 0,
    is_anonymous TINYINT(1) DEFAULT 0,
    ends_at DATETIME DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user_id (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

// Check table creation error
if ($conn->error) {
    error_log("Poll table error: " . $conn->error);
}

$conn->query("CREATE TABLE IF NOT EXISTS forum_poll_votes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    poll_id INT NOT NULL,
    user_id INT NOT NULL,
    option_index INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_vote (poll_id, user_id, option_index),
    INDEX idx_poll_id (poll_id)
) ENGINE=InnoDB");

// GET - Get poll data
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['id'])) {
    $poll_id = intval($_GET['id']);
    
    // Get poll
    $stmt = $conn->prepare("SELECT p.*, u.nama as creator_name FROM forum_polls p JOIN users u ON p.user_id = u.id WHERE p.id = ?");
    $stmt->bind_param("i", $poll_id);
    $stmt->execute();
    $poll = $stmt->get_result()->fetch_assoc();
    
    if (!$poll) {
        echo json_encode(['error' => 'Poll not found']);
        exit;
    }
    
    $options = json_decode($poll['options'], true);
    
    // Get vote counts
    $stmt = $conn->prepare("SELECT option_index, COUNT(*) as count FROM forum_poll_votes WHERE poll_id = ? GROUP BY option_index");
    $stmt->bind_param("i", $poll_id);
    $stmt->execute();
    $votes_result = $stmt->get_result();
    
    $vote_counts = [];
    $total_votes = 0;
    while ($row = $votes_result->fetch_assoc()) {
        $vote_counts[$row['option_index']] = (int)$row['count'];
        $total_votes += (int)$row['count'];
    }
    
    // Check if user voted
    $stmt = $conn->prepare("SELECT option_index FROM forum_poll_votes WHERE poll_id = ? AND user_id = ?");
    $stmt->bind_param("ii", $poll_id, $user_id);
    $stmt->execute();
    $user_votes = [];
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $user_votes[] = $row['option_index'];
    }
    
    // Check if ended
    $is_ended = $poll['ends_at'] && strtotime($poll['ends_at']) < time();
    
    echo json_encode([
        'success' => true,
        'poll' => [
            'id' => $poll['id'],
            'question' => $poll['question'],
            'options' => $options,
            'is_multiple' => (bool)$poll['is_multiple'],
            'is_anonymous' => (bool)$poll['is_anonymous'],
            'ends_at' => $poll['ends_at'],
            'is_ended' => $is_ended,
            'creator' => $poll['creator_name'],
            'created_at' => $poll['created_at'],
            'vote_counts' => $vote_counts,
            'total_votes' => $total_votes,
            'user_votes' => $user_votes,
            'has_voted' => !empty($user_votes)
        ]
    ]);
    exit;
}

// POST - Create poll or vote
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    
    // Create poll
    if (isset($data['create'])) {
        $question = trim($data['question'] ?? '');
        $options = $data['options'] ?? [];
        $is_multiple = !empty($data['is_multiple']) ? 1 : 0;
        $is_anonymous = !empty($data['is_anonymous']) ? 1 : 0;
        $duration = intval($data['duration'] ?? 0); // in hours, 0 = no limit
        
        if (empty($question) || count($options) < 2) {
            echo json_encode(['error' => 'Pertanyaan dan minimal 2 pilihan diperlukan']);
            exit;
        }
        
        $options = array_slice(array_filter(array_map('trim', $options)), 0, 10);
        $options_json = json_encode($options);
        $ends_at = $duration > 0 ? date('Y-m-d H:i:s', strtotime("+{$duration} hours")) : null;
        
        $stmt = $conn->prepare("INSERT INTO forum_polls (user_id, question, options, is_multiple, is_anonymous, ends_at) VALUES (?, ?, ?, ?, ?, ?)");
        if (!$stmt) {
            echo json_encode(['error' => 'DB prepare error: ' . $conn->error]);
            exit;
        }
        $stmt->bind_param("issiis", $user_id, $question, $options_json, $is_multiple, $is_anonymous, $ends_at);
        if (!$stmt->execute()) {
            echo json_encode(['error' => 'DB execute error: ' . $stmt->error]);
            exit;
        }
        $poll_id = $conn->insert_id;
        
        echo json_encode(['success' => true, 'poll_id' => $poll_id]);
        exit;
    }
    
    // Vote
    if (isset($data['vote'])) {
        $poll_id = intval($data['poll_id'] ?? 0);
        $option_indices = $data['options'] ?? [];
        
        if (!$poll_id || empty($option_indices)) {
            echo json_encode(['error' => 'Invalid vote']);
            exit;
        }
        
        // Check if poll exists and not ended
        $stmt = $conn->prepare("SELECT * FROM forum_polls WHERE id = ?");
        $stmt->bind_param("i", $poll_id);
        $stmt->execute();
        $poll = $stmt->get_result()->fetch_assoc();
        
        if (!$poll) {
            echo json_encode(['error' => 'Poll not found']);
            exit;
        }
        
        if ($poll['ends_at'] && strtotime($poll['ends_at']) < time()) {
            echo json_encode(['error' => 'Polling sudah berakhir']);
            exit;
        }
        
        // If not multiple choice, remove previous votes
        if (!$poll['is_multiple']) {
            $stmt = $conn->prepare("DELETE FROM forum_poll_votes WHERE poll_id = ? AND user_id = ?");
            $stmt->bind_param("ii", $poll_id, $user_id);
            $stmt->execute();
            $option_indices = [intval($option_indices[0])];
        }
        
        // Add votes
        foreach ($option_indices as $opt_idx) {
            $opt_idx = intval($opt_idx);
            $stmt = $conn->prepare("INSERT IGNORE INTO forum_poll_votes (poll_id, user_id, option_index) VALUES (?, ?, ?)");
            $stmt->bind_param("iii", $poll_id, $user_id, $opt_idx);
            $stmt->execute();
        }
        
        echo json_encode(['success' => true]);
        exit;
    }
}

echo json_encode(['error' => 'Invalid request']);
?>
