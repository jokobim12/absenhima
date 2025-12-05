<?php
session_start();
header('Content-Type: application/json');

include "../config/koneksi.php";
include "sticker_init.php";

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

initStickerTables($conn);

$user_id = intval($_SESSION['user_id']);
$input = json_decode(file_get_contents('php://input'), true);

$action = $input['action'] ?? 'save';

if ($action === 'save') {
    // Save sticker by ID (curi stiker)
    if (isset($input['sticker_id'])) {
        $sticker_id = intval($input['sticker_id']);
        
        // Check if sticker exists
        $check = mysqli_query($conn, "SELECT id FROM stickers WHERE id = $sticker_id");
        if (mysqli_num_rows($check) == 0) {
            http_response_code(404);
            echo json_encode(['error' => 'Stiker tidak ditemukan']);
            exit;
        }
        
        // Check if already saved
        $checkSaved = mysqli_query($conn, "SELECT id FROM user_stickers WHERE user_id = $user_id AND sticker_id = $sticker_id");
        if (mysqli_num_rows($checkSaved) > 0) {
            echo json_encode(['success' => true, 'message' => 'Stiker sudah ada di koleksi']);
            exit;
        }
        
        $stmt = mysqli_prepare($conn, "INSERT INTO user_stickers (user_id, sticker_id) VALUES (?, ?)");
        mysqli_stmt_bind_param($stmt, "ii", $user_id, $sticker_id);
        
        if (mysqli_stmt_execute($stmt)) {
            mysqli_stmt_close($stmt);
            echo json_encode(['success' => true, 'message' => 'Stiker berhasil disimpan']);
        } else {
            http_response_code(500);
            echo json_encode(['error' => 'Gagal menyimpan stiker']);
        }
    }
    // Save sticker by URL (from chat message)
    else if (isset($input['file_url'])) {
        $file_url = trim($input['file_url']);
        
        // Check if sticker with this URL exists
        $stmt = mysqli_prepare($conn, "SELECT id FROM stickers WHERE file_url = ?");
        mysqli_stmt_bind_param($stmt, "s", $file_url);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $existing = mysqli_fetch_assoc($result);
        mysqli_stmt_close($stmt);
        
        if ($existing) {
            $sticker_id = $existing['id'];
        } else {
            // Create new sticker entry
            $stmt = mysqli_prepare($conn, "INSERT INTO stickers (file_url) VALUES (?)");
            mysqli_stmt_bind_param($stmt, "s", $file_url);
            mysqli_stmt_execute($stmt);
            $sticker_id = mysqli_insert_id($conn);
            mysqli_stmt_close($stmt);
        }
        
        // Add to user's collection
        $stmt = mysqli_prepare($conn, "INSERT IGNORE INTO user_stickers (user_id, sticker_id) VALUES (?, ?)");
        mysqli_stmt_bind_param($stmt, "ii", $user_id, $sticker_id);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        
        echo json_encode(['success' => true, 'message' => 'Stiker berhasil disimpan']);
    }
    else {
        http_response_code(400);
        echo json_encode(['error' => 'sticker_id atau file_url diperlukan']);
    }
}
else if ($action === 'remove') {
    $sticker_id = intval($input['sticker_id'] ?? 0);
    
    if ($sticker_id <= 0) {
        http_response_code(400);
        echo json_encode(['error' => 'sticker_id diperlukan']);
        exit;
    }
    
    $stmt = mysqli_prepare($conn, "DELETE FROM user_stickers WHERE user_id = ? AND sticker_id = ?");
    mysqli_stmt_bind_param($stmt, "ii", $user_id, $sticker_id);
    
    if (mysqli_stmt_execute($stmt)) {
        mysqli_stmt_close($stmt);
        echo json_encode(['success' => true, 'message' => 'Stiker dihapus dari koleksi']);
    } else {
        http_response_code(500);
        echo json_encode(['error' => 'Gagal menghapus stiker']);
    }
}
else {
    http_response_code(400);
    echo json_encode(['error' => 'Action tidak valid']);
}
?>
