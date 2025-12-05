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

// Get user's sticker collection
$userStickers = [];
$result = mysqli_query($conn, "
    SELECT s.id, s.file_url, s.pack_id 
    FROM user_stickers us 
    JOIN stickers s ON us.sticker_id = s.id 
    WHERE us.user_id = $user_id 
    ORDER BY us.created_at DESC
");
while ($row = mysqli_fetch_assoc($result)) {
    $userStickers[] = $row;
}

// Get sticker packs with their stickers
$packs = [];
$packResult = mysqli_query($conn, "SELECT * FROM sticker_packs ORDER BY id");
while ($pack = mysqli_fetch_assoc($packResult)) {
    $pack['stickers'] = [];
    $stickerResult = mysqli_query($conn, "SELECT id, file_url FROM stickers WHERE pack_id = {$pack['id']} ORDER BY id");
    while ($sticker = mysqli_fetch_assoc($stickerResult)) {
        $pack['stickers'][] = $sticker;
    }
    if (count($pack['stickers']) > 0) {
        $packs[] = $pack;
    }
}

// Get recent stickers (uploaded by anyone, for discovery)
$recentStickers = [];
$recentResult = mysqli_query($conn, "
    SELECT s.id, s.file_url, u.nama as uploader 
    FROM stickers s 
    LEFT JOIN users u ON s.uploaded_by = u.id 
    WHERE s.pack_id IS NULL 
    ORDER BY s.created_at DESC 
    LIMIT 20
");
while ($row = mysqli_fetch_assoc($recentResult)) {
    $recentStickers[] = $row;
}

echo json_encode([
    'success' => true,
    'my_stickers' => $userStickers,
    'packs' => $packs,
    'recent' => $recentStickers
]);
?>
