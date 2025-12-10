<?php
/**
 * Helper functions untuk sistem notifikasi
 */

/**
 * Buat notifikasi untuk satu user
 */
function createNotification($conn, $user_id, $type, $title, $message = '', $link = null, $data = null) {
    $stmt = $conn->prepare("INSERT INTO notifications (user_id, type, title, message, link, data) VALUES (?, ?, ?, ?, ?, ?)");
    $data_json = $data ? json_encode($data) : null;
    $stmt->bind_param("isssss", $user_id, $type, $title, $message, $link, $data_json);
    return $stmt->execute();
}

/**
 * Broadcast notifikasi ke semua user aktif
 */
function broadcastNotification($conn, $type, $title, $message = '', $link = null, $data = null) {
    // Get all active users
    $result = $conn->query("SELECT id FROM users WHERE id > 0");
    
    if (!$result) return false;
    
    $data_json = $data ? json_encode($data) : null;
    $stmt = $conn->prepare("INSERT INTO notifications (user_id, type, title, message, link, data) VALUES (?, ?, ?, ?, ?, ?)");
    
    $count = 0;
    while ($user = $result->fetch_assoc()) {
        $user_id = $user['id'];
        $stmt->bind_param("isssss", $user_id, $type, $title, $message, $link, $data_json);
        if ($stmt->execute()) {
            $count++;
        }
    }
    
    $stmt->close();
    return $count;
}

/**
 * Buat notifikasi pengumuman baru
 */
function notifyNewAnnouncement($conn, $title, $content, $type = 'info') {
    $icons = [
        'info' => '📢',
        'warning' => '⚠️',
        'danger' => '🚨',
        'success' => '✅'
    ];
    $icon = $icons[$type] ?? '📢';
    
    return broadcastNotification(
        $conn,
        'announcement',
        "$icon $title",
        mb_substr($content, 0, 100) . (mb_strlen($content) > 100 ? '...' : ''),
        'dashboard.php',
        ['announcement_type' => $type]
    );
}

/**
 * Buat notifikasi event dimulai
 */
function notifyEventStarted($conn, $event_id, $event_name) {
    return broadcastNotification(
        $conn,
        'event',
        "🎯 Event Dimulai!",
        "Event \"$event_name\" sudah dibuka. Segera lakukan absensi!",
        'dashboard.php',
        ['event_id' => $event_id]
    );
}

/**
 * Buat notifikasi iuran baru
 */
function notifyNewIuran($conn, $nama, $nominal, $deadline = null) {
    $nominal_formatted = 'Rp ' . number_format($nominal, 0, ',', '.');
    $deadline_text = $deadline ? " (deadline: " . date('d M Y', strtotime($deadline)) . ")" : "";
    
    return broadcastNotification(
        $conn,
        'iuran',
        "💰 Iuran Baru: $nama",
        "Nominal: $nominal_formatted$deadline_text",
        'iuran.php',
        ['nominal' => $nominal, 'deadline' => $deadline]
    );
}

/**
 * Buat notifikasi pembayaran iuran dikonfirmasi
 */
function notifyPaymentConfirmed($conn, $user_id, $iuran_name, $nominal) {
    $nominal_formatted = 'Rp ' . number_format($nominal, 0, ',', '.');
    
    return createNotification(
        $conn,
        $user_id,
        'payment',
        "✅ Pembayaran Dikonfirmasi",
        "Pembayaran iuran \"$iuran_name\" ($nominal_formatted) telah dikonfirmasi",
        'iuran.php',
        ['iuran_name' => $iuran_name, 'nominal' => $nominal]
    );
}
?>
