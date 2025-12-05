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

// Cek dan buat kolom jika belum ada
$checkCol = mysqli_query($conn, "SHOW COLUMNS FROM users LIKE 'forum_wallpaper'");
if (mysqli_num_rows($checkCol) == 0) {
    mysqli_query($conn, "ALTER TABLE users ADD COLUMN forum_wallpaper VARCHAR(255) DEFAULT NULL");
    mysqli_query($conn, "ALTER TABLE users ADD COLUMN forum_wallpaper_opacity FLOAT DEFAULT 0.5");
}

// Handle GET - ambil settings
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $stmt = mysqli_prepare($conn, "SELECT forum_wallpaper, forum_wallpaper_opacity FROM users WHERE id = ?");
    mysqli_stmt_bind_param($stmt, "i", $user_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $user = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);
    
    echo json_encode([
        'success' => true,
        'wallpaper' => $user['forum_wallpaper'] ?? null,
        'opacity' => floatval($user['forum_wallpaper_opacity'] ?? 0.5)
    ]);
    exit;
}

// Handle POST - upload atau update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // Update opacity only
    if (isset($_POST['opacity'])) {
        $opacity = floatval($_POST['opacity']);
        $opacity = max(0, min(1, $opacity)); // Clamp 0-1
        
        $stmt = mysqli_prepare($conn, "UPDATE users SET forum_wallpaper_opacity = ? WHERE id = ?");
        mysqli_stmt_bind_param($stmt, "di", $opacity, $user_id);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        
        echo json_encode(['success' => true, 'opacity' => $opacity]);
        exit;
    }
    
    // Remove wallpaper
    if (isset($_POST['remove'])) {
        // Get current wallpaper to delete file
        $stmt = mysqli_prepare($conn, "SELECT forum_wallpaper FROM users WHERE id = ?");
        mysqli_stmt_bind_param($stmt, "i", $user_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $user = mysqli_fetch_assoc($result);
        mysqli_stmt_close($stmt);
        
        if ($user['forum_wallpaper'] && strpos($user['forum_wallpaper'], 'uploads/') !== false) {
            $file = '../' . $user['forum_wallpaper'];
            if (file_exists($file)) {
                unlink($file);
            }
        }
        
        $stmt = mysqli_prepare($conn, "UPDATE users SET forum_wallpaper = NULL WHERE id = ?");
        mysqli_stmt_bind_param($stmt, "i", $user_id);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        
        echo json_encode(['success' => true, 'message' => 'Wallpaper dihapus']);
        exit;
    }
    
    // Upload new wallpaper
    if (isset($_FILES['wallpaper']) && $_FILES['wallpaper']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['wallpaper'];
        $allowedTypes = ['image/jpeg', 'image/png', 'image/webp'];
        $maxSize = 5 * 1024 * 1024; // 5MB
        
        // Validate
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);
        
        if (!in_array($mimeType, $allowedTypes)) {
            http_response_code(400);
            echo json_encode(['error' => 'Format harus JPG, PNG, atau WEBP']);
            exit;
        }
        
        if ($file['size'] > $maxSize) {
            http_response_code(400);
            echo json_encode(['error' => 'Ukuran maksimal 5MB']);
            exit;
        }
        
        // Delete old wallpaper
        $stmt = mysqli_prepare($conn, "SELECT forum_wallpaper FROM users WHERE id = ?");
        mysqli_stmt_bind_param($stmt, "i", $user_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $user = mysqli_fetch_assoc($result);
        mysqli_stmt_close($stmt);
        
        if ($user['forum_wallpaper'] && strpos($user['forum_wallpaper'], 'uploads/') !== false) {
            $oldFile = '../' . $user['forum_wallpaper'];
            if (file_exists($oldFile)) {
                unlink($oldFile);
            }
        }
        
        // Save new file
        $uploadDir = '../uploads/wallpapers/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }
        
        $ext = $mimeType === 'image/jpeg' ? 'jpg' : ($mimeType === 'image/png' ? 'png' : 'webp');
        $filename = 'wallpaper_' . $user_id . '_' . time() . '.' . $ext;
        $filepath = $uploadDir . $filename;
        $dbPath = 'uploads/wallpapers/' . $filename;
        
        if (move_uploaded_file($file['tmp_name'], $filepath)) {
            $stmt = mysqli_prepare($conn, "UPDATE users SET forum_wallpaper = ? WHERE id = ?");
            mysqli_stmt_bind_param($stmt, "si", $dbPath, $user_id);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
            
            echo json_encode([
                'success' => true,
                'wallpaper' => $dbPath
            ]);
        } else {
            http_response_code(500);
            echo json_encode(['error' => 'Gagal upload file']);
        }
        exit;
    }
    
    http_response_code(400);
    echo json_encode(['error' => 'Request tidak valid']);
}
?>
