<?php
session_start();
header('Content-Type: application/json');

include "../config/koneksi.php";

// Cek tabel exists
$check = mysqli_query($conn, "SHOW TABLES LIKE 'forum_messages'");
if (!$check || mysqli_num_rows($check) == 0) {
    $sql = "CREATE TABLE IF NOT EXISTS forum_messages (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        message TEXT NOT NULL,
        image_url VARCHAR(255) DEFAULT NULL,
        reply_to INT DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_user_id (user_id),
        INDEX idx_created_at (created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    mysqli_query($conn, $sql);
}

// Cek kolom image_url
$checkImg = mysqli_query($conn, "SHOW COLUMNS FROM forum_messages LIKE 'image_url'");
if (mysqli_num_rows($checkImg) == 0) {
    mysqli_query($conn, "ALTER TABLE forum_messages ADD COLUMN image_url VARCHAR(255) DEFAULT NULL AFTER message");
}

// Cek kolom file_url dan file_name
$checkFile = mysqli_query($conn, "SHOW COLUMNS FROM forum_messages LIKE 'file_url'");
if (mysqli_num_rows($checkFile) == 0) {
    mysqli_query($conn, "ALTER TABLE forum_messages ADD COLUMN file_url VARCHAR(255) DEFAULT NULL AFTER image_url");
    mysqli_query($conn, "ALTER TABLE forum_messages ADD COLUMN file_name VARCHAR(255) DEFAULT NULL AFTER file_url");
}

// Cek kolom voice_url dan voice_duration
$checkVoice = mysqli_query($conn, "SHOW COLUMNS FROM forum_messages LIKE 'voice_url'");
if (mysqli_num_rows($checkVoice) == 0) {
    mysqli_query($conn, "ALTER TABLE forum_messages ADD COLUMN voice_url VARCHAR(255) DEFAULT NULL AFTER file_name");
    mysqli_query($conn, "ALTER TABLE forum_messages ADD COLUMN voice_duration INT DEFAULT 0 AFTER voice_url");
}

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$user_id = intval($_SESSION['user_id']);
$input = json_decode(file_get_contents('php://input'), true);

$message = trim($input['message'] ?? '');
$image_url = isset($input['image_url']) ? trim($input['image_url']) : null;
$file_url = isset($input['file_url']) ? trim($input['file_url']) : null;
$file_name = isset($input['file_name']) ? trim($input['file_name']) : null;
$voice_url = isset($input['voice_url']) ? trim($input['voice_url']) : null;
$voice_duration = isset($input['voice_duration']) ? intval($input['voice_duration']) : 0;
$reply_to = isset($input['reply_to']) ? intval($input['reply_to']) : null;

// Validasi: harus ada message, image, file, atau voice
if (empty($message) && empty($image_url) && empty($file_url) && empty($voice_url)) {
    http_response_code(400);
    echo json_encode(['error' => 'Pesan, gambar, file, atau voice harus diisi']);
    exit;
}

if (strlen($message) > 2000) {
    http_response_code(400);
    echo json_encode(['error' => 'Pesan terlalu panjang (max 2000 karakter)']);
    exit;
}

// Simpan pesan
$stmt = mysqli_prepare($conn, "INSERT INTO forum_messages (user_id, message, image_url, file_url, file_name, voice_url, voice_duration, reply_to) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
mysqli_stmt_bind_param($stmt, "isssssis", $user_id, $message, $image_url, $file_url, $file_name, $voice_url, $voice_duration, $reply_to);

if (mysqli_stmt_execute($stmt)) {
    $message_id = mysqli_insert_id($conn);
    mysqli_stmt_close($stmt);
    
    $stmt = mysqli_prepare($conn, "SELECT nama, picture FROM users WHERE id = ?");
    mysqli_stmt_bind_param($stmt, "i", $user_id);
    mysqli_stmt_execute($stmt);
    $user = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);
    $sender_name = $user['nama'];
    
    // Get reply info if exists
    $reply_info = null;
    $reply_user_id = null;
    if ($reply_to) {
        $stmt = mysqli_prepare($conn, "SELECT m.message, m.user_id, u.nama FROM forum_messages m JOIN users u ON m.user_id = u.id WHERE m.id = ?");
        mysqli_stmt_bind_param($stmt, "i", $reply_to);
        mysqli_stmt_execute($stmt);
        $reply_result = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
        if ($reply_result) {
            $reply_info = ['nama' => $reply_result['nama'], 'message' => $reply_result['message']];
            $reply_user_id = $reply_result['user_id'];
        }
        mysqli_stmt_close($stmt);
        
        // Create notification for reply (if not replying to self)
        if ($reply_user_id && $reply_user_id != $user_id) {
            $notif_title = "$sender_name membalas pesanmu";
            $notif_message = !empty($message) ? substr($message, 0, 100) : ($voice_url ? '🎤 Voice message' : ($image_url ? '📷 Gambar' : '📎 File'));
            $notif_link = "dashboard.php#msg-$message_id";
            
            $stmt = mysqli_prepare($conn, "INSERT INTO notifications (user_id, type, title, message, link) VALUES (?, 'reply', ?, ?, ?)");
            mysqli_stmt_bind_param($stmt, "isss", $reply_user_id, $notif_title, $notif_message, $notif_link);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
        }
    }
    
    // Check for mentions (@username) and create notifications
    if (!empty($message)) {
        preg_match_all('/@(\w+)/', $message, $mentions);
        if (!empty($mentions[1])) {
            $mentioned_names = array_unique($mentions[1]);
            foreach ($mentioned_names as $mention_name) {
                // Find user by name
                $stmt = mysqli_prepare($conn, "SELECT id FROM users WHERE nama LIKE ? AND id != ?");
                $mention_pattern = "%$mention_name%";
                mysqli_stmt_bind_param($stmt, "si", $mention_pattern, $user_id);
                mysqli_stmt_execute($stmt);
                $mention_result = mysqli_stmt_get_result($stmt);
                
                while ($mention_user = mysqli_fetch_assoc($mention_result)) {
                    $notif_title = "$sender_name menyebutmu di forum";
                    $notif_message = substr($message, 0, 100);
                    $notif_link = "dashboard.php#msg-$message_id";
                    
                    $stmt2 = mysqli_prepare($conn, "INSERT INTO notifications (user_id, type, title, message, link) VALUES (?, 'mention', ?, ?, ?)");
                    mysqli_stmt_bind_param($stmt2, "isss", $mention_user['id'], $notif_title, $notif_message, $notif_link);
                    mysqli_stmt_execute($stmt2);
                    mysqli_stmt_close($stmt2);
                }
                mysqli_stmt_close($stmt);
            }
        }
    }
    
    // Add points for chat missions
    // Misi 1: Kirim 1 pesan (1 poin)
    $checkChat1 = $conn->prepare("SELECT id FROM point_history WHERE user_id = ? AND activity_type = 'chat' AND DATE(created_at) = CURDATE() LIMIT 1");
    if ($checkChat1) {
        $checkChat1->bind_param("i", $user_id);
        $checkChat1->execute();
        if ($checkChat1->get_result()->num_rows == 0) {
            $conn->query("INSERT INTO point_history (user_id, points, activity_type, description, reference_id) VALUES ($user_id, 1, 'chat', 'Kirim pesan di forum', $message_id)");
            $conn->query("UPDATE users SET total_points = total_points + 1 WHERE id = $user_id");
        }
        $checkChat1->close();
    }
    
    // Misi 2: Kirim 5 pesan (3 poin bonus)
    $checkChat5 = $conn->prepare("SELECT id FROM point_history WHERE user_id = ? AND activity_type = 'chat_5' AND DATE(created_at) = CURDATE() LIMIT 1");
    if ($checkChat5) {
        $checkChat5->bind_param("i", $user_id);
        $checkChat5->execute();
        if ($checkChat5->get_result()->num_rows == 0) {
            // Hitung total pesan hari ini (termasuk yang baru dikirim)
            $countMsg = $conn->query("SELECT COUNT(*) as c FROM forum_messages WHERE user_id = $user_id AND DATE(created_at) = CURDATE()");
            $msgCount = $countMsg->fetch_assoc()['c'];
            if ($msgCount >= 5) {
                $conn->query("INSERT INTO point_history (user_id, points, activity_type, description, reference_id) VALUES ($user_id, 3, 'chat_5', 'Aktif berdiskusi (5 pesan)', $message_id)");
                $conn->query("UPDATE users SET total_points = total_points + 3 WHERE id = $user_id");
            }
        }
        $checkChat5->close();
    }
    
    echo json_encode([
        'success' => true,
        'message' => [
            'id' => $message_id,
            'user_id' => $user_id,
            'nama' => $user['nama'],
            'picture' => $user['picture'],
            'message' => htmlspecialchars($message),
            'image_url' => $image_url,
            'file_url' => $file_url,
            'file_name' => $file_name,
            'voice_url' => $voice_url,
            'voice_duration' => $voice_duration,
            'reply_to' => $reply_to,
            'reply_info' => $reply_info,
            'created_at' => date('Y-m-d H:i:s')
        ]
    ]);
} else {
    http_response_code(500);
    echo json_encode(['error' => 'Gagal menyimpan pesan: ' . mysqli_error($conn)]);
}
?>
